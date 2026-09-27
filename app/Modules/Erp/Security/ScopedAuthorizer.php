<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Security;

/**
 * Avalia autorização ERP combinando permissão global e vínculos de escopo.
 *
 * O caller fornece as permissões já resolvidas e os escopos vinculados ao
 * usuário. Esta classe não consulta sessão/banco e, por padrão, nega acesso.
 */
final class ScopedAuthorizer
{
    /**
     * @param array<int, string> $permissions
     * @param array<int, AccessScope> $scopes
     */
    public static function allows(
        string $permission,
        AccessScope $requiredScope,
        array $permissions,
        array $scopes,
    ): bool {
        if (!in_array($permission, $permissions, true)) {
            return false;
        }

        foreach ($scopes as $scope) {
            if ($scope->equals($requiredScope)) {
                return true;
            }
        }

        return false;
    }
}
