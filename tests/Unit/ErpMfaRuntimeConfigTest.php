<?php

declare(strict_types=1);

use Moves\Modules\Erp\Security\MfaRuntimeConfig;
use PHPUnit\Framework\TestCase;

final class ErpMfaRuntimeConfigTest extends TestCase
{
    /** @var array<string, string|false> */
    private array $originalProcessEnvironment = [];

    /** @var array<string, string> */
    private array $originalEnvironment = [];

    protected function setUp(): void
    {
        foreach (['MFA_KEY_ID', 'MFA_KEY', 'ERP_MFA_KEY_ID', 'ERP_MFA_KEY'] as $key) {
            $this->originalProcessEnvironment[$key] = getenv($key);
            if (isset($_ENV[$key])) {
                $this->originalEnvironment[$key] = (string) $_ENV[$key];
            }
            unset($_ENV[$key]);
            putenv($key);
        }
    }

    protected function tearDown(): void
    {
        foreach (['MFA_KEY_ID', 'MFA_KEY', 'ERP_MFA_KEY_ID', 'ERP_MFA_KEY'] as $key) {
            if (array_key_exists($key, $this->originalEnvironment)) {
                $_ENV[$key] = $this->originalEnvironment[$key];
            } else {
                unset($_ENV[$key]);
            }

            $value = $this->originalProcessEnvironment[$key] ?? false;
            putenv($value === false ? $key : $key . '=' . $value);
        }
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

    public function testPrefersTransversalConfigurationAndKeepsLegacyFallback(): void
    {
        $_ENV['MFA_KEY_ID'] = 'moves-key-v2';
        $_ENV['MFA_KEY'] = base64_encode(str_repeat('m', 32));
        $_ENV['ERP_MFA_KEY_ID'] = 'erp-key-v1';
        $_ENV['ERP_MFA_KEY'] = base64_encode(str_repeat('e', 32));

        $config = MfaRuntimeConfig::fromEnvironment();

        self::assertSame('moves-key-v2', $config->keyId);
        self::assertSame(str_repeat('m', 32), $config->key);
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
