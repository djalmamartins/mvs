<?php

declare(strict_types=1);

use Moves\Modules\Erp\Security\MfaEnrollmentRepository;
use Moves\Modules\Erp\Security\MfaSecretCipher;
use Moves\Modules\Erp\Security\SecurityAuditRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class ErpMfaEnrollmentRepositoryTest extends TestCase
{
    private PDO $pdo;
    private MfaEnrollmentRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE erp_mfa_enrollments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                method TEXT NOT NULL,
                secret_ciphertext TEXT NOT NULL,
                secret_key_id TEXT NOT NULL,
                enabled_at TEXT NULL,
                disabled_at TEXT NULL,
                UNIQUE (user_id, method)
            )'
        );
        $this->pdo->exec(
            'CREATE TABLE erp_security_audit (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_type TEXT NOT NULL,
                actor_user_id INTEGER NULL,
                subject_user_id INTEGER NULL,
                metadata_json TEXT NULL,
                created_at TEXT NOT NULL
            )'
        );
        $this->repository = new MfaEnrollmentRepository(
            $this->pdo,
            new MfaSecretCipher('mfa-key-v1', str_repeat('k', 32)),
            new SecurityAuditRepository($this->pdo)
        );
    }

    public function testPersistsOnlyEncryptedSecretAndReadsActiveEnrollment(): void
    {
        $secret = 'GEZDGNBVGY3TQOJQ';

        $this->repository->enrollTotp(7, $secret);

        $row = $this->pdo->query('SELECT * FROM erp_mfa_enrollments')->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertSame('mfa-key-v1', $row['secret_key_id']);
        self::assertNotSame($secret, $row['secret_ciphertext']);
        self::assertStringNotContainsString($secret, $row['secret_ciphertext']);
        self::assertSame($secret, $this->repository->activeTotpSecret(7));

        $audit = $this->pdo->query('SELECT * FROM erp_security_audit')->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($audit);
        self::assertSame('mfa.totp.enrolled', $audit['event_type']);
        self::assertSame(7, (int) $audit['actor_user_id']);
        self::assertSame(7, (int) $audit['subject_user_id']);
        self::assertSame('{"method":"totp"}', $audit['metadata_json']);
        self::assertStringNotContainsString($secret, (string) $audit['metadata_json']);
    }

    public function testDisabledEnrollmentCannotBeReadAsActive(): void
    {
        $this->repository->enrollTotp(7, 'GEZDGNBVGY3TQOJQ');

        self::assertTrue($this->repository->disableTotp(7, 9));
        self::assertNull($this->repository->activeTotpSecret(7));
        self::assertFalse($this->repository->disableTotp(7, 9));

        $audit = $this->pdo->query("SELECT * FROM erp_security_audit WHERE event_type = 'mfa.totp.disabled'")->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($audit);
        self::assertSame(9, (int) $audit['actor_user_id']);
        self::assertSame(7, (int) $audit['subject_user_id']);
        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM erp_security_audit WHERE event_type = 'mfa.totp.disabled'")->fetchColumn());
    }

    public function testInvalidOrMissingUserFailsClosed(): void
    {
        self::assertNull($this->repository->activeTotpSecret(0));
        self::assertNull($this->repository->activeTotpSecret(404));
        self::assertFalse($this->repository->disableTotp(-1));

        $this->expectException(InvalidArgumentException::class);
        $this->repository->enrollTotp(0, 'GEZDGNBVGY3TQOJQ');
    }

    public function testReenrollmentReactivatesEnrollmentAndReplacesSecret(): void
    {
        $this->repository->enrollTotp(7, 'GEZDGNBVGY3TQOJQ');
        self::assertTrue($this->repository->disableTotp(7));

        $this->repository->enrollTotp(7, 'JBSWY3DPEHPK3PXP', 9);

        self::assertSame('JBSWY3DPEHPK3PXP', $this->repository->activeTotpSecret(7));
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM erp_mfa_enrollments')->fetchColumn());
        $disabledAt = $this->pdo->query("SELECT disabled_at FROM erp_mfa_enrollments WHERE user_id = 7 AND method = 'totp'")->fetchColumn();
        self::assertNull($disabledAt);

        $audit = $this->pdo->query("SELECT * FROM erp_security_audit WHERE event_type = 'mfa.totp.reenrolled'")->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($audit);
        self::assertSame(9, (int) $audit['actor_user_id']);
        self::assertSame(7, (int) $audit['subject_user_id']);
        self::assertStringNotContainsString('JBSWY3DPEHPK3PXP', (string) $audit['metadata_json']);
    }
}
