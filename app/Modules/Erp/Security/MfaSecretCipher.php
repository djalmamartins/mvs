<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Security;

/**
 * Encrypts MFA secrets before persistence using a versioned external key.
 *
 * The key material is supplied by configuration/runtime and is never stored
 * alongside the ciphertext. Persist keyId with the ciphertext so rotation can
 * be handled without guessing which key was used.
 */
final class MfaSecretCipher
{
    private const CIPHER = 'aes-256-gcm';
    private const IV_BYTES = 12;
    private const TAG_BYTES = 16;

    public function __construct(
        private readonly string $keyId,
        private readonly string $key
    ) {
        if ($this->keyId === '' || strlen($this->key) !== 32) {
            throw new \InvalidArgumentException('Invalid MFA encryption key configuration.');
        }
    }

    /** @return array{ciphertext:string,key_id:string} */
    public function encrypt(string $secret): array
    {
        if ($secret === '') {
            throw new \InvalidArgumentException('MFA secret cannot be empty.');
        }

        $iv = random_bytes(self::IV_BYTES);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $secret,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_BYTES
        );

        if ($ciphertext === false || strlen($tag) !== self::TAG_BYTES) {
            throw new \RuntimeException('Unable to encrypt MFA secret.');
        }

        return [
            'ciphertext' => base64_encode($iv . $tag . $ciphertext),
            'key_id' => $this->keyId,
        ];
    }

    public function decrypt(string $ciphertext, string $keyId): string
    {
        if (!hash_equals($this->keyId, $keyId)) {
            throw new \RuntimeException('MFA encryption key is unavailable.');
        }

        $payload = base64_decode($ciphertext, true);
        if ($payload === false || strlen($payload) <= self::IV_BYTES + self::TAG_BYTES) {
            throw new \RuntimeException('Invalid MFA ciphertext.');
        }

        $iv = substr($payload, 0, self::IV_BYTES);
        $tag = substr($payload, self::IV_BYTES, self::TAG_BYTES);
        $encrypted = substr($payload, self::IV_BYTES + self::TAG_BYTES);
        $secret = openssl_decrypt(
            $encrypted,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($secret === false || $secret === '') {
            throw new \RuntimeException('Unable to decrypt MFA secret.');
        }

        return $secret;
    }
}
