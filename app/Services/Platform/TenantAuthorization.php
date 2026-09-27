<?php

declare(strict_types=1);

namespace Moves\Services\Platform;

use PDO;

final readonly class TenantAuthorization
{
    public function __construct(private PDO $pdo)
    {
    }

    public function can(int $userId, int $tenantId, string $permission): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT COUNT(*) FROM talk_tenant_users m
             JOIN talk_tenants t ON t.id=m.tenant_id AND t.status='active'
             JOIN platform_roles r ON r.id=m.role_id AND r.tenant_id=m.tenant_id
             JOIN platform_role_permissions rp ON rp.role_id=r.id
             JOIN platform_permissions p ON p.id=rp.permission_id AND p.slug=:permission
             WHERE m.user_id=:user_id AND m.tenant_id=:tenant_id AND m.status='active'"
        );
        $statement->execute(['permission' => $permission, 'user_id' => $userId, 'tenant_id' => $tenantId]);
        return (int) $statement->fetchColumn() > 0;
    }
}
