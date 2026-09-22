<?php

declare(strict_types=1);

use Moves\Modules\Erp\Security\MfaRuntimeConfig;
use PHPUnit\Framework\TestCase;

final class ErpMfaRuntimeConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_ENV['ERP_MFA_KEY_ID'], $_ENV['ERP_MFA_KEY']);
        putenv('ERP_MFA_KEY_ID');
        putenv('ERP_MFA_KEY');
    }

    public function testLoadsVersionedKeyFromEnvironment(): void
    {
        $_ENV['ERP_MFA_KEY_ID'] = 'mfa-key-v1';
        $_ENV['ERP_MFA_KEY'] = base64_encode(str_repeat('k', 32));

        $config = MfaRuntimeConfig::fromEnvironment();

        self::assertSame('mfa-key-v1', $config->keyId);
        self::assertSame(str_repeat('k', 32), $config->key);

        $encrypted = $config->cipher()->encrypt('totp-secret');
        self::assertSame('mfa-key-v1', $encrypted['key_id']);
        self::assertSame('totp-secret', $config->cipher()->decrypt($encrypted['ciphertext'], $encrypted['key_id']));
    }

    public function testMissingConfigurationFailsClosed(): void
    {
        $this->expectException(RuntimeException::class);
        MfaRuntimeConfig::fromEnvironment();
    }

    public function testInvalidBase64KeyFailsClosed(): void
    {
        $_ENV['ERP_MFA_KEY_ID'] = 'mfa-key-v1';
        $_ENV['ERP_MFA_KEY'] = 'not-base64!';

        $this->expectException(RuntimeException::class);
        MfaRuntimeConfig::fromEnvironment();
    }

    public function testWrongKeyLengthFailsClosed(): void
    {
        $_ENV['ERP_MFA_KEY_ID'] = 'mfa-key-v1';
        $_ENV['ERP_MFA_KEY'] = base64_encode('too-short');

        $this->expectException(RuntimeException::class);
        MfaRuntimeConfig::fromEnvironment();
    }
}
