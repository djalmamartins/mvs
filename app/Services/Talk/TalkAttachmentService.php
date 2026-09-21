<?php

declare(strict_types=1);

namespace Moves\Services\Talk;

use Moves\Boot\Connection;
use PDO;
use RuntimeException;

final class TalkAttachmentService
{
    private const MAX_BYTES = 10_485_760; // 10 MiB

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

    /** @return array<string,mixed> */
    public function store(int $ticketId, int $userId, array $file): array
    {
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
        $ticket = Connection::getInstance()->prepare('SELECT conversation_id FROM talk_tickets WHERE id=:id');
        $ticket->execute(['id' => $ticketId]);
        $conversationId = (int)$ticket->fetchColumn();
        if ($conversationId <= 0) {
            throw new RuntimeException('Atendimento não encontrado.');
        }

        $root = dirname(__DIR__, 3) . '/storage/talk/' . date('Y/m');
        if (!is_dir($root) && !mkdir($root, 0775, true) && !is_dir($root)) {
            throw new RuntimeException('Não foi possível preparar o armazenamento.');
        }
        $stored = bin2hex(random_bytes(20)) . '.' . self::ALLOWED[$mime];
        $target = $root . '/' . $stored;
        if (!move_uploaded_file($tmp, $target)) {
            throw new RuntimeException('Não foi possível salvar o anexo.');
        }
        $relative = 'storage/talk/' . date('Y/m') . '/' . $stored;
        $original = mb_substr(basename((string)($file['name'] ?? 'arquivo')), 0, 255);
        $pdo = Connection::getInstance();
        $pdo->beginTransaction();
        try {
            $message = $pdo->prepare("INSERT INTO talk_messages(conversation_id,ticket_id,sender_type,sender_user_id,direction,type,body,media_url,metadata,sent_at) VALUES(:conversation_id,:ticket_id,'user',:user_id,'outbound',:type,:body,:media_url,:metadata,NOW())");
            $message->execute([
                'conversation_id' => $conversationId,
                'ticket_id' => $ticketId,
                'user_id' => $userId,
                'type' => str_starts_with($mime, 'image/') ? 'image' : (str_starts_with($mime, 'audio/') ? 'audio' : (str_starts_with($mime, 'video/') ? 'video' : 'document')),
                'body' => $original,
                'media_url' => $relative,
                'metadata' => json_encode(['mime_type'=>$mime,'size_bytes'=>$size], JSON_THROW_ON_ERROR),
            ]);
            $messageId = (int)$pdo->lastInsertId();
            $attachment = $pdo->prepare('INSERT INTO talk_attachments(ticket_id,message_id,uploaded_by,original_name,stored_name,mime_type,size_bytes,storage_path) VALUES(:ticket_id,:message_id,:user_id,:original,:stored,:mime,:size,:path)');
            $attachment->execute(['ticket_id'=>$ticketId,'message_id'=>$messageId,'user_id'=>$userId,'original'=>$original,'stored'=>$stored,'mime'=>$mime,'size'=>$size,'path'=>$relative]);
            $id = (int)$pdo->lastInsertId();
            $pdo->prepare('UPDATE talk_tickets SET first_response_at=COALESCE(first_response_at,NOW()),last_activity_at=NOW(),updated_at=NOW() WHERE id=:id')->execute(['id'=>$ticketId]);
            $pdo->prepare('UPDATE talk_conversations SET last_message_at=NOW(),updated_at=NOW() WHERE id=:id')->execute(['id'=>$conversationId]);
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
        $s=Connection::getInstance()->prepare('SELECT * FROM talk_attachments WHERE id=:id LIMIT 1');
        $s->execute(['id'=>$id]);
        $row=$s->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return list<array<string,mixed>> */
    public function forTicket(int $ticketId): array
    {
        $s=Connection::getInstance()->prepare('SELECT a.*,u.name uploaded_by_name FROM talk_attachments a LEFT JOIN users u ON u.id=a.uploaded_by WHERE a.ticket_id=:ticket_id ORDER BY a.created_at,a.id');
        $s->execute(['ticket_id'=>$ticketId]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }
}
