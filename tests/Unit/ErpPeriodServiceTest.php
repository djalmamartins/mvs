<?php

declare(strict_types=1);

use Moves\Modules\Erp\Periods\PeriodAlreadyExists;
use Moves\Modules\Erp\Periods\PeriodRepository;
use Moves\Modules\Erp\Periods\PeriodService;
use Moves\Services\Platform\PlatformAudit;
use PHPUnit\Framework\TestCase;

final class ErpPeriodServiceTest extends TestCase
{
    private PDO $pdo;
    private PeriodRepository $repository;
    private PeriodService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('PRAGMA foreign_keys=ON');
        $this->pdo->exec('CREATE TABLE users(id INTEGER PRIMARY KEY,name TEXT NOT NULL)');
        $this->pdo->exec("INSERT INTO users VALUES(1,'Owner A'),(2,'Owner B')");
        $this->pdo->exec('CREATE TABLE erp_administrators(id INTEGER PRIMARY KEY,tenant_id INTEGER NOT NULL)');
        $this->pdo->exec('INSERT INTO erp_administrators VALUES(10,100),(20,200)');
        $this->pdo->exec('CREATE TABLE erp_condominiums(id INTEGER PRIMARY KEY,administrator_id INTEGER NOT NULL,legal_name TEXT NOT NULL,trade_name TEXT,status TEXT,UNIQUE(administrator_id,id),FOREIGN KEY(administrator_id) REFERENCES erp_administrators(id))');
        $this->pdo->exec("INSERT INTO erp_condominiums VALUES(101,10,'Condomínio A1','A1','active'),(102,10,'Condomínio A2','A2','active'),(201,20,'Condomínio B','B','active')");
        $this->pdo->exec("CREATE TABLE erp_accounting_periods(id INTEGER PRIMARY KEY AUTOINCREMENT,administrator_id INTEGER NOT NULL,condominium_id INTEGER NOT NULL,period_year INTEGER NOT NULL,period_month INTEGER NOT NULL,status TEXT NOT NULL DEFAULT 'open',created_by_user_id INTEGER NOT NULL,updated_by_user_id INTEGER NOT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(administrator_id,id),UNIQUE(administrator_id,condominium_id,period_year,period_month),CHECK(period_year BETWEEN 2000 AND 2100),CHECK(period_month BETWEEN 1 AND 12),CHECK(status='open'),FOREIGN KEY(administrator_id) REFERENCES erp_administrators(id),FOREIGN KEY(administrator_id,condominium_id) REFERENCES erp_condominiums(administrator_id,id),FOREIGN KEY(created_by_user_id) REFERENCES users(id),FOREIGN KEY(updated_by_user_id) REFERENCES users(id))");
        $this->pdo->exec('CREATE TABLE platform_audit_events(id INTEGER PRIMARY KEY AUTOINCREMENT,tenant_id INTEGER,actor_user_id INTEGER,event_type TEXT,subject_type TEXT,subject_id INTEGER,metadata TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->repository = new PeriodRepository($this->pdo);
        $this->service = new PeriodService($this->pdo, $this->repository, new PlatformAudit($this->pdo));
    }

    public function testCreatesOpenMonthlyPeriodAndAuditAtomically(): void
    {
        $id = $this->service->create(100, 10, 1, ['condominium_id'=>'101','month'=>'10','year'=>'2026','status'=>'closed']);
        $period = $this->repository->find(10, $id);
        self::assertNotNull($period);
        self::assertSame(10, (int) $period['period_month']);
        self::assertSame(2026, (int) $period['period_year']);
        self::assertSame('open', $period['status']);
        self::assertSame('Owner A', $period['created_by_name']);
        $audit = $this->pdo->query('SELECT tenant_id,actor_user_id,event_type,subject_type,subject_id,metadata FROM platform_audit_events')->fetch(PDO::FETCH_ASSOC);
        self::assertSame(100, (int) $audit['tenant_id']);
        self::assertSame(1, (int) $audit['actor_user_id']);
        self::assertSame('erp.accounting_period.created', $audit['event_type']);
        self::assertSame('accounting_period', $audit['subject_type']);
        self::assertSame($id, (int) $audit['subject_id']);
        self::assertStringContainsString('"month":10', (string) $audit['metadata']);
    }

    public function testUniqueConstraintReturnsExpectedDomainError(): void
    {
        $input = ['condominium_id'=>101,'month'=>10,'year'=>2026];
        $this->service->create(100, 10, 1, $input);
        try {
            $this->service->create(100, 10, 1, $input);
            self::fail('Duplicate month should be rejected.');
        } catch (PeriodAlreadyExists $exception) {
            self::assertStringContainsString('Já existe uma competência', $exception->getMessage());
        }
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM erp_accounting_periods')->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM platform_audit_events')->fetchColumn());
    }

    public function testSameMonthAcrossCondominiumsAndYearsIsAllowedAndFiltersAreScoped(): void
    {
        $a1 = $this->service->create(100, 10, 1, ['condominium_id'=>101,'month'=>3,'year'=>2026]);
        $this->service->create(100, 10, 1, ['condominium_id'=>102,'month'=>3,'year'=>2026]);
        $this->service->create(100, 10, 1, ['condominium_id'=>101,'month'=>3,'year'=>2025]);
        $this->service->create(200, 20, 2, ['condominium_id'=>201,'month'=>3,'year'=>2026]);
        self::assertCount(3, $this->repository->search(10, ['condominium_id'=>null,'year'=>null,'status'=>'']));
        self::assertCount(2, $this->repository->search(10, ['condominium_id'=>101,'year'=>null,'status'=>'open']));
        self::assertCount(1, $this->repository->search(10, ['condominium_id'=>null,'year'=>2025,'status'=>'open']));
        self::assertSame('A1', $this->repository->find(10, $a1)['trade_name']);
        self::assertNull($this->repository->find(20, $a1));
    }

    public function testRejectsInvalidMonthYearCondominiumAndCrossTenantCondominium(): void
    {
        foreach ([
            [['condominium_id'=>101,'month'=>0,'year'=>2026], 'mês'],
            [['condominium_id'=>101,'month'=>13,'year'=>2026], 'mês'],
            [['condominium_id'=>101,'month'=>1,'year'=>1999], 'ano'],
            [['condominium_id'=>101,'month'=>1,'year'=>2101], 'ano'],
            [['condominium_id'=>'','month'=>1,'year'=>2026], 'condomínio'],
            [['condominium_id'=>999,'month'=>1,'year'=>2026], 'condomínio'],
            [['condominium_id'=>201,'month'=>1,'year'=>2026], 'não pertence'],
        ] as [$input, $message]) {
            try {
                $this->service->create(100, 10, 1, $input);
                self::fail('Expected input to be rejected.');
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString($message, $exception->getMessage());
            }
        }
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM erp_accounting_periods')->fetchColumn());
    }

    public function testAuditFailureRollsBackPeriod(): void
    {
        $this->pdo->exec("CREATE TRIGGER fail_audit BEFORE INSERT ON platform_audit_events BEGIN SELECT RAISE(ABORT,'audit unavailable'); END");
        try {
            $this->service->create(100, 10, 1, ['condominium_id'=>101,'month'=>10,'year'=>2026]);
            self::fail('Audit failure should abort creation.');
        } catch (PDOException $exception) {
            self::assertStringContainsString('audit unavailable', $exception->getMessage());
        }
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM erp_accounting_periods')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM platform_audit_events')->fetchColumn());
    }
}
