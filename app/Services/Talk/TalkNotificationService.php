<?php

declare(strict_types=1);

namespace Moves\Services\Talk;

use Moves\Boot\Connection;
use PDO;

final class TalkNotificationService
{
    public function __construct(private ?int $tenantId = null) {}
    private function tenantId(): int { return $this->tenantId ??= (new TalkTenantContext())->currentTenantId(); }
    public function notifyTicketAssignee(int $ticketId, ?int $actorId, string $type, string $title, ?string $body = null, array $payload = []): void
    {
        $pdo = Connection::getInstance();
        $statement = $pdo->prepare("SELECT t.assigned_user_id, COALESCE(us.notifications_enabled,1) enabled FROM talk_tickets t LEFT JOIN talk_user_settings us ON us.tenant_id=t.tenant_id AND us.user_id=t.assigned_user_id WHERE t.tenant_id=:tenant_id AND t.id=:ticket_id LIMIT 1");
        $statement->execute(['tenant_id'=>$this->tenantId(),'ticket_id'=>$ticketId]);
        $target = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$target || $target['assigned_user_id'] === null || (int)$target['enabled'] !== 1) { return; }
        $recipientId = (int)$target['assigned_user_id'];
        if ($actorId !== null && $recipientId === $actorId) { return; }
        $this->create($recipientId, $ticketId, $type, $title, $body, $payload);
    }

    public function notifyUser(int $recipientId, ?int $ticketId, string $type, string $title, ?string $body = null, array $payload = []): void
    {
        (new TalkTenantContext())->forUser($recipientId,$this->tenantId());
        $statement = Connection::getInstance()->prepare('SELECT COALESCE(notifications_enabled,1) FROM talk_user_settings WHERE tenant_id=:tenant_id AND user_id=:user_id');
        $statement->execute(['tenant_id'=>$this->tenantId(),'user_id'=>$recipientId]);
        $enabled = $statement->fetchColumn();
        if ($enabled !== false && (int)$enabled !== 1) { return; }
        $this->create($recipientId, $ticketId, $type, $title, $body, $payload);
    }

    public function unread(int $userId, int $limit = 30): array
    {
        $limit = max(1, min(100, $limit));
        $statement = Connection::getInstance()->prepare("SELECT n.*,t.protocol FROM talk_notifications n LEFT JOIN talk_tickets t ON t.tenant_id=n.tenant_id AND t.id=n.ticket_id WHERE n.tenant_id=:tenant_id AND n.recipient_id=:user_id AND n.read_at IS NULL ORDER BY n.created_at DESC,n.id DESC LIMIT {$limit}");
        $statement->execute(['tenant_id'=>$this->tenantId(),'user_id'=>$userId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function unreadCount(int $userId): int
    {
        $statement = Connection::getInstance()->prepare('SELECT COUNT(*) FROM talk_notifications WHERE tenant_id=:tenant_id AND recipient_id=:user_id AND read_at IS NULL');
        $statement->execute(['tenant_id'=>$this->tenantId(),'user_id'=>$userId]);
        return (int)$statement->fetchColumn();
    }

    public function markRead(int $notificationId, int $userId): bool
    {
        $statement = Connection::getInstance()->prepare('UPDATE talk_notifications SET read_at=NOW() WHERE tenant_id=:tenant_id AND id=:id AND recipient_id=:user_id AND read_at IS NULL');
        $statement->execute(['tenant_id'=>$this->tenantId(),'id'=>$notificationId,'user_id'=>$userId]);
        return $statement->rowCount() === 1;
    }

    public function markAllRead(int $userId): int
    {
        $statement = Connection::getInstance()->prepare('UPDATE talk_notifications SET read_at=NOW() WHERE tenant_id=:tenant_id AND recipient_id=:user_id AND read_at IS NULL');
        $statement->execute(['tenant_id'=>$this->tenantId(),'user_id'=>$userId]);
        return $statement->rowCount();
    }

    private function create(int $recipientId, ?int $ticketId, string $type, string $title, ?string $body, array $payload): void
    {
        $statement = Connection::getInstance()->prepare('INSERT INTO talk_notifications(tenant_id,recipient_id,ticket_id,type,title,body,payload) VALUES(:tenant_id,:recipient_id,:ticket_id,:type,:title,:body,:payload)');
        $statement->execute([
            'tenant_id'=>$this->tenantId(),
            'recipient_id'=>$recipientId,
            'ticket_id'=>$ticketId,
            'type'=>mb_substr($type,0,60),
            'title'=>mb_substr($title,0,190),
            'body'=>$body !== null ? mb_substr($body,0,500) : null,
            'payload'=>$payload === [] ? null : json_encode($payload,JSON_THROW_ON_ERROR),
        ]);
    }
}
