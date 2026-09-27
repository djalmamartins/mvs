<?php

declare(strict_types=1);

use Moves\Modules\Erp\Security\MfaSecretCipher;
use PHPUnit\Framework\TestCase;

final class ErpMfaSecretCipherTest extends TestCase
{
    public function testEncryptsAndDecryptsWithoutPersistingPlaintext(): void
    {
        $cipher = new MfaSecretCipher('mfa-key-v1', str_repeat('k', 32));
        $secret = 'GEZDGNBVGY3TQOJQ';

        $encrypted = $cipher->encrypt($secret);

        self::assertSame('mfa-key-v1', $encrypted['key_id']);
        self::assertNotSame($secret, $encrypted['ciphertext']);
        self::assertStringNotContainsString($secret, $encrypted['ciphertext']);
        self::assertSame($secret, $cipher->decrypt($encrypted['ciphertext'], $encrypted['key_id']));
    }

    public function testUsesRandomNonceForEveryEncryption(): void
    {
        $cipher = new MfaSecretCipher('mfa-key-v1', str_repeat('k', 32));

        $first = $cipher->encrypt('GEZDGNBVGY3TQOJQ');
        $second = $cipher->encrypt('GEZDGNBVGY3TQOJQ');

        self::assertNotSame($first['ciphertext'], $second['ciphertext']);
    }

    public function testFailsClosedForWrongKeyId(): void
    {
        $cipher = new MfaSecretCipher('mfa-key-v2', str_repeat('k', 32));
        $encrypted = $cipher->encrypt('GEZDGNBVGY3TQOJQ');

        $this->expectException(RuntimeException::class);
        $cipher->decrypt($encrypted['ciphertext'], 'mfa-key-v1');
    }

    public function testRejectsTamperedCiphertext(): void
    {
        $cipher = new MfaSecretCipher('mfa-key-v1', str_repeat('k', 32));
        $encrypted = $cipher->encrypt('GEZDGNBVGY3TQOJQ');
        $payload = base64_decode($encrypted['ciphertext'], true);
        self::assertIsString($payload);
        $payload[strlen($payload) - 1] = chr(ord($payload[strlen($payload) - 1]) ^ 1);

        $this->expectException(RuntimeException::class);
        $cipher->decrypt(base64_encode($payload), $encrypted['key_id']);
    }

    public function testRejectsInvalidConfigurationAndEmptySecret(): void
    {
        try {
            new MfaSecretCipher('', str_repeat('k', 32));
            self::fail('Empty key id must be rejected.');
        } catch (InvalidArgumentException) {
            self::assertTrue(true);
        }

        $cipher = new MfaSecretCipher('mfa-key-v1', str_repeat('k', 32));
        $this->expectException(InvalidArgumentException::class);
        $cipher->encrypt('');
    }
}
