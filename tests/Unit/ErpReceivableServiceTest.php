<?php

declare(strict_types=1);

use Moves\Modules\Erp\Receivables\ReceivableRepository;
use Moves\Modules\Erp\Receivables\ReceivableService;
use Moves\Services\Platform\PlatformAudit;
use PHPUnit\Framework\TestCase;

final class ErpReceivableServiceTest extends TestCase
{
    private PDO $pdo;
    private ReceivableRepository $repository;
    private ReceivableService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE users(id INTEGER PRIMARY KEY,name TEXT NOT NULL)');
        $this->pdo->exec("INSERT INTO users VALUES(1,'Owner A'),(2,'Owner B')");
        $this->pdo->exec('CREATE TABLE erp_condominiums(id INTEGER PRIMARY KEY,administrator_id INTEGER,legal_name TEXT,trade_name TEXT,status TEXT)');
        $this->pdo->exec("INSERT INTO erp_condominiums VALUES(101,10,'Residencial A','A','active'),(102,10,'Residencial A2','A2','active'),(201,20,'Residencial B','B','active')");
        $this->pdo->exec('CREATE TABLE erp_blocks(id INTEGER PRIMARY KEY,condominium_id INTEGER,name TEXT)');
        $this->pdo->exec("INSERT INTO erp_blocks VALUES(1,101,'Torre A')");
        $this->pdo->exec('CREATE TABLE erp_units(id INTEGER PRIMARY KEY,condominium_id INTEGER,block_id INTEGER,code TEXT,status TEXT)');
        $this->pdo->exec("INSERT INTO erp_units VALUES(1001,101,1,'12','active'),(1002,102,NULL,'4','active'),(2001,201,NULL,'3','active')");
        $this->pdo->exec('CREATE TABLE erp_people(id INTEGER PRIMARY KEY,administrator_id INTEGER,full_name TEXT,trade_name TEXT,status TEXT)');
        $this->pdo->exec("INSERT INTO erp_people VALUES(1,10,'Pessoa A',NULL,'active'),(2,10,'Pessoa A2',NULL,'active'),(3,20,'Pessoa B',NULL,'active')");
        $this->pdo->exec('CREATE TABLE erp_person_links(id INTEGER PRIMARY KEY,administrator_id INTEGER,person_id INTEGER,condominium_id INTEGER,unit_id INTEGER,role TEXT,starts_at TEXT,ends_at TEXT,status TEXT,UNIQUE(administrator_id,id,condominium_id,unit_id))');
        $today = date('Y-m-d');
        $insertLink = $this->pdo->prepare("INSERT INTO erp_person_links VALUES(?,?,?,?,?,'owner',?,NULL,'active')");
        foreach ([[10001,10,1,101,1001],[10002,10,2,102,1002],[20001,20,3,201,2001]] as $link) {
            $insertLink->execute([...$link, $today]);
        }
        $this->pdo->exec('CREATE TABLE erp_accounting_plans(id INTEGER PRIMARY KEY,administrator_id INTEGER,condominium_id INTEGER,status TEXT,name TEXT)');
        $this->pdo->exec("INSERT INTO erp_accounting_plans VALUES(501,10,101,'active','Plano A'),(502,10,102,'active','Plano A2'),(601,20,201,'active','Plano B')");
        $this->pdo->exec('CREATE TABLE erp_accounting_accounts(id INTEGER PRIMARY KEY,administrator_id INTEGER,plan_id INTEGER,code TEXT,name TEXT,nature TEXT,account_type TEXT,status TEXT)');
        $this->pdo->exec("INSERT INTO erp_accounting_accounts VALUES(7001,10,501,'1.1','Recebíveis','asset','analytic','active'),(7002,10,501,'3.1','Receita','revenue','analytic','active'),(7003,10,502,'1.1','Recebíveis A2','asset','analytic','active'),(7004,10,502,'3.1','Receita A2','revenue','analytic','active'),(8001,20,601,'1.1','Recebíveis B','asset','analytic','active'),(8002,20,601,'3.1','Receita B','revenue','analytic','active')");
        $this->pdo->exec('CREATE TABLE erp_accounting_periods(id INTEGER PRIMARY KEY,administrator_id INTEGER,condominium_id INTEGER,period_year INTEGER,period_month INTEGER,status TEXT)');
        $this->pdo->exec("INSERT INTO erp_accounting_periods VALUES(9001,10,101,2026,10,'open'),(9002,10,102,2026,10,'open'),(9901,10,101,2026,9,'closed'),(9101,20,201,2026,10,'open')");
        $this->pdo->exec('CREATE TABLE platform_audit_events(id INTEGER PRIMARY KEY AUTOINCREMENT,tenant_id INTEGER,actor_user_id INTEGER,event_type TEXT,subject_type TEXT,subject_id INTEGER,metadata TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->pdo->exec('CREATE TABLE erp_receivables(id INTEGER PRIMARY KEY AUTOINCREMENT,administrator_id INTEGER,condominium_id INTEGER,unit_id INTEGER,person_link_id INTEGER,plan_id INTEGER,receivable_account_id INTEGER,revenue_account_id INTEGER,description TEXT,total_amount NUMERIC,created_by_user_id INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->pdo->exec('CREATE TABLE erp_receivable_installments(id INTEGER PRIMARY KEY AUTOINCREMENT,administrator_id INTEGER,condominium_id INTEGER,receivable_id INTEGER,installment_number INTEGER,accounting_period_id INTEGER,due_date TEXT,amount NUMERIC,created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->repository = new ReceivableRepository($this->pdo);
        $this->service = new ReceivableService($this->pdo, $this->repository, new PlatformAudit($this->pdo));
    }

    public function testCreatesAndReadsMultiInstallmentReceivableWithAudit(): void
    {
        $id = $this->service->create(100, 10, 1, $this->validInput());
        $detail = $this->service->detail(10, $id);
        self::assertNotNull($detail);
        self::assertSame('100.50', number_format((float) $detail['total_amount'], 2, '.', ''));
        self::assertSame('Pessoa A', $detail['full_name']);
        self::assertSame('Recebíveis', $detail['receivable_account_name']);
        self::assertCount(2, $detail['installments']);
        self::assertSame('50.25', number_format((float) $detail['installments'][0]['amount'], 2, '.', ''));
        self::assertSame('50.25', number_format((float) $detail['installments'][1]['amount'], 2, '.', ''));
        self::assertCount(1, $this->service->search(10, ['query' => 'Pessoa A']));
        self::assertSame([], $this->service->search(20, ['query' => 'Pessoa A']));
        self::assertNull($this->service->detail(20, $id));
        $event = $this->pdo->query('SELECT * FROM platform_audit_events')->fetch(PDO::FETCH_ASSOC);
        self::assertSame('erp.receivable.created', $event['event_type']);
        self::assertSame('receivable', $event['subject_type']);
        self::assertSame(100, (int) $event['tenant_id']);
        self::assertStringContainsString('"installment_count":2', (string) $event['metadata']);
    }

    public function testRejectsInvalidTotalsAndCrossCondominiumReferences(): void
    {
        $valid = $this->validInput();
        $invalidInputs = [];
        $badTotal = $valid;
        $badTotal['total_amount'] = '100.51';
        $invalidInputs[] = [$badTotal, 'soma das parcelas'];
        $badPeriod = $valid;
        $badPeriod['installments']['period_id'][1] = 9901;
        $invalidInputs[] = [$badPeriod, 'mesmo condomínio'];
        $badAccount = $valid;
        $badAccount['revenue_account_id'] = 7004;
        $invalidInputs[] = [$badAccount, 'plano do condomínio'];
        $badLink = $valid;
        $badLink['person_link_id'] = 20001;
        $invalidInputs[] = [$badLink, 'vínculo ativo'];
        $badMoney = $valid;
        $badMoney['installments']['amount'][0] = '1.005';
        $invalidInputs[] = [$badMoney, 'valores monetários'];

        foreach ($invalidInputs as [$input, $message]) {
            try {
                $this->service->create(100, 10, 1, $input);
                self::fail('Invalid receivable data should be rejected.');
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString($message, $exception->getMessage());
            }
        }
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM erp_receivables')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM platform_audit_events')->fetchColumn());
    }

    public function testAuditFailureRollsBackTitleAndEveryInstallment(): void
    {
        $this->pdo->exec("CREATE TRIGGER fail_audit BEFORE INSERT ON platform_audit_events BEGIN SELECT RAISE(ABORT,'audit unavailable'); END");
        try {
            $this->service->create(100, 10, 1, $this->validInput());
            self::fail('Audit failure should roll back the full write.');
        } catch (PDOException $exception) {
            self::assertStringContainsString('audit unavailable', $exception->getMessage());
        }
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM erp_receivables')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM erp_receivable_installments')->fetchColumn());
    }

    /** @return array<string,mixed> */
    private function validInput(): array
    {
        return [
            'person_link_id' => 10001,
            'receivable_account_id' => 7001,
            'revenue_account_id' => 7002,
            'description' => 'Cota mensal',
            'total_amount' => '100.50',
            'installments' => [
                'period_id' => [9001, 9001],
                'due_date' => ['2026-10-10', '2026-11-10'],
                'amount' => ['50.25', '50.25'],
            ],
        ];
    }
}
