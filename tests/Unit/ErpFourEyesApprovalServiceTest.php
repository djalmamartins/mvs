<?php

declare(strict_types=1);

use Moves\Modules\Erp\Security\FourEyesApprovalService;
use Moves\Modules\Erp\Security\FourEyesPolicy;
use Moves\Modules\Erp\Security\SecurityAuditRepository;
use PHPUnit\Framework\TestCase;

final class ErpFourEyesApprovalServiceTest extends TestCase
{
    private PDO $pdo;
    private FourEyesApprovalService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE erp_security_audit (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_type TEXT NOT NULL,
                actor_user_id INTEGER NULL,
                subject_user_id INTEGER NULL,
                metadata_json TEXT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );

        $this->service = new FourEyesApprovalService(
            new FourEyesPolicy(),
            new SecurityAuditRepository($this->pdo)
        );
    }

    public function testDistinctApproverIsRecordedWithInitiatorAndOperation(): void
    {
        $this->service->approve('payment.release', 10, 20, 30);

        $row = $this->pdo->query('SELECT * FROM erp_security_audit')->fetch(PDO::FETCH_ASSOC);
        self::assertSame('security.four_eyes.approved', $row['event_type']);
        self::assertSame(20, (int) $row['actor_user_id']);
        self::assertSame(30, (int) $row['subject_user_id']);
        self::assertSame(
            ['operation' => 'payment.release', 'initiator_user_id' => 10],
            json_decode((string) $row['metadata_json'], true, 512, JSON_THROW_ON_ERROR)
        );
    }

    public function testSelfApprovalIsDeniedWithoutAuditEvent(): void
    {
        try {
            $this->service->approve('period.close', 10, 10);
            self::fail('Self-approval must be denied.');
        } catch (DomainException) {
            self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM erp_security_audit')->fetchColumn());
        }
    }

    public function testOperationIsRequired(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->approve('   ', 10, 20);
    }
}
