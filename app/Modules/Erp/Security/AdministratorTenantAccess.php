<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Security;

use PDO;

/** Maps ERP scopes to the canonical Talk tenant membership and product gate. */
final readonly class AdministratorTenantAccess
{
    public function __construct(private PDO $pdo)
    {
    }

    public function allows(int $userId, AccessScope $scope): bool
    {
        $from = $scope->type === AccessScope::CONDOMINIUM
            ? "erp_condominiums condominium INNER JOIN erp_administrators administrator ON administrator.id=condominium.administrator_id AND condominium.status='active'"
            : 'erp_administrators administrator';
        $scopeColumn = $scope->type === AccessScope::CONDOMINIUM ? 'condominium.id' : 'administrator.id';
        $statement = $this->pdo->prepare(
            "SELECT COUNT(*) FROM {$from}
             INNER JOIN talk_tenants tenant ON tenant.id=administrator.tenant_id AND tenant.status='active'
             INNER JOIN talk_tenant_users member ON member.tenant_id=tenant.id AND member.user_id=:user_id AND member.status='active'
             INNER JOIN users account ON account.id=member.user_id AND account.status='active'
             INNER JOIN platform_tenant_products product ON product.tenant_id=tenant.id AND product.product='erp' AND product.enabled=1
             WHERE {$scopeColumn}=:scope_id AND administrator.status='active'"
        );
        $statement->execute(['user_id' => $userId, 'scope_id' => $scope->id]);
        return (int) $statement->fetchColumn() === 1;
    }

    public function hasAnyAdministrator(int $userId): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT 1 FROM erp_administrators administrator
             INNER JOIN talk_tenants tenant ON tenant.id=administrator.tenant_id AND tenant.status='active'
             INNER JOIN talk_tenant_users member ON member.tenant_id=tenant.id AND member.user_id=:user_id AND member.status='active'
             INNER JOIN users account ON account.id=member.user_id AND account.status='active'
             INNER JOIN platform_tenant_products product ON product.tenant_id=tenant.id AND product.product='erp' AND product.enabled=1
             INNER JOIN erp_scope_grants grant_record ON grant_record.user_id=member.user_id AND grant_record.revoked_at IS NULL
               AND ((grant_record.scope_type='administrator' AND grant_record.scope_id=administrator.id)
                 OR (grant_record.scope_type='condominium' AND grant_record.scope_id IN
                   (SELECT id FROM erp_condominiums WHERE administrator_id=administrator.id)))
             WHERE administrator.status='active' LIMIT 1"
        );
        $statement->execute(['user_id' => $userId]);
        return $statement->fetchColumn() !== false;
    }

    public function hasAdministrator(int $userId, int $tenantId): bool
    {
        if ($userId <= 0 || $tenantId <= 0) {
            return false;
        }

        $statement = $this->pdo->prepare(
            "SELECT 1 FROM erp_administrators administrator
             INNER JOIN talk_tenants tenant ON tenant.id=administrator.tenant_id AND tenant.status='active'
             INNER JOIN talk_tenant_users member ON member.tenant_id=tenant.id
               AND member.user_id=:user_id AND member.status='active'
             INNER JOIN users account ON account.id=member.user_id AND account.status='active'
             INNER JOIN platform_tenant_products product ON product.tenant_id=tenant.id
               AND product.product='erp' AND product.enabled=1
             INNER JOIN erp_scope_grants grant_record ON grant_record.user_id=member.user_id
               AND grant_record.revoked_at IS NULL
               AND ((grant_record.scope_type='administrator' AND grant_record.scope_id=administrator.id)
                 OR (grant_record.scope_type='condominium' AND grant_record.scope_id IN
                   (SELECT id FROM erp_condominiums WHERE administrator_id=administrator.id)))
             WHERE administrator.tenant_id=:tenant_id AND administrator.status='active'
             LIMIT 1"
        );
        $statement->execute(['user_id' => $userId, 'tenant_id' => $tenantId]);

        return $statement->fetchColumn() !== false;
    }
}
