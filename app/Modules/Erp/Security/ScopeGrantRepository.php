<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Security;

use PDO;

/**
 * Reads persisted ERP scope grants without ever treating revoked rows as access.
 */
final readonly class ScopeGrantRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<ScopedPermission> */
    public function activeForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $statement = $this->pdo->prepare(
            'SELECT capability, scope_type, scope_id
             FROM erp_scope_grants
             WHERE user_id = :user_id AND revoked_at IS NULL
             ORDER BY id ASC'
        );
        $statement->execute(['user_id' => $userId]);

        $grants = [];
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $grants[] = new ScopedPermission(
                (string) $row['capability'],
                new AccessScope((string) $row['scope_type'], (int) $row['scope_id'])
            );
        }

        return $grants;
    }
}
