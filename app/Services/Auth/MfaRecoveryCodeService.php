<?php

declare(strict_types=1);

namespace Moves\Services\Auth;

use PDO;
use Throwable;

final readonly class MfaRecoveryCodeService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function consume(int $userId, string $code): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $normalized = strtoupper(str_replace(['-', ' '], '', trim($code)));
        if (preg_match('/^[A-Z2-7]{12}$/D', $normalized) !== 1) {
            return false;
        }

        try {
            $this->pdo->beginTransaction();
            $statement = $this->pdo->prepare(
                'SELECT id, code_hash FROM platform_mfa_recovery_codes
                 WHERE user_id=:user_id AND used_at IS NULL
                 ORDER BY id ASC'
            );
            $statement->execute(['user_id' => $userId]);

            $matchedId = null;
            while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                if (password_verify($normalized, (string) $row['code_hash'])) {
                    $matchedId = (int) $row['id'];
                    break;
                }
            }

            if ($matchedId === null) {
                $this->pdo->rollBack();
                return false;
            }

            $update = $this->pdo->prepare(
                'UPDATE platform_mfa_recovery_codes
                 SET used_at=CURRENT_TIMESTAMP
                 WHERE id=:id AND user_id=:user_id AND used_at IS NULL'
            );
            $update->execute(['id' => $matchedId, 'user_id' => $userId]);
            if ($update->rowCount() !== 1) {
                $this->pdo->rollBack();
                return false;
            }

            $this->pdo->commit();
            return true;
        } catch (Throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return false;
        }
    }
}
