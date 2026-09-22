<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Security;

/**
 * Resolves persisted ERP grants against a server-controlled route scope.
 *
 * This service is intentionally deny-by-default: invalid users, missing route
 * scope, malformed capabilities and absent/revoked grants never authorize.
 */
final readonly class ScopedAccess
{
    public function __construct(private ScopeGrantRepository $grants)
    {
    }

    /** @param array<string, mixed> $routeAttributes */
    public function allows(int $userId, string $capability, array $routeAttributes): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $scope = ScopeContext::fromRoute($routeAttributes);
        if ($scope === null) {
            return false;
        }

        try {
            foreach ($this->grants->activeForUser($userId) as $grant) {
                if ($grant->allows($capability, $scope)) {
                    return true;
                }
            }
        } catch (\InvalidArgumentException) {
            return false;
        }

        return false;
    }
}
