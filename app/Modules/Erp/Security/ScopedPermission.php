<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Security;

use InvalidArgumentException;

/**
 * One explicit ERP permission grant bound to an authorization scope.
 *
 * The wildcard permission is intentionally unsupported: sensitive ERP access
 * must always be granted by a named capability and a concrete scope.
 */
final readonly class ScopedPermission
{
    public function __construct(
        public string $permission,
        public AccessScope $scope,
    ) {
        if (!preg_match('/^[a-z][a-z0-9]*(?:\.[a-z0-9]+)+$/', $permission)) {
            throw new InvalidArgumentException('ERP permission must be a named dotted capability.');
        }
    }

    public function allows(string $permission, AccessScope $scope): bool
    {
        return hash_equals($this->permission, $permission) && $this->scope->equals($scope);
    }
}
