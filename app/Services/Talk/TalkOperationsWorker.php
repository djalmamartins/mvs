<?php

declare(strict_types=1);

namespace Moves\Services\Talk;

use Moves\Boot\Connection;
use PDO;

final class TalkOperationsWorker
{
    /** @return array{assigned:int,jack:int} */
    public function processDue(): array
    {
        $pdo = Connection::getInstance();
        $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        $tenantIds = $pdo->query("SELECT id FROM talk_tenants WHERE status='active' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
        $result = ['assigned' => 0, 'jack' => 0];

        foreach ($tenantIds as $tenantId) {
            $tenantId = (int) $tenantId;
            $lock = 'moves_talk_ops_' . substr(sha1($database), 0, 12) . '_' . $tenantId;
            $statement = $pdo->prepare('SELECT GET_LOCK(:lock_name,0)');
            $statement->execute(['lock_name' => $lock]);
            if ((int) $statement->fetchColumn() !== 1) {
                continue;
            }
            try {
                $result['assigned'] += (new TalkService($tenantId))->autoAssign();
                $result['jack'] += (new TalkJackService($tenantId))->processEligible();
            } finally {
                $statement = $pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
                $statement->execute(['lock_name' => $lock]);
            }
        }

        return $result;
    }
}
