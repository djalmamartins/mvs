<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Security;

/**
 * Central policy for roles that must complete MFA before accessing sensitive ERP capabilities.
 * Unknown/empty roles are not promoted to sensitive access by this policy.
 */
final class MfaRequirementPolicy
{
    /** @var list<string> */
    private const SENSITIVE_ROLES = [
        'admin',
        'administrator',
        'superadmin',
        'supervisor',
        'finance',
        'financial',
        'manager',
    ];

    public function requiresMfa(?string $role): bool
    {
        if ($role === null) {
            return false;
        }

        $normalized = strtolower(trim($role));
        if ($normalized === '') {
            return false;
        }

        return in_array($normalized, self::SENSITIVE_ROLES, true);
    }
}
