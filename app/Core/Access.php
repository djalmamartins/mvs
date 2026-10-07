<?php

declare(strict_types=1);

namespace Moves\Core;

use Moves\Boot\Connection;
use Moves\Services\Platform\TenantAuthorization;
use Moves\Services\Platform\TenantContext;

/**
 * Moves | Access
 *
 * Centraliza as regras de autorização da aplicação.
 *
 * @author Djalma Martins
 * @package Moves\Core
 */
final class Access
{
    /**
     * Verifica se o usuário autenticado possui
     * uma determinada permissão.
     */
    public static function can(string $permission): bool
    {
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        // Every authenticated user can view their own profile. Other
        // capabilities are scoped to the active tenant and must come from
        // that tenant's role grants, never the legacy global users.role.
        if ($permission === 'profile.view') {
            return true;
        }

        try {
            $pdo = Connection::getInstance();
            $tenantId = (new TenantContext($pdo))->currentId((int) $user->id);
            return (new TenantAuthorization($pdo))->can((int) $user->id, $tenantId, $permission);
        } catch (\Throwable) {
            // Tenant-scoped capabilities fail closed when context or storage
            // cannot be resolved; global role fallbacks would cross tenants.
            return false;
        }
    }

    /**
     * Verifica se o usuário autenticado
     * possui um determinado papel.
     */
    public static function is(string $role): bool
    {
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        return (string) ($user->role ?? 'user') === $role;
    }
}
