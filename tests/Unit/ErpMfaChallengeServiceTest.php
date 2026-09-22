<?php

declare(strict_types=1);

use Moves\Modules\Erp\Security\MfaChallengeService;
use Moves\Modules\Erp\Security\MfaEnrollmentRepository;
use Moves\Modules\Erp\Security\MfaSecretCipher;
use Moves\Modules\Erp\Security\TotpVerifier;
use PDO;
use PHPUnit\Framework\TestCase;

final class ErpMfaChallengeServiceTest extends TestCase
{
    private PDO $pdo;
    private MfaEnrollmentRepository $enrollments;
    private MfaChallengeService $service;

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

        $this->enrollments = new MfaEnrollmentRepository(
            $this->pdo,
            new MfaSecretCipher('mfa-key-v1', str_repeat('k', 32))
        );
        $this->service = new MfaChallengeService($this->enrollments, new TotpVerifier(window: 0));
    }

    public function testValidCodeForActiveEnrollmentIsAccepted(): void
    {
        $this->enrollments->enrollTotp(7, 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ');

        self::assertTrue($this->service->verifyTotp(7, '287082', 59));
    }

    public function testMissingOrDisabledEnrollmentFailsClosed(): void
    {
        self::assertFalse($this->service->verifyTotp(7, '287082', 59));

        $this->enrollments->enrollTotp(7, 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ');
        $this->enrollments->disableTotp(7);

        self::assertFalse($this->service->verifyTotp(7, '287082', 59));
    }

    public function testInvalidIdentityOrCodeFailsClosed(): void
    {
        self::assertFalse($this->service->verifyTotp(0, '287082', 59));
        self::assertFalse($this->service->verifyTotp(7, '12345', 59));
        self::assertFalse($this->service->verifyTotp(7, 'abcdef', 59));
    }

    public function testWrongCodeIsRejected(): void
    {
        $this->enrollments->enrollTotp(7, 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ');

        self::assertFalse($this->service->verifyTotp(7, '000000', 59));
    }
}
