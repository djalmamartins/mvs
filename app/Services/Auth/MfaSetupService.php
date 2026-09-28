<?php

declare(strict_types=1);

namespace Moves\Services\Auth;

use chillerlan\QRCode\QRCode;
use Closure;
use Moves\Modules\Erp\Security\MfaEnrollmentRepository;
use Moves\Modules\Erp\Security\TotpVerifier;
use PDO;
use RuntimeException;
use Throwable;

final class MfaSetupService
{
    /** @var Closure():string */
    private Closure $secretGenerator;

    /** @param null|Closure():string $secretGenerator */
    public function __construct(
        private PDO $pdo,
        private MfaEnrollmentRepository $enrollments,
        private TotpVerifier $verifier,
        ?Closure $secretGenerator = null
    ) {
        $this->secretGenerator = $secretGenerator ?? fn (): string => $this->base32(random_bytes(20));
    }

    /** @return array{secret:string,uri:string,qr:string} */
    public function begin(int $userId, string $email): array
    {
        if ($this->enrollments->hasActiveTotp($userId)) {
            throw new RuntimeException('MFA já está ativo.');
        }

        $secret = ($this->secretGenerator)();
        $this->enrollments->stageTotp($userId, $secret);

        return $this->setupPayload($email, $secret);
    }

    /** @return array{secret:string,uri:string,qr:string}|null */
    public function pending(int $userId, string $email): ?array
    {
        $secret = $this->enrollments->pendingTotpSecret($userId);

        return $secret === null ? null : $this->setupPayload($email, $secret);
    }

    /** @return array<int,string>|null */
    public function confirm(int $userId, string $code, ?int $timestamp = null): ?array
    {
        $secret = $this->enrollments->pendingTotpSecret($userId);
        if ($secret === null || !$this->verifier->verify($secret, trim($code), $timestamp)) {
            return null;
        }

        $codes = [];
        for ($index = 0; $index < 8; $index++) {
            $value = substr($this->base32(random_bytes(8)), 0, 12);
            $codes[] = implode('-', str_split($value, 4));
        }

        $this->pdo->beginTransaction();
        try {
            if (!$this->enrollments->enablePendingTotp($userId, $userId)) {
                throw new RuntimeException('Não foi possível ativar o MFA.');
            }
            $delete = $this->pdo->prepare('DELETE FROM platform_mfa_recovery_codes WHERE user_id=:user_id');
            $delete->execute(['user_id' => $userId]);
            $insert = $this->pdo->prepare(
                'INSERT INTO platform_mfa_recovery_codes (user_id,code_hash) VALUES (:user_id,:code_hash)'
            );
            foreach ($codes as $recoveryCode) {
                $insert->execute([
                    'user_id' => $userId,
                    'code_hash' => password_hash($this->normalizeRecoveryCode($recoveryCode), PASSWORD_DEFAULT),
                ]);
            }
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        return $codes;
    }

    public function enabled(int $userId): bool
    {
        return $this->enrollments->hasActiveTotp($userId);
    }

    /** @return array{secret:string,uri:string,qr:string} */
    private function setupPayload(string $email, string $secret): array
    {
        $label = rawurlencode('Moves:' . strtolower(trim($email)));
        $uri = 'otpauth://totp/' . $label . '?secret=' . rawurlencode($secret)
            . '&issuer=Moves&algorithm=SHA1&digits=6&period=30';

        return ['secret' => $secret, 'uri' => $uri, 'qr' => (string) (new QRCode())->render($uri)];
    }

    private function normalizeRecoveryCode(string $code): string
    {
        return strtoupper(str_replace(['-', ' '], '', trim($code)));
    }

    private function base32(string $bytes): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $buffer = 0;
        $bits = 0;
        $output = '';
        foreach (str_split($bytes) as $byte) {
            $buffer = ($buffer << 8) | ord($byte);
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $output .= $alphabet[($buffer >> $bits) & 31];
                $buffer &= (1 << $bits) - 1;
            }
        }
        if ($bits > 0) {
            $output .= $alphabet[($buffer << (5 - $bits)) & 31];
        }

        return $output;
    }
}
