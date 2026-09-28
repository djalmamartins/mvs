<?php

declare(strict_types=1);

use Moves\Modules\Erp\Security\MfaEnrollmentRepository;
use Moves\Modules\Erp\Security\MfaSecretCipher;
use Moves\Modules\Erp\Security\TotpVerifier;
use Moves\Services\Auth\MfaSetupService;
use PHPUnit\Framework\TestCase;

final class MfaSetupServiceTest extends TestCase
{
    private PDO $pdo;
    private MfaEnrollmentRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE erp_mfa_enrollments (id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER NOT NULL,method TEXT NOT NULL,secret_ciphertext TEXT NOT NULL,secret_key_id TEXT NOT NULL,enabled_at TEXT NULL,disabled_at TEXT NULL,UNIQUE(user_id,method))');
        $this->pdo->exec('CREATE TABLE platform_mfa_recovery_codes (id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER NOT NULL,code_hash TEXT NOT NULL,used_at TEXT NULL,created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->repository = new MfaEnrollmentRepository(
            $this->pdo,
            new MfaSecretCipher('moves-mfa-v1', str_repeat('k', 32))
        );
    }

    public function testStagesEncryptedSecretAndProducesLocalQrPayload(): void
    {
        $service = $this->service();

        $setup = $service->begin(7, 'ANA@example.com');

        self::assertSame('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', $setup['secret']);
        self::assertStringStartsWith('otpauth://totp/Moves%3Aana%40example.com', $setup['uri']);
        self::assertStringStartsWith('data:image/svg+xml;base64,', $setup['qr']);
        self::assertFalse($service->enabled(7));
        $stored = (string) $this->pdo->query('SELECT secret_ciphertext FROM erp_mfa_enrollments')->fetchColumn();
        self::assertStringNotContainsString($setup['secret'], $stored);
    }

    public function testConfirmationEnablesTotpAndStoresOnlyHashedRecoveryCodes(): void
    {
        $service = $this->service();
        $service->begin(7, 'ana@example.com');

        self::assertNull($service->confirm(7, '000000', 59));
        $codes = $service->confirm(7, '287082', 59);

        self::assertIsArray($codes);
        self::assertCount(8, $codes);
        self::assertTrue($service->enabled(7));
        $hashes = $this->pdo->query('SELECT code_hash FROM platform_mfa_recovery_codes')->fetchAll(PDO::FETCH_COLUMN);
        self::assertCount(8, $hashes);
        self::assertTrue(password_verify(str_replace('-', '', $codes[0]), (string) $hashes[0]));
        self::assertStringNotContainsString($codes[0], implode('', $hashes));
    }

    private function service(): MfaSetupService
    {
        return new MfaSetupService(
            $this->pdo,
            $this->repository,
            new TotpVerifier(window: 0),
            static fn (): string => 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'
        );
    }
}
