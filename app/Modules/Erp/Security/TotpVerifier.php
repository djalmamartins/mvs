<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Security;

/**
 * RFC 6238 compatible TOTP verification for ERP sensitive profiles.
 *
 * Secrets are expected as Base32 and are never persisted or logged here.
 */
final class TotpVerifier
{
    public function __construct(
        private readonly int $period = 30,
        private readonly int $digits = 6,
        private readonly int $window = 1
    ) {
        if ($period < 1 || $digits < 6 || $digits > 8 || $window < 0 || $window > 10) {
            throw new \InvalidArgumentException('Invalid TOTP configuration.');
        }
    }

    public function verify(string $base32Secret, string $code, ?int $timestamp = null): bool
    {
        if (!preg_match('/^\d{' . $this->digits . '}$/D', $code)) {
            return false;
        }

        $secret = $this->decodeBase32($base32Secret);
        if ($secret === null || $secret === '') {
            return false;
        }

        $counter = intdiv($timestamp ?? time(), $this->period);
        for ($offset = -$this->window; $offset <= $this->window; $offset++) {
            $candidateCounter = $counter + $offset;
            if ($candidateCounter < 0) {
                continue;
            }

            if (hash_equals($this->codeForCounter($secret, $candidateCounter), $code)) {
                return true;
            }
        }

        return false;
    }

    private function codeForCounter(string $secret, int $counter): string
    {
        $binaryCounter = pack('N2', intdiv($counter, 0x100000000), $counter % 0x100000000);
        $hash = hash_hmac('sha1', $binaryCounter, $secret, true);
        $offset = ord($hash[19]) & 0x0f;
        $value = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);

        $modulo = 10 ** $this->digits;

        return str_pad((string) ($value % $modulo), $this->digits, '0', STR_PAD_LEFT);
    }

    private function decodeBase32(string $value): ?string
    {
        $value = strtoupper(preg_replace('/[\s-]+/', '', $value) ?? '');
        $value = rtrim($value, '=');
        if ($value === '' || preg_match('/[^A-Z2-7]/', $value)) {
            return null;
        }

        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $buffer = 0;
        $bits = 0;
        $output = '';

        foreach (str_split($value) as $character) {
            $index = strpos($alphabet, $character);
            if ($index === false) {
                return null;
            }

            $buffer = ($buffer << 5) | $index;
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $output .= chr(($buffer >> $bits) & 0xff);
                $buffer &= (1 << $bits) - 1;
            }
        }

        return $output;
    }
}
