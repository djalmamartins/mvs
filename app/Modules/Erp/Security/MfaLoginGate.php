<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Security;

/**
 * Centralizes the MFA decision at the authentication boundary.
 * Sensitive roles fail closed: a valid TOTP is required before session grant.
 */
final readonly class MfaLoginGate
{
    public function __construct(
        private MfaRequirementPolicy $policy,
        private MfaChallengeService $challenge
    ) {
    }

    public function canEstablishSession(
        int $userId,
        ?string $role,
        ?string $totpCode,
        ?int $timestamp = null
    ): bool {
        if (!$this->policy->requiresMfa($role)) {
            return true;
        }

        if ($userId <= 0 || $totpCode === null) {
            return false;
        }

        $code = trim($totpCode);
        if ($code === '') {
            return false;
        }

        return $this->challenge->verifyTotp($userId, $code, $timestamp);
    }
}
