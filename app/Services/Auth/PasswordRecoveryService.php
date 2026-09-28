<?php

declare(strict_types=1);

namespace Moves\Services\Auth;

use Closure;
use DateTimeImmutable;
use Moves\Contracts\RecoveryMailer;
use PDO;
use Throwable;

final class PasswordRecoveryService
{
    public const EXPIRY_MINUTES = 15;
    public const MAX_REQUESTS = 3;
    public const MAX_ATTEMPTS = 5;
    public const RESEND_COOLDOWN_SECONDS = 60;

    /** @var Closure():DateTimeImmutable */
    private Closure $clock;

    /** @param null|Closure():DateTimeImmutable $clock */
    public function __construct(
        private PDO $pdo,
        private RecoveryMailer $mailer,
        ?Closure $clock = null
    ) {
        $this->clock = $clock ?? static fn (): DateTimeImmutable => new DateTimeImmutable();
    }

    /**
     * @return array{request_id:int,email:string,masked_email:string,expires_in_minutes:int,rate_limited:bool,retry_after:int}
     */
    public function request(string $email, string $ip): array
    {
        $email = strtolower(trim($email));
        $emailHash = hash('sha256', $email);
        $ipHash = hash('sha256', $ip);
        $now = $this->now();
        $latest = $this->latestRequest($emailHash, $ipHash);
        $since = $now->modify('-' . self::EXPIRY_MINUTES . ' minutes')->format('Y-m-d H:i:s');

        $rate = $this->pdo->prepare(
            'SELECT COUNT(*) AS request_count,MIN(created_at) AS oldest_created_at '
            . 'FROM password_recovery_requests '
            . 'WHERE email_hash=:email_hash AND requested_ip_hash=:ip_hash AND created_at>=:since'
        );
        $rate->execute([
            'email_hash' => $emailHash,
            'ip_hash' => $ipHash,
            'since' => $since,
        ]);
        $rateWindow = $rate->fetch(PDO::FETCH_ASSOC);
        $requestCount = (int) $rateWindow['request_count'];

        $retryAfter = $latest === null ? 0 : $this->retryAfter($latest, $now);
        if ($requestCount >= self::MAX_REQUESTS) {
            $oldest = new DateTimeImmutable((string) $rateWindow['oldest_created_at']);
            $windowRetry = $oldest->modify('+' . self::EXPIRY_MINUTES . ' minutes')->getTimestamp()
                - $now->getTimestamp();
            $retryAfter = max($retryAfter, $windowRetry);
        }
        if ($latest !== null && ($retryAfter > 0 || $requestCount >= self::MAX_REQUESTS)) {
            return $this->requestResult((int) $latest['id'], $email, true, $retryAfter);
        }

        $lookup = $this->pdo->prepare(
            'SELECT id,name,email FROM users WHERE LOWER(email)=:email AND status=:status LIMIT 1'
        );
        $lookup->execute(['email' => $email, 'status' => 'active']);
        $user = $lookup->fetch(PDO::FETCH_ASSOC);
        $code = (string) random_int(100000, 999999);
        $expiresAt = $now->modify('+' . self::EXPIRY_MINUTES . ' minutes')->format('Y-m-d H:i:s');

        $this->pdo->beginTransaction();
        try {
            if (is_array($user)) {
                $invalidate = $this->pdo->prepare(
                    'UPDATE password_recovery_requests SET used_at=:used_at '
                    . 'WHERE user_id=:user_id AND used_at IS NULL'
                );
                $invalidate->execute([
                    'used_at' => $now->format('Y-m-d H:i:s'),
                    'user_id' => (int) $user['id'],
                ]);
            }

            $insert = $this->pdo->prepare(
                'INSERT INTO password_recovery_requests '
                . '(user_id,email_hash,code_hash,requested_ip_hash,expires_at,created_at) '
                . 'VALUES (:user_id,:email_hash,:code_hash,:ip_hash,:expires_at,:created_at)'
            );
            $insert->execute([
                'user_id' => is_array($user) ? (int) $user['id'] : null,
                'email_hash' => $emailHash,
                'code_hash' => password_hash($code, PASSWORD_DEFAULT),
                'ip_hash' => $ipHash,
                'expires_at' => $expiresAt,
                'created_at' => $now->format('Y-m-d H:i:s'),
            ]);
            $requestId = (int) $this->pdo->lastInsertId();
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }

        if (is_array($user)) {
            try {
                $this->mailer->sendRecoveryCode(
                    (string) $user['email'],
                    (string) $user['name'],
                    $code,
                    self::EXPIRY_MINUTES
                );
            } catch (Throwable $exception) {
                $discard = $this->pdo->prepare('DELETE FROM password_recovery_requests WHERE id=:id');
                $discard->execute(['id' => $requestId]);

                throw $exception;
            }
        }

        return $this->requestResult($requestId, $email, false, 0);
    }

    /** @return array{status:string,remaining_attempts:int} */
    public function verify(int $requestId, string $email, string $code): array
    {
        $this->pdo->beginTransaction();
        try {
            $suffix = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $statement = $this->pdo->prepare(
                'SELECT id,user_id,code_hash,attempts,expires_at,used_at,verified_at '
                . 'FROM password_recovery_requests WHERE id=:id AND email_hash=:email_hash LIMIT 1'
                . $suffix
            );
            $statement->execute([
                'id' => $requestId,
                'email_hash' => hash('sha256', strtolower(trim($email))),
            ]);
            $request = $statement->fetch(PDO::FETCH_ASSOC);

            if (!is_array($request)) {
                $this->pdo->commit();

                return ['status' => 'invalid', 'remaining_attempts' => 0];
            }

            if ($request['verified_at'] !== null) {
                $this->pdo->commit();

                return [
                    'status' => 'verified',
                    'remaining_attempts' => max(0, self::MAX_ATTEMPTS - (int) $request['attempts']),
                ];
            }

            if ($request['used_at'] !== null || (int) $request['attempts'] >= self::MAX_ATTEMPTS) {
                $this->pdo->commit();

                return ['status' => 'locked', 'remaining_attempts' => 0];
            }

            if (new DateTimeImmutable((string) $request['expires_at']) <= $this->now()) {
                $expire = $this->pdo->prepare(
                    'UPDATE password_recovery_requests SET used_at=:used_at WHERE id=:id AND used_at IS NULL'
                );
                $expire->execute(['used_at' => $this->now()->format('Y-m-d H:i:s'), 'id' => $requestId]);
                $this->pdo->commit();

                return ['status' => 'expired', 'remaining_attempts' => 0];
            }

            $attempts = (int) $request['attempts'] + 1;
            $matches = password_verify($code, (string) $request['code_hash']);
            if ($matches && $request['user_id'] !== null) {
                $verify = $this->pdo->prepare(
                    'UPDATE password_recovery_requests '
                    . 'SET attempts=:attempts,verified_at=:verified_at,expires_at=:reset_expires_at WHERE id=:id'
                );
                $verify->execute([
                    'attempts' => $attempts,
                    'verified_at' => $this->now()->format('Y-m-d H:i:s'),
                    'reset_expires_at' => $this->now()
                        ->modify('+' . self::EXPIRY_MINUTES . ' minutes')
                        ->format('Y-m-d H:i:s'),
                    'id' => $requestId,
                ]);
                $this->pdo->commit();

                return [
                    'status' => 'verified',
                    'remaining_attempts' => max(0, self::MAX_ATTEMPTS - $attempts),
                ];
            }

            $failed = $this->pdo->prepare(
                'UPDATE password_recovery_requests '
                . 'SET attempts=:attempts,used_at=:used_at WHERE id=:id'
            );
            $failed->execute([
                'attempts' => $attempts,
                'used_at' => $attempts >= self::MAX_ATTEMPTS
                    ? $this->now()->format('Y-m-d H:i:s')
                    : null,
                'id' => $requestId,
            ]);
            $this->pdo->commit();

            return [
                'status' => $attempts >= self::MAX_ATTEMPTS ? 'locked' : 'invalid',
                'remaining_attempts' => max(0, self::MAX_ATTEMPTS - $attempts),
            ];
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function resetPassword(int $requestId, string $email, string $newPassword): string
    {
        if (!PasswordPolicy::accepts($newPassword)) {
            return 'weak';
        }

        $this->pdo->beginTransaction();
        try {
            $suffix = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $statement = $this->pdo->prepare(
                'SELECT r.id,r.user_id,r.expires_at,r.verified_at,r.used_at,'
                . 'u.password AS current_password,u.status AS user_status '
                . 'FROM password_recovery_requests r LEFT JOIN users u ON u.id=r.user_id '
                . 'WHERE r.id=:id AND r.email_hash=:email_hash LIMIT 1'
                . $suffix
            );
            $statement->execute([
                'id' => $requestId,
                'email_hash' => hash('sha256', strtolower(trim($email))),
            ]);
            $request = $statement->fetch(PDO::FETCH_ASSOC);

            if (
                !is_array($request)
                || $request['user_id'] === null
                || $request['verified_at'] === null
                || $request['used_at'] !== null
                || $request['user_status'] !== 'active'
            ) {
                $this->pdo->commit();

                return 'invalid';
            }

            if (new DateTimeImmutable((string) $request['expires_at']) <= $this->now()) {
                $expire = $this->pdo->prepare(
                    'UPDATE password_recovery_requests SET used_at=:used_at WHERE id=:id AND used_at IS NULL'
                );
                $expire->execute(['used_at' => $this->now()->format('Y-m-d H:i:s'), 'id' => $requestId]);
                $this->pdo->commit();

                return 'expired';
            }

            if (password_verify($newPassword, (string) $request['current_password'])) {
                $this->pdo->commit();

                return 'reused';
            }

            $userId = (int) $request['user_id'];
            $update = $this->pdo->prepare(
                'UPDATE users SET password=:password WHERE id=:id AND status=:status'
            );
            $update->execute([
                'password' => password_hash($newPassword, PASSWORD_DEFAULT),
                'id' => $userId,
                'status' => 'active',
            ]);
            if ($update->rowCount() !== 1) {
                $this->pdo->rollBack();

                return 'invalid';
            }

            $consume = $this->pdo->prepare(
                'UPDATE password_recovery_requests SET used_at=:used_at '
                . 'WHERE user_id=:user_id AND used_at IS NULL'
            );
            $consume->execute([
                'used_at' => $this->now()->format('Y-m-d H:i:s'),
                'user_id' => $userId,
            ]);
            $this->pdo->commit();

            return 'reset';
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    /** @return array<string,mixed>|null */
    private function latestRequest(string $emailHash, string $ipHash): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id,created_at FROM password_recovery_requests '
            . 'WHERE email_hash=:email_hash AND requested_ip_hash=:ip_hash ORDER BY id DESC LIMIT 1'
        );
        $statement->execute(['email_hash' => $emailHash, 'ip_hash' => $ipHash]);
        $request = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($request) ? $request : null;
    }

    /** @param array<string,mixed> $request */
    private function retryAfter(array $request, DateTimeImmutable $now): int
    {
        $availableAt = (new DateTimeImmutable((string) $request['created_at']))
            ->modify('+' . self::RESEND_COOLDOWN_SECONDS . ' seconds');

        return max(0, $availableAt->getTimestamp() - $now->getTimestamp());
    }

    /**
     * @return array{request_id:int,email:string,masked_email:string,expires_in_minutes:int,rate_limited:bool,retry_after:int}
     */
    private function requestResult(
        int $requestId,
        string $email,
        bool $rateLimited,
        int $retryAfter
    ): array {
        return [
            'request_id' => $requestId,
            'email' => $email,
            'masked_email' => $this->maskEmail($email),
            'expires_in_minutes' => self::EXPIRY_MINUTES,
            'rate_limited' => $rateLimited,
            'retry_after' => $retryAfter,
        ];
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $visible = mb_substr($local, 0, min(2, mb_strlen($local)));

        return $visible . str_repeat('•', max(3, mb_strlen($local) - mb_strlen($visible))) . '@' . $domain;
    }

    private function now(): DateTimeImmutable
    {
        return ($this->clock)();
    }
}
