<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Security;

use PDO;

/**
 * Persists TOTP enrollments without exposing plaintext secrets to storage.
 */
final readonly class MfaEnrollmentRepository
{
    public function __construct(
        private PDO $pdo,
        private MfaSecretCipher $cipher,
        private ?SecurityAuditRepository $audit = null
    ) {
    }

    public function enrollTotp(int $userId, string $secret, ?int $actorUserId = null): void
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Invalid MFA user.');
        }

        $wasEnrolled = $this->hasTotpEnrollment($userId);
        $encrypted = $this->cipher->encrypt($secret);
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $upsert = $driver === 'sqlite'
            ? "INSERT INTO erp_mfa_enrollments
                (user_id, method, secret_ciphertext, secret_key_id, enabled_at, disabled_at)
               VALUES (:user_id, 'totp', :ciphertext, :key_id, CURRENT_TIMESTAMP, NULL)
               ON CONFLICT(user_id, method) DO UPDATE SET
                  secret_ciphertext = excluded.secret_ciphertext,
                  secret_key_id = excluded.secret_key_id,
                  enabled_at = CURRENT_TIMESTAMP,
                  disabled_at = NULL"
            : "INSERT INTO erp_mfa_enrollments
                (user_id, method, secret_ciphertext, secret_key_id, enabled_at, disabled_at)
               VALUES (:user_id, 'totp', :ciphertext, :key_id, CURRENT_TIMESTAMP, NULL)
               ON DUPLICATE KEY UPDATE
                  secret_ciphertext = VALUES(secret_ciphertext),
                  secret_key_id = VALUES(secret_key_id),
                  enabled_at = CURRENT_TIMESTAMP,
                  disabled_at = NULL";

        $statement = $this->pdo->prepare($upsert);
        $statement->execute([
            'user_id' => $userId,
            'ciphertext' => $encrypted['ciphertext'],
            'key_id' => $encrypted['key_id'],
        ]);

        $this->audit?->append(
            $wasEnrolled ? 'mfa.totp.reenrolled' : 'mfa.totp.enrolled',
            $actorUserId ?? $userId,
            $userId,
            ['method' => 'totp']
        );
    }

    public function activeTotpSecret(int $userId): ?string
    {
        if ($userId <= 0) {
            return null;
        }

        $statement = $this->pdo->prepare(
            "SELECT secret_ciphertext, secret_key_id
             FROM erp_mfa_enrollments
             WHERE user_id = :user_id
               AND method = 'totp'
               AND enabled_at IS NOT NULL
               AND disabled_at IS NULL
             LIMIT 1"
        );
        $statement->execute(['user_id' => $userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->cipher->decrypt(
            (string) $row['secret_ciphertext'],
            (string) $row['secret_key_id']
        );
    }

    public function disableTotp(int $userId, ?int $actorUserId = null): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $statement = $this->pdo->prepare(
            "UPDATE erp_mfa_enrollments
             SET disabled_at = CURRENT_TIMESTAMP
             WHERE user_id = :user_id
               AND method = 'totp'
               AND disabled_at IS NULL"
        );
        $statement->execute(['user_id' => $userId]);
        $disabled = $statement->rowCount() > 0;

        if ($disabled) {
            $this->audit?->append(
                'mfa.totp.disabled',
                $actorUserId ?? $userId,
                $userId,
                ['method' => 'totp']
            );
        }

        return $disabled;
    }

    private function hasTotpEnrollment(int $userId): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT 1 FROM erp_mfa_enrollments
             WHERE user_id = :user_id AND method = 'totp'
             LIMIT 1"
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchColumn() !== false;
    }
}
