<?php

declare(strict_types=1);

use Moves\Modules\Erp\Payables\PayableRepository;
use Moves\Modules\Erp\Payables\PayableService;
use Moves\Services\Platform\PlatformAudit;
use PHPUnit\Framework\TestCase;

final class ErpPayableServiceTest extends TestCase
{
    private PDO $pdo;
    private PayableService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec("CREATE TABLE users(id INTEGER PRIMARY KEY,name TEXT NOT NULL); INSERT INTO users VALUES(1,'Owner A'),(2,'Owner B');");
        $this->pdo->exec("CREATE TABLE erp_condominiums(id INTEGER PRIMARY KEY,administrator_id INTEGER,legal_name TEXT,trade_name TEXT,status TEXT); INSERT INTO erp_condominiums VALUES(101,10,'Condomínio A','A','active'),(102,10,'Condomínio A2','A2','active'),(201,20,'Condomínio B','B','active');");
        $this->pdo->exec("CREATE TABLE erp_people(id INTEGER PRIMARY KEY,administrator_id INTEGER,full_name TEXT,trade_name TEXT,status TEXT); INSERT INTO erp_people VALUES(1,10,'Fornecedor A',NULL,'active'),(2,10,'Fornecedor A2',NULL,'active'),(3,20,'Fornecedor B',NULL,'active');");
        $this->pdo->exec("CREATE TABLE erp_suppliers(id INTEGER PRIMARY KEY,administrator_id INTEGER,person_id INTEGER,status TEXT); INSERT INTO erp_suppliers VALUES(301,10,1,'active'),(302,10,2,'active'),(401,20,3,'active');");
        $this->pdo->exec('CREATE TABLE erp_supplier_condominiums(id INTEGER PRIMARY KEY,administrator_id INTEGER,supplier_id INTEGER,condominium_id INTEGER,starts_at TEXT,ends_at TEXT,status TEXT);');
        $link = $this->pdo->prepare("INSERT INTO erp_supplier_condominiums VALUES(?,?,?,?,?,NULL,'active')");
        foreach ([[1,10,301,101],[2,10,302,102],[3,20,401,201]] as $row) $link->execute([...$row,date('Y-m-d')]);
        $this->pdo->exec("CREATE TABLE erp_accounting_plans(id INTEGER PRIMARY KEY,administrator_id INTEGER,condominium_id INTEGER,status TEXT,name TEXT); INSERT INTO erp_accounting_plans VALUES(501,10,101,'active','Plano A'),(502,10,102,'active','Plano A2'),(601,20,201,'active','Plano B');");
        $this->pdo->exec("CREATE TABLE erp_accounting_accounts(id INTEGER PRIMARY KEY,administrator_id INTEGER,plan_id INTEGER,code TEXT,name TEXT,nature TEXT,account_type TEXT,status TEXT); INSERT INTO erp_accounting_accounts VALUES(7001,10,501,'2.1','Obrigações','liability','analytic','active'),(7002,10,501,'4.1','Manutenção','expense','analytic','active'),(7003,10,502,'2.1','Obrigações A2','liability','analytic','active'),(7004,10,502,'4.1','Despesa A2','expense','analytic','active'),(8001,20,601,'2.1','Obrigações B','liability','analytic','active'),(8002,20,601,'4.1','Despesa B','expense','analytic','active');");
        $this->pdo->exec("CREATE TABLE erp_accounting_periods(id INTEGER PRIMARY KEY,administrator_id INTEGER,condominium_id INTEGER,period_year INTEGER,period_month INTEGER,status TEXT); INSERT INTO erp_accounting_periods VALUES(9001,10,101,2026,10,'open'),(9002,10,102,2026,10,'open'),(9901,10,101,2026,9,'closed'),(9101,20,201,2026,10,'open');");
        $this->pdo->exec('CREATE TABLE platform_audit_events(id INTEGER PRIMARY KEY AUTOINCREMENT,tenant_id INTEGER,actor_user_id INTEGER,event_type TEXT,subject_type TEXT,subject_id INTEGER,metadata TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->pdo->exec('CREATE TABLE erp_payables(id INTEGER PRIMARY KEY AUTOINCREMENT,administrator_id INTEGER,condominium_id INTEGER,supplier_id INTEGER,plan_id INTEGER,liability_account_id INTEGER,expense_account_id INTEGER,description TEXT,total_amount NUMERIC,created_by_user_id INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->pdo->exec('CREATE TABLE erp_payable_installments(id INTEGER PRIMARY KEY AUTOINCREMENT,administrator_id INTEGER,condominium_id INTEGER,payable_id INTEGER,installment_number INTEGER,accounting_period_id INTEGER,due_date TEXT,amount NUMERIC,created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->service = new PayableService($this->pdo, new PayableRepository($this->pdo), new PlatformAudit($this->pdo));
    }

    public function testCreatesListsAndReadsTenantScopedObligationAndAudit(): void
    {
        $id = $this->service->create(100, 10, 1, $this->validInput());
        $detail = $this->service->detail(10, $id);
        self::assertNotNull($detail);
        self::assertSame('Fornecedor A', $detail['supplier_name']);
        self::assertSame('Obrigações', $detail['liability_account_name']);
        self::assertSame('Manutenção', $detail['expense_account_name']);
        self::assertCount(2, $detail['installments']);
        self::assertSame('50.25', number_format((float)$detail['installments'][0]['amount'], 2, '.', ''));
        self::assertCount(1, $this->service->search(10, ['query'=>'Manutenção']));
        self::assertSame([], $this->service->search(20, ['query'=>'Manutenção']));
        self::assertNull($this->service->detail(20, $id));
        $event = $this->pdo->query('SELECT * FROM platform_audit_events')->fetch(PDO::FETCH_ASSOC);
        self::assertSame('erp.payable.created', $event['event_type']);
        self::assertSame('payable', $event['subject_type']);
        self::assertSame(100, (int)$event['tenant_id']);
        self::assertStringContainsString('"installment_count":2', (string)$event['metadata']);
    }

    public function testRejectsMismatchedTotalsAndInvalidTenantReferencesWithoutWrites(): void
    {
        $input = $this->validInput();
        $invalid = [];
        $row = $input; $row['total_amount'] = '100.51'; $invalid[] = [$row, 'soma das parcelas'];
        $row = $input; $row['installments']['period_id'][1] = 9901; $invalid[] = [$row, 'competência deve estar aberta'];
        $row = $input; $row['expense_account_id'] = 7004; $invalid[] = [$row, 'plano do condomínio'];
        $row = $input; $row['supplier_id'] = 401; $invalid[] = [$row, 'vínculo ativo'];
        $row = $input; $row['installments']['amount'][0] = '1.005'; $invalid[] = [$row, 'valores monetários'];
        foreach ($invalid as [$data, $message]) {
            try { $this->service->create(100, 10, 1, $data); self::fail('Dados inválidos não podem criar obrigação.'); }
            catch (InvalidArgumentException $exception) { self::assertStringContainsString($message, $exception->getMessage()); }
        }
        self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM erp_payables')->fetchColumn());
        self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM platform_audit_events')->fetchColumn());
    }

    public function testAuditFailureRollsBackTitleAndInstallments(): void
    {
        $this->pdo->exec("CREATE TRIGGER fail_audit BEFORE INSERT ON platform_audit_events BEGIN SELECT RAISE(ABORT,'audit unavailable'); END");
        try { $this->service->create(100, 10, 1, $this->validInput()); self::fail('Falha de auditoria deve abortar toda a gravação.'); }
        catch (PDOException $exception) { self::assertStringContainsString('audit unavailable', $exception->getMessage()); }
        self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM erp_payables')->fetchColumn());
        self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM erp_payable_installments')->fetchColumn());
    }

    /** @return array<string,mixed> */
    private function validInput(): array
    {
        return ['supplier_id'=>301,'condominium_id'=>101,'liability_account_id'=>7001,'expense_account_id'=>7002,'description'=>'Manutenção de elevadores','total_amount'=>'100.50','installments'=>['period_id'=>[9001,9001],'due_date'=>['2026-10-10','2026-11-10'],'amount'=>['50.25','50.25']]];
    }
}
