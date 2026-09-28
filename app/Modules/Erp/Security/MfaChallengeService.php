<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Security;

/**
 * Verifies a TOTP challenge only for users with an active encrypted enrollment.
 * Authentication policy decides which profiles must present the challenge.
 */
final readonly class MfaChallengeService
{
    public function __construct(
        private MfaEnrollmentRepository $enrollments,
        private TotpVerifier $verifier
    ) {
    }

    public function verifyTotp(int $userId, string $code, ?int $timestamp = null): bool
    {
        if ($userId <= 0 || preg_match('/^\d{6}$/D', $code) !== 1) {
            return false;
        }

        try {
            $secret = $this->enrollments->activeTotpSecret($userId);
        } catch (\Throwable) {
            return false;
        }

        if ($secret === null || $secret === '') {
            return false;
        }

        try {
            return $this->verifier->verify($secret, $code, $timestamp);
        } catch (\Throwable) {
            return false;
        }
    }
}
