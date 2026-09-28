<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Security;

final class SensitiveDataCipher
{
    private const CIPHER = 'aes-256-gcm';
    private const IV_BYTES = 12;
    private const TAG_BYTES = 16;

    public function __construct(private readonly string $keyId, private readonly string $key)
    {
        if ($keyId === '' || strlen($key) !== 32) {
            throw new \InvalidArgumentException('Invalid sensitive data encryption key configuration.');
        }
    }

    /** @return array{ciphertext:string,key_id:string} */
    public function encrypt(string $plaintext): array
    {
        if ($plaintext === '') {
            throw new \InvalidArgumentException('Sensitive data cannot be empty.');
        }
        $iv = random_bytes(self::IV_BYTES);
        $tag = '';
        $encrypted = openssl_encrypt($plaintext, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_BYTES);
        if ($encrypted === false || strlen($tag) !== self::TAG_BYTES) {
            throw new \RuntimeException('Unable to encrypt sensitive data.');
        }
        return ['ciphertext' => base64_encode($iv . $tag . $encrypted), 'key_id' => $this->keyId];
    }

    public function decrypt(string $ciphertext, string $keyId): string
    {
        if (!hash_equals($this->keyId, $keyId)) {
            throw new \RuntimeException('Sensitive data encryption key is unavailable.');
        }
        $payload = base64_decode($ciphertext, true);
        if ($payload === false || strlen($payload) <= self::IV_BYTES + self::TAG_BYTES) {
            throw new \RuntimeException('Invalid sensitive data ciphertext.');
        }
        $iv = substr($payload, 0, self::IV_BYTES);
        $tag = substr($payload, self::IV_BYTES, self::TAG_BYTES);
        $encrypted = substr($payload, self::IV_BYTES + self::TAG_BYTES);
        $plaintext = openssl_decrypt($encrypted, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($plaintext === false || $plaintext === '') {
            throw new \RuntimeException('Unable to decrypt sensitive data.');
        }
        return $plaintext;
    }
}