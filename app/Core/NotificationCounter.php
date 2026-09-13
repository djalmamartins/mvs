<?php
declare(strict_types=1);
namespace Moves\Core;
use Moves\Boot\Connection;
use Throwable;
final class NotificationCounter
{
    public static function unreadFor(int $userId): int
    {
        try { $statement=Connection::getInstance()->prepare('SELECT COUNT(*) FROM notifications WHERE read_at IS NULL AND (recipient_id IS NULL OR recipient_id=?)'); $statement->execute([$userId]); return (int)$statement->fetchColumn(); }
        catch (Throwable) { return 0; }
    }
}
