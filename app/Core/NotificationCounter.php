<?php
declare(strict_types=1);

namespace Moves\Core;

use Moves\Boot\Connection;
use PDO;
use Throwable;

final class NotificationCounter
{
    public static function unreadFor(int $userId): int
    {
        try {
            $statement = Connection::getInstance()->prepare('SELECT COUNT(*) FROM notifications WHERE read_at IS NULL AND (recipient_id IS NULL OR recipient_id=?)');
            $statement->execute([$userId]);
            return (int) $statement->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    /** @return array<int, array<string, mixed>> */
    public static function recentFor(int $userId, int $limit = 5): array
    {
        try {
            $limit = max(1, min(10, $limit));
            $statement = Connection::getInstance()->prepare(
                'SELECT id,title,message,action_url,link,read_at,created_at FROM notifications WHERE recipient_id IS NULL OR recipient_id=? ORDER BY id DESC LIMIT '.$limit
            );
            $statement->execute([$userId]);
            return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }
}
