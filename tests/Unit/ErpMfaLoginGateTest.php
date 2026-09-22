<?php

declare(strict_types=1);

use Moves\Modules\Erp\Security\MfaChallengeService;
use Moves\Modules\Erp\Security\MfaEnrollmentRepository;
use Moves\Modules\Erp\Security\MfaLoginGate;
use Moves\Modules\Erp\Security\MfaRequirementPolicy;
use Moves\Modules\Erp\Security\MfaSecretCipher;
use Moves\Modules\Erp\Security\TotpVerifier;
use PDO;
use PHPUnit\Framework\TestCase;

final class ErpMfaLoginGateTest extends TestCase
{
    private MfaEnrollmentRepository $enrollments;
    private MfaLoginGate $gate;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
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
            $pdo,
            new MfaSecretCipher('mfa-key-v1', str_repeat('k', 32))
        );
        $challenge = new MfaChallengeService($this->enrollments, new TotpVerifier(window: 0));
        $this->gate = new MfaLoginGate(new MfaRequirementPolicy(), $challenge);
    }

    public function testCommonRoleDoesNotRequireMfa(): void
    {
        self::assertTrue($this->gate->canEstablishSession(7, 'user', null, 59));
    }

    public function testSensitiveRoleFailsClosedWithoutChallenge(): void
    {
        self::assertFalse($this->gate->canEstablishSession(7, 'admin', null, 59));
        self::assertFalse($this->gate->canEstablishSession(7, 'finance', '', 59));
        self::assertFalse($this->gate->canEstablishSession(0, 'manager', '287082', 59));
    }

    public function testSensitiveRoleWithoutEnrollmentIsRejected(): void
    {
        self::assertFalse($this->gate->canEstablishSession(7, 'admin', '287082', 59));
    }

    public function testSensitiveRoleRequiresValidTotpBeforeSessionGrant(): void
    {
        $this->enrollments->enrollTotp(7, 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ');

        self::assertFalse($this->gate->canEstablishSession(7, 'admin', '000000', 59));
        self::assertTrue($this->gate->canEstablishSession(7, 'admin', '287082', 59));
    }

    public function testDisabledEnrollmentFailsClosed(): void
    {
        $this->enrollments->enrollTotp(7, 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ');
        $this->enrollments->disableTotp(7);

        self::assertFalse($this->gate->canEstablishSession(7, 'supervisor', '287082', 59));
    }
}
