<?php

declare(strict_types=1);

namespace Moves\Services\Talk;

use DateTimeImmutable;
use DateTimeZone;
use Moves\Boot\Connection;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class TalkInboundService
{
    public function receiveWhatsApp(array $payload): int
    {
        $stage = 'validate';
        $externalId = mb_substr(trim((string) ($payload['external_id'] ?? '')), 0, 191);
        $phone = preg_replace('/\D+/', '', (string) ($payload['from'] ?? '')) ?? '';
        $senderJid = mb_substr(trim((string) ($payload['from_jid'] ?? '')), 0, 190);
        if ($senderJid !== '' && preg_match('/^\d+@(s\.whatsapp\.net|lid)$/', $senderJid) !== 1) $senderJid = '';
        $externalAddress = $senderJid !== '' ? $senderJid : ($phone !== '' ? $phone.'@s.whatsapp.net' : '');
        $body = mb_substr(trim((string) ($payload['body'] ?? '')), 0, 4000);
        $pushName = mb_substr(trim((string) ($payload['push_name'] ?? '')), 0, 160);
        $messageType = mb_substr(trim((string) ($payload['type'] ?? 'text')), 0, 30) ?: 'text';

        if ($externalId === '' || $externalAddress === '') {
            throw new RuntimeException('Mensagem WhatsApp inválida.');
        }

        $sentAt = $this->sentAt($payload['timestamp'] ?? null);
        $stage = 'connect';
        $pdo = Connection::getInstance();
        $stage = 'deduplicate';
        $duplicate = $pdo->prepare('SELECT ticket_id FROM talk_messages WHERE external_id=:external_id LIMIT 1');
        $duplicate->execute(['external_id' => $externalId]);
        $existingTicket = $duplicate->fetchColumn();
        if ($existingTicket !== false) {
            return 0;
        }

        $pdo->beginTransaction();
        try {
            $stage = 'contact';
            $contactId = $this->contact($pdo, $externalAddress, $phone, $pushName);
            $stage = 'conversation';
            $conversationId = $this->conversation($pdo, $contactId, $externalAddress);
            $stage = 'ticket';
            $ticketId = $this->activeTicket($pdo, $conversationId);
            if ($ticketId === 0) {
                $ticketId = $this->createTicket($pdo, $conversationId);
            }

            $stage = 'message';
            $insert = $pdo->prepare("INSERT INTO talk_messages(conversation_id,ticket_id,sender_type,external_id,direction,type,body,metadata,sent_at) VALUES(:conversation_id,:ticket_id,'contact',:external_id,'inbound',:type,:body,:metadata,:sent_at)");
            $insert->execute([
                'conversation_id' => $conversationId,
                'ticket_id' => $ticketId,
                'external_id' => $externalId,
                'type' => $messageType,
                'body' => $body,
                'metadata' => json_encode(['channel' => 'whatsapp', 'push_name' => $pushName ?: null], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'sent_at' => $sentAt,
            ]);
            $messageId = (int) $pdo->lastInsertId();

            $stage = 'touch';
            $pdo->prepare("UPDATE talk_conversations SET status='open',last_message_at=GREATEST(COALESCE(last_message_at,:last_message),:received_at),updated_at=NOW() WHERE id=:id")
                ->execute(['last_message' => $sentAt, 'received_at' => $sentAt, 'id' => $conversationId]);
            $pdo->prepare('UPDATE talk_tickets SET last_activity_at=NOW(),updated_at=NOW() WHERE id=:id')
                ->execute(['id' => $ticketId]);
            $stage = 'event';
            $pdo->prepare("INSERT INTO talk_events(ticket_id,user_id,actor_type,event_type,payload) VALUES(:ticket_id,NULL,'contact','message.received',:payload)")
                ->execute([
                    'ticket_id' => $ticketId,
                    'payload' => json_encode(['message_id' => $messageId, 'external_id' => $externalId], JSON_THROW_ON_ERROR),
                ]);

            $pdo->commit();
            return $ticketId;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($exception instanceof PDOException && (string) $exception->getCode() === '23000') {
                $duplicate->execute(['external_id' => $externalId]);
                if ($duplicate->fetchColumn() !== false) return 0;
            }
            throw new RuntimeException('Inbound WhatsApp falhou em '.$stage.': '.$exception->getMessage(), 0, $exception);
        }
    }

    private function contact(PDO $pdo, string $externalAddress, string $phone, string $pushName): int
    {
        $select = $pdo->prepare("SELECT id,name FROM talk_contacts WHERE channel='whatsapp' AND (external_id=:external_id OR (:phone_guard<>'' AND phone=:phone_value)) ORDER BY id LIMIT 1 FOR UPDATE");
        $select->execute(['external_id' => $externalAddress, 'phone_guard' => $phone, 'phone_value' => $phone]);
        $contact = $select->fetch(PDO::FETCH_ASSOC);
        if ($contact) {
            if ($pushName !== '' && trim((string) ($contact['name'] ?? '')) !== $pushName) {
                $pdo->prepare('UPDATE talk_contacts SET name=:name,phone=NULLIF(:phone,\'\'),external_id=:external_id,updated_at=NOW() WHERE id=:id')
                    ->execute(['name' => $pushName, 'phone' => $phone, 'external_id' => $externalAddress, 'id' => $contact['id']]);
            }
            return (int) $contact['id'];
        }

        $insert = $pdo->prepare("INSERT INTO talk_contacts(name,phone,external_id,channel,metadata) VALUES(:name,:phone,:external_id,'whatsapp',:metadata)");
        $insert->execute([
            'name' => $pushName !== '' ? $pushName : null,
            'phone' => $phone !== '' ? $phone : null,
            'external_id' => $externalAddress,
            'metadata' => json_encode(['source' => 'baileys'], JSON_THROW_ON_ERROR),
        ]);
        return (int) $pdo->lastInsertId();
    }

    private function conversation(PDO $pdo, int $contactId, string $externalAddress): int
    {
        $select = $pdo->prepare("SELECT id FROM talk_conversations WHERE contact_id=:contact_id AND channel='whatsapp' ORDER BY id DESC LIMIT 1 FOR UPDATE");
        $select->execute(['contact_id' => $contactId]);
        $conversationId = (int) ($select->fetchColumn() ?: 0);
        if ($conversationId > 0) return $conversationId;

        $insert = $pdo->prepare("INSERT INTO talk_conversations(contact_id,channel,external_id,status,last_message_at) VALUES(:contact_id,'whatsapp',:external_id,'open',NULL)");
        $insert->execute(['contact_id' => $contactId, 'external_id' => 'wa:'.$externalAddress]);
        return (int) $pdo->lastInsertId();
    }

    private function activeTicket(PDO $pdo, int $conversationId): int
    {
        $select = $pdo->prepare("SELECT id FROM talk_tickets WHERE conversation_id=:conversation_id AND status<>'closed' ORDER BY id DESC LIMIT 1 FOR UPDATE");
        $select->execute(['conversation_id' => $conversationId]);
        return (int) ($select->fetchColumn() ?: 0);
    }

    private function createTicket(PDO $pdo, int $conversationId): int
    {
        $queueId = $this->queue($pdo);
        do {
            $protocol = 'TALK-'.date('Ymd').'-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
            $check = $pdo->prepare('SELECT COUNT(*) FROM talk_tickets WHERE protocol=:protocol');
            $check->execute(['protocol' => $protocol]);
        } while ((int) $check->fetchColumn() > 0);

        $insert = $pdo->prepare("INSERT INTO talk_tickets(conversation_id,protocol,queue_id,status,priority,source,queued_at,last_activity_at) VALUES(:conversation_id,:protocol,:queue_id,'queued','normal','whatsapp',NOW(),NOW())");
        $insert->execute(['conversation_id' => $conversationId, 'protocol' => $protocol, 'queue_id' => $queueId ?: null]);
        return (int) $pdo->lastInsertId();
    }

    private function queue(PDO $pdo): int
    {
        $queueId = (int) ($pdo->query("SELECT id FROM talk_queues WHERE status='active' ORDER BY id LIMIT 1")->fetchColumn() ?: 0);
        if ($queueId > 0) return $queueId;

        $pdo->exec("INSERT IGNORE INTO talk_departments(name,slug,status) VALUES('Atendimento','atendimento','active')");
        $departmentId = (int) $pdo->query("SELECT id FROM talk_departments WHERE slug='atendimento' LIMIT 1")->fetchColumn();
        $insert = $pdo->prepare("INSERT IGNORE INTO talk_queues(department_id,name,slug,status,auto_assign_after_seconds) VALUES(:department_id,'Atendimento geral','atendimento-geral','active',30)");
        $insert->execute(['department_id' => $departmentId]);
        return (int) $pdo->query("SELECT id FROM talk_queues WHERE slug='atendimento-geral' LIMIT 1")->fetchColumn();
    }

    private function sentAt(mixed $timestamp): string
    {
        $seconds = filter_var($timestamp, FILTER_VALIDATE_INT);
        if ($seconds === false || $seconds < 1 || $seconds > time() + 300) return date('Y-m-d H:i:s');
        return (new DateTimeImmutable('@'.$seconds))->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s');
    }
}
