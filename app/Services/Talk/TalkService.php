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
