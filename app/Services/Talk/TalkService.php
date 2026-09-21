<?php

declare(strict_types=1);

namespace Moves\Services\Talk;

use Moves\Boot\Connection;
use PDO;

final class TalkService
{
    public function dashboard(): array
    {
        $pdo = Connection::getInstance();
        $counts = [
            'queued' => $this->count($pdo, "SELECT COUNT(*) FROM talk_tickets WHERE status = 'queued'"),
            'active' => $this->count($pdo, "SELECT COUNT(*) FROM talk_tickets WHERE status IN ('assigned','open')"),
            'closed_today' => $this->count($pdo, "SELECT COUNT(*) FROM talk_tickets WHERE status = 'closed' AND DATE(closed_at) = CURRENT_DATE"),
            'contacts' => $this->count($pdo, 'SELECT COUNT(*) FROM talk_contacts'),
        ];
        return ['counts' => $counts, 'queue' => $this->queue()];
    }

    public function queue(): array
    {
        $statement = Connection::getInstance()->query(
            "SELECT t.id, t.protocol, t.subject, t.priority, t.status, t.queued_at, t.created_at,
                    c.name AS contact_name, c.phone AS contact_phone,
                    q.name AS queue_name, u.name AS assigned_name,
                    cv.channel
               FROM talk_tickets t
               INNER JOIN talk_conversations cv ON cv.id = t.conversation_id
               INNER JOIN talk_contacts c ON c.id = cv.contact_id
               LEFT JOIN talk_queues q ON q.id = t.queue_id
               LEFT JOIN users u ON u.id = t.assigned_user_id
              WHERE t.status = 'queued'
              ORDER BY FIELD(t.priority, 'urgent','high','normal','low'), COALESCE(t.queued_at,t.created_at) ASC, t.id ASC
              LIMIT 100"
        );
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function claim(int $ticketId, int $userId): bool
    {
        $pdo = Connection::getInstance();
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare("SELECT id, queue_id, status FROM talk_tickets WHERE id = :id FOR UPDATE");
            $statement->execute(['id' => $ticketId]);
            $ticket = $statement->fetch(PDO::FETCH_ASSOC);
            if (!$ticket || $ticket['status'] !== 'queued') {
                $pdo->rollBack();
                return false;
            }

            if ($ticket['queue_id'] !== null) {
                $eligible = $pdo->prepare(
                    "SELECT COUNT(*) FROM talk_queue_members
                     WHERE queue_id = :queue_id AND user_id = :user_id AND status = 'active'"
                );
                $eligible->execute(['queue_id' => $ticket['queue_id'], 'user_id' => $userId]);
                if ((int) $eligible->fetchColumn() === 0) {
                    $pdo->rollBack();
                    return false;
                }
            }

            $update = $pdo->prepare(
                "UPDATE talk_tickets SET assigned_user_id = :user_id, status = 'assigned',
                        assigned_at = NOW(), updated_at = NOW()
                 WHERE id = :id AND status = 'queued'"
            );
            $update->execute(['user_id' => $userId, 'id' => $ticketId]);
            if ($update->rowCount() !== 1) {
                $pdo->rollBack();
                return false;
            }

            $event = $pdo->prepare(
                "INSERT INTO talk_events (ticket_id, user_id, actor_type, event_type, payload)
                 VALUES (:ticket_id, :user_id, 'user', 'ticket.claimed', :payload)"
            );
            $event->execute([
                'ticket_id' => $ticketId,
                'user_id' => $userId,
                'payload' => json_encode(['assigned_user_id' => $userId], JSON_THROW_ON_ERROR),
            ]);
            $pdo->commit();
            return true;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function seedSimulation(int $actorId): int
    {
        $pdo = Connection::getInstance();
        $pdo->beginTransaction();
        try {
            $pdo->exec("INSERT IGNORE INTO talk_departments (name,slug,status) VALUES ('Atendimento','atendimento','active')");
            $departmentId = (int) $pdo->query("SELECT id FROM talk_departments WHERE slug='atendimento'")->fetchColumn();
            $queue = $pdo->prepare("INSERT IGNORE INTO talk_queues (department_id,name,slug,status,auto_assign_after_seconds) VALUES (:department_id,'Atendimento geral','atendimento-geral','active',30)");
            $queue->execute(['department_id' => $departmentId]);
            $queueId = (int) $pdo->query("SELECT id FROM talk_queues WHERE slug='atendimento-geral'")->fetchColumn();
            $member = $pdo->prepare("INSERT INTO talk_queue_members(queue_id,user_id,role,capacity,status) VALUES(:queue_id,:user_id,'agent',5,'active') ON DUPLICATE KEY UPDATE status='active'");
            $member->execute(['queue_id' => $queueId, 'user_id' => $actorId]);

            $external = 'sim:' . bin2hex(random_bytes(6));
            $contact = $pdo->prepare("INSERT INTO talk_contacts(name,phone,external_id,channel,metadata) VALUES('Cliente de simulação','SIM-0001',:external_id,'simulation',:metadata)");
            $contact->execute(['external_id' => $external, 'metadata' => json_encode(['simulation' => true], JSON_THROW_ON_ERROR)]);
            $contactId = (int) $pdo->lastInsertId();

            $conversationExternal = $external . ':conversation';
            $conversation = $pdo->prepare("INSERT INTO talk_conversations(contact_id,channel,external_id,status,last_message_at) VALUES(:contact_id,'simulation',:external_id,'open',NOW())");
            $conversation->execute(['contact_id' => $contactId, 'external_id' => $conversationExternal]);
            $conversationId = (int) $pdo->lastInsertId();

            $protocol = 'SIM-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $ticket = $pdo->prepare("INSERT INTO talk_tickets(protocol,conversation_id,queue_id,status,priority,subject,source,queued_at) VALUES(:protocol,:conversation_id,:queue_id,'queued','normal','Atendimento de simulação','simulation',NOW())");
            $ticket->execute(['protocol' => $protocol, 'conversation_id' => $conversationId, 'queue_id' => $queueId]);
            $ticketId = (int) $pdo->lastInsertId();

            $message = $pdo->prepare("INSERT INTO talk_messages(conversation_id,ticket_id,sender_type,direction,type,body,sent_at,metadata) VALUES(:conversation_id,:ticket_id,'contact','inbound','text','Olá, preciso de ajuda com meu atendimento.',NOW(),:metadata)");
            $message->execute(['conversation_id' => $conversationId, 'ticket_id' => $ticketId, 'metadata' => json_encode(['simulation' => true], JSON_THROW_ON_ERROR)]);

            $event = $pdo->prepare("INSERT INTO talk_events(ticket_id,user_id,actor_type,event_type,payload) VALUES(:ticket_id,:user_id,'system','ticket.created',:payload)");
            $event->execute(['ticket_id' => $ticketId, 'user_id' => $actorId, 'payload' => json_encode(['source' => 'simulation'], JSON_THROW_ON_ERROR)]);
            $pdo->commit();
            return $ticketId;
        } catch (\\Throwable $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $exception;
        }
    }

    public function ticket(int $ticketId): ?array
    {
        $pdo = Connection::getInstance();
        $statement = $pdo->prepare(
            "SELECT t.*, c.name AS contact_name, c.phone AS contact_phone, cv.channel, q.name AS queue_name, u.name AS assigned_name
               FROM talk_tickets t
               INNER JOIN talk_conversations cv ON cv.id=t.conversation_id
               INNER JOIN talk_contacts c ON c.id=cv.contact_id
               LEFT JOIN talk_queues q ON q.id=t.queue_id
               LEFT JOIN users u ON u.id=t.assigned_user_id
              WHERE t.id=:id LIMIT 1"
        );
        $statement->execute(['id' => $ticketId]);
        $ticket = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$ticket) { return null; }
        $messages = $pdo->prepare("SELECT m.*, u.name AS sender_name FROM talk_messages m LEFT JOIN users u ON u.id=m.sender_user_id WHERE m.ticket_id=:id ORDER BY m.sent_at ASC,m.id ASC");
        $messages->execute(['id' => $ticketId]);
        $events = $pdo->prepare("SELECT e.*, u.name AS user_name FROM talk_events e LEFT JOIN users u ON u.id=e.user_id WHERE e.ticket_id=:id ORDER BY e.created_at DESC,e.id DESC LIMIT 50");
        $events->execute(['id' => $ticketId]);
        $ticket['messages'] = $messages->fetchAll(PDO::FETCH_ASSOC);
        $ticket['events'] = $events->fetchAll(PDO::FETCH_ASSOC);
        return $ticket;
    }

    public function sendSimulationMessage(int $ticketId, int $userId, string $body): void
    {
        $body = trim($body);
        if ($body === '') { return; }
        $ticket = $this->ticket($ticketId);
        if ($ticket === null || $ticket['source'] !== 'simulation' || (int)($ticket['assigned_user_id'] ?? 0) !== $userId) {
            throw new \\RuntimeException('Atendimento de simulação indisponível para este usuário.');
        }
        Connection::getInstance()->prepare("INSERT INTO talk_messages(conversation_id,ticket_id,sender_type,sender_user_id,direction,type,body,sent_at,metadata) VALUES(:conversation_id,:ticket_id,'user',:user_id,'outbound','text',:body,NOW(),:metadata)")
            ->execute(['conversation_id'=>$ticket['conversation_id'],'ticket_id'=>$ticketId,'user_id'=>$userId,'body'=>$body,'metadata'=>json_encode(['simulation'=>true], JSON_THROW_ON_ERROR)]);
    }

    public function conversations(): array
    {
        return Connection::getInstance()->query(
            "SELECT cv.id, cv.channel, cv.status, cv.last_message_at, c.name AS contact_name, c.phone,
                    t.protocol, t.status AS ticket_status, q.name AS queue_name, u.name AS assigned_name
               FROM talk_conversations cv
               INNER JOIN talk_contacts c ON c.id = cv.contact_id
               LEFT JOIN talk_tickets t ON t.id = (
                   SELECT tt.id FROM talk_tickets tt WHERE tt.conversation_id = cv.id ORDER BY tt.id DESC LIMIT 1
               )
               LEFT JOIN talk_queues q ON q.id = t.queue_id
               LEFT JOIN users u ON u.id = t.assigned_user_id
              ORDER BY COALESCE(cv.last_message_at, cv.created_at) DESC LIMIT 100"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contacts(): array
    {
        return Connection::getInstance()->query(
            'SELECT id, name, phone, email, channel, created_at, updated_at FROM talk_contacts ORDER BY updated_at DESC, id DESC LIMIT 100'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    private function count(PDO $pdo, string $sql): int
    {
        return (int) $pdo->query($sql)->fetchColumn();
    }
}
