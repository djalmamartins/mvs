<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Cadastros;

use Moves\Boot\Connection;
use Moves\Modules\Erp\Security\ScopeGrantRepository;
use Moves\Modules\Erp\Security\ScopedAccess;
use Moves\Modules\Erp\Security\SecurityAuditRepository;
use Moves\Modules\Erp\Security\AdministratorTenantAccess;
use Moves\Modules\Erp\Security\AccessScope;
use PDO;

/**
 * Canonical runtime composition for ERP cadastro application boundaries.
 *
 * Controllers/API consumers must resolve services here instead of rebuilding
 * authorization/audit dependencies independently.
 */
final class CadastroServiceFactory
{
    public static function access(?PDO $pdo = null): CadastroAccessService
    {
        $pdo ??= Connection::getInstance();
        $tenantAccess = new AdministratorTenantAccess($pdo);
        $scopedAccess = new ScopedAccess(
            new ScopeGrantRepository($pdo),
            static fn (int $userId, AccessScope $scope): bool => $tenantAccess->allows($userId, $scope)
        );

        return new CadastroAccessService(
            new AdministratorRepository($pdo),
            new CondominiumRepository($pdo),
            $scopedAccess,
        );
    }

    public static function write(?PDO $pdo = null): CadastroWriteService
    {
        $pdo ??= Connection::getInstance();
        $tenantAccess = new AdministratorTenantAccess($pdo);
        $scopedAccess = new ScopedAccess(
            new ScopeGrantRepository($pdo),
            static fn (int $userId, AccessScope $scope): bool => $tenantAccess->allows($userId, $scope)
        );

        return new CadastroWriteService(
            new AdministratorRepository($pdo),
            new CondominiumRepository($pdo),
            $scopedAccess,
            new SecurityAuditRepository($pdo),
        );
    }
}
