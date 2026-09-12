<?php

declare(strict_types=1);

namespace Moves\Core;

use DateTimeImmutable;
use Moves\Boot\Connection;
use PDO;

/**
 * Moves | Login Throttle
 *
 * Limita tentativas de autenticação por origem e identificador.
 *
 * @author Djalma Martins
 * @package Moves\Core
 */
final class LoginThrottle
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_SECONDS = 900;

    public static function blocked(string $email, string $ip): bool
    {
        $statement = Connection::getInstance()->prepare(
            'SELECT attempts, window_started_at, blocked_until
             FROM login_attempts
             WHERE key_hash = :key_hash'
        );
        $statement->execute(['key_hash' => self::key($email, $ip)]);
        $attempt = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($attempt)) {
            return false;
        }

        $now = new DateTimeImmutable();
        $blockedUntil = $attempt['blocked_until'] !== null
            ? new DateTimeImmutable((string) $attempt['blocked_until'])
            : null;

        if ($blockedUntil !== null && $blockedUntil > $now) {
            return true;
        }

        $window = new DateTimeImmutable((string) $attempt['window_started_at']);

        if ($window->getTimestamp() + self::WINDOW_SECONDS <= $now->getTimestamp()) {
            self::clear($email, $ip);
        }

        return false;
    }

    public static function recordFailure(string $email, string $ip): void
    {
        $pdo = Connection::getInstance();
        $key = self::key($email, $ip);
        $now = new DateTimeImmutable();
        $pdo->beginTransaction();

        try {
            $statement = $pdo->prepare(
                'SELECT attempts, window_started_at
                 FROM login_attempts
                 WHERE key_hash = :key_hash
                 FOR UPDATE'
            );
            $statement->execute(['key_hash' => $key]);
            $attempt = $statement->fetch(PDO::FETCH_ASSOC);
            $attempts = 1;
            $windowStartedAt = $now;

            if (is_array($attempt)) {
                $currentWindow = new DateTimeImmutable(
                    (string) $attempt['window_started_at']
                );

                if (
                    $currentWindow->getTimestamp() + self::WINDOW_SECONDS
                    > $now->getTimestamp()
                ) {
                    $attempts = (int) $attempt['attempts'] + 1;
                    $windowStartedAt = $currentWindow;
                }
            }

            $blockedUntil = $attempts >= self::MAX_ATTEMPTS
                ? $now->modify('+' . self::WINDOW_SECONDS . ' seconds')
                : null;

            $statement = $pdo->prepare(
                'INSERT INTO login_attempts
                    (key_hash, attempts, window_started_at, blocked_until)
                 VALUES
                    (:key_hash, :attempts, :window_started_at, :blocked_until)
                 ON DUPLICATE KEY UPDATE
                    attempts = VALUES(attempts),
                    window_started_at = VALUES(window_started_at),
                    blocked_until = VALUES(blocked_until)'
            );
            $statement->execute([
                'key_hash' => $key,
                'attempts' => $attempts,
                'window_started_at' => $windowStartedAt->format('Y-m-d H:i:s'),
                'blocked_until' => $blockedUntil?->format('Y-m-d H:i:s'),
            ]);
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public static function clear(string $email, string $ip): void
    {
        $statement = Connection::getInstance()->prepare(
            'DELETE FROM login_attempts WHERE key_hash = :key_hash'
        );
        $statement->execute(['key_hash' => self::key($email, $ip)]);
    }

    private static function key(string $email, string $ip): string
    {
        return hash('sha256', strtolower(trim($email)) . '|' . $ip);
    }
}
