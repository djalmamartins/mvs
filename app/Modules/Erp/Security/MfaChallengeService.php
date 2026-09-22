<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Security;

/**
 * Verifies an MFA challenge without exposing the persisted TOTP secret to callers.
 * Missing/invalid users, missing enrollment and invalid codes all fail closed.
 */
final readonly class MfaChallengeService
{
    public function __construct(
        private MfaEnrollmentRepository $enrollments,
        private TotpVerifier $totp
    ) {
    }

    public function verifyTotp(int $userId, string $code, ?int $timestamp = null): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $secret = $this->enrollments->activeTotpSecret($userId);
        if ($secret === null || $secret === '') {
            return false;
        }

        return $this->totp->verify($secret, $code, $timestamp);
    }
}
