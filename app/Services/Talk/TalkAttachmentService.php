<?php

declare(strict_types=1);

namespace Moves\Services\Talk;

use Moves\Boot\Connection;
use PDO;
use RuntimeException;

final class TalkAttachmentService
{
    private const MAX_BYTES = 10_485_760;

    /** @var array<string,string> */
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
        'text/plain' => 'txt',
        'audio/mpeg' => 'mp3',
        'audio/ogg' => 'ogg',
        'audio/mp4' => 'm4a',
        'video/mp4' => 'mp4',
    ];

    public function __construct(private ?int $tenantId = null) {}
    private function tenantId(): int { return $this->tenantId ??= (new TalkTenantContext())->currentTenantId(); }

    /** @return array<string,mixed> */
    public function store(int $ticketId, int $userId, array $file): array
    {
        if (!(new TalkService($this->tenantId()))->canOperateTicket($ticketId, $userId)) {
            throw new RuntimeException('Você não tem acesso a este atendimento.');
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Não foi possível receber o anexo.');
        }
        $tmp = (string)($file['tmp_name'] ?? '');
        $size = (int)($file['size'] ?? 0);
        if ($tmp === '' || !is_uploaded_file($tmp) || $size <= 0 || $size > self::MAX_BYTES) {
            throw new RuntimeException('Anexo inválido ou maior que 10 MB.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: 'application/octet-stream';
        if (!isset(self::ALLOWED[$mime])) {
            throw new RuntimeException('Tipo de arquivo não permitido.');
        }

        $pdo = Connection::getInstance();
        $ticket = $pdo->prepare('SELECT conversation_id,channel_id,status FROM talk_tickets WHERE tenant_id=:tenant_id AND id=:id LIMIT 1');
        $ticket->execute(['tenant_id'=>$this->tenantId(),'id' => $ticketId]);
        $ticketRow = $ticket->fetch(PDO::FETCH_ASSOC);
        if (!$ticketRow || (int)$ticketRow['conversation_id'] <= 0) {
            throw new RuntimeException('Atendimento não encontrado.');
        }
        if ((string)$ticketRow['status'] === 'closed') {
            throw new RuntimeException('Reabra o atendimento antes de enviar anexos.');
        }
        $conversationId = (int)$ticketRow['conversation_id'];

        $month = date('Y/m');
        $root = dirname(__DIR__, 3) . '/storage/talk/' . $this->tenantId() . '/' . $month;
        if (!is_dir($root) && !mkdir($root, 0770, true) && !is_dir($root)) {
            throw new RuntimeException('Não foi possível preparar o armazenamento.');
        }
        $stored = bin2hex(random_bytes(20)) . '.' . self::ALLOWED[$mime];
        $target = $root . '/' . $stored;
        if (!move_uploaded_file($tmp, $target)) {
            throw new RuntimeException('Não foi possível salvar o anexo.');
        }
        @chmod($target, 0660);
        $relative = 'storage/talk/' . $this->tenantId() . '/' . $month . '/' . $stored;
        $original = $this->safeOriginalName((string)($file['name'] ?? 'arquivo'));

        $pdo->beginTransaction();
        try {
            $type = str_starts_with($mime, 'image/') ? 'image' : (str_starts_with($mime, 'audio/') ? 'audio' : (str_starts_with($mime, 'video/') ? 'video' : 'document'));
            $message = $pdo->prepare("INSERT INTO talk_messages(tenant_id,channel_id,conversation_id,ticket_id,sender_type,sender_user_id,direction,type,body,media_url,metadata,sent_at) VALUES(:tenant_id,:channel_id,:conversation_id,:ticket_id,'user',:user_id,'outbound',:type,:body,:media_url,:metadata,NOW())");
            $message->execute([
                'tenant_id'=>$this->tenantId(),
                'channel_id'=>(int)$ticketRow['channel_id'],
                'conversation_id' => $conversationId,
                'ticket_id' => $ticketId,
                'user_id' => $userId,
                'type' => $type,
                'body' => $original,
                'media_url' => $relative,
                'metadata' => json_encode(['mime_type'=>$mime,'size_bytes'=>$size], JSON_THROW_ON_ERROR),
            ]);
            $messageId = (int)$pdo->lastInsertId();
            $attachment = $pdo->prepare('INSERT INTO talk_attachments(tenant_id,ticket_id,message_id,uploaded_by,original_name,stored_name,mime_type,size_bytes,storage_path) VALUES(:tenant_id,:ticket_id,:message_id,:user_id,:original,:stored,:mime,:size,:path)');
            $attachment->execute(['tenant_id'=>$this->tenantId(),'ticket_id'=>$ticketId,'message_id'=>$messageId,'user_id'=>$userId,'original'=>$original,'stored'=>$stored,'mime'=>$mime,'size'=>$size,'path'=>$relative]);
            $id = (int)$pdo->lastInsertId();
            $pdo->prepare('UPDATE talk_tickets SET first_response_at=COALESCE(first_response_at,NOW()),last_activity_at=NOW(),updated_at=NOW() WHERE tenant_id=:tenant_id AND id=:id')->execute(['tenant_id'=>$this->tenantId(),'id'=>$ticketId]);
            $pdo->prepare('UPDATE talk_conversations SET last_message_at=NOW(),updated_at=NOW() WHERE tenant_id=:tenant_id AND id=:id')->execute(['tenant_id'=>$this->tenantId(),'id'=>$conversationId]);
            $pdo->commit();
            return $this->find($id) ?? [];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            @unlink($target);
            throw $e;
        }
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $s=Connection::getInstance()->prepare('SELECT * FROM talk_attachments WHERE tenant_id=:tenant_id AND id=:id LIMIT 1');
        $s->execute(['tenant_id'=>$this->tenantId(),'id'=>$id]);
        $row=$s->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return list<array<string,mixed>> */
    public function forTicket(int $ticketId): array
    {
        $s=Connection::getInstance()->prepare('SELECT a.*,u.name uploaded_by_name FROM talk_attachments a LEFT JOIN users u ON u.id=a.uploaded_by WHERE a.tenant_id=:tenant_id AND a.ticket_id=:ticket_id ORDER BY a.created_at,a.id');
        $s->execute(['tenant_id'=>$this->tenantId(),'ticket_id'=>$ticketId]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    private function safeOriginalName(string $name): string
    {
        $name = str_replace(["\0", "\r", "\n"], '', basename($name));
        $name = preg_replace('/[^\pL\pN._()\- ]/u', '_', $name) ?: 'arquivo';
        $name = trim($name, " .\t");
        return mb_substr($name !== '' ? $name : 'arquivo', 0, 180);
    }
}
