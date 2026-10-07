<?php

declare(strict_types=1);

use Moves\Modules\Erp\ChartOfAccounts\ChartOfAccountsRepository;
use Moves\Modules\Erp\ChartOfAccounts\ChartOfAccountsService;
use Moves\Services\Platform\PlatformAudit;
use PHPUnit\Framework\TestCase;

final class ErpChartOfAccountsServiceTest extends TestCase
{
    private PDO $pdo;
    private ChartOfAccountsRepository $repository;
    private ChartOfAccountsService $service;
    private int $planA;

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
        $this->pdo->exec("INSERT INTO erp_condominiums VALUES(101,10,'Condomínio A','A','active'),(102,10,'Condomínio A2','A2','active'),(201,20,'Condomínio B','B','active')");
        $this->pdo->exec("CREATE TABLE erp_accounting_plans(id INTEGER PRIMARY KEY AUTOINCREMENT,administrator_id INTEGER NOT NULL,condominium_id INTEGER NOT NULL,name TEXT NOT NULL,status TEXT NOT NULL DEFAULT 'active',created_by_user_id INTEGER NOT NULL,updated_by_user_id INTEGER NOT NULL,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(administrator_id,id),UNIQUE(administrator_id,condominium_id),FOREIGN KEY(administrator_id) REFERENCES erp_administrators(id),FOREIGN KEY(administrator_id,condominium_id) REFERENCES erp_condominiums(administrator_id,id),FOREIGN KEY(created_by_user_id) REFERENCES users(id),FOREIGN KEY(updated_by_user_id) REFERENCES users(id),CHECK(status IN ('active','inactive')))");
        $this->pdo->exec("CREATE TABLE erp_accounting_accounts(id INTEGER PRIMARY KEY AUTOINCREMENT,administrator_id INTEGER NOT NULL,plan_id INTEGER NOT NULL,parent_id INTEGER NULL,code TEXT NOT NULL,name TEXT NOT NULL,nature TEXT NOT NULL,account_type TEXT NOT NULL,level INTEGER NOT NULL DEFAULT 1,sort_order INTEGER NOT NULL DEFAULT 0,status TEXT NOT NULL DEFAULT 'active',created_by_user_id INTEGER NOT NULL,updated_by_user_id INTEGER NOT NULL,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(plan_id,code),UNIQUE(plan_id,id),FOREIGN KEY(administrator_id,plan_id) REFERENCES erp_accounting_plans(administrator_id,id),FOREIGN KEY(plan_id,parent_id) REFERENCES erp_accounting_accounts(plan_id,id),FOREIGN KEY(created_by_user_id) REFERENCES users(id),FOREIGN KEY(updated_by_user_id) REFERENCES users(id),CHECK(parent_id IS NULL OR parent_id<>id),CHECK(level BETWEEN 1 AND 8),CHECK(nature IN ('asset','liability','equity','revenue','expense')),CHECK(account_type IN ('synthetic','analytic')),CHECK(status IN ('active','inactive')))");
        $this->pdo->exec('CREATE TABLE platform_audit_events(id INTEGER PRIMARY KEY AUTOINCREMENT,tenant_id INTEGER,actor_user_id INTEGER,event_type TEXT,subject_type TEXT,subject_id INTEGER,metadata TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->repository = new ChartOfAccountsRepository($this->pdo);
        $this->service = new ChartOfAccountsService($this->pdo, $this->repository, new PlatformAudit($this->pdo));
        $this->planA = $this->service->createPlan(100, 10, 1, ['condominium_id' => 101, 'name' => 'Plano A']);
    }

    public function testCreatesPlanRootAndChildAndPersistsAudit(): void
    {
        $root = $this->service->createAccount(100, 10, 1, $this->planA, $this->account('1', 'Ativo', 'synthetic'));
        $child = $this->service->createAccount(100, 10, 1, $this->planA, $this->account('1.1', 'Disponível', 'synthetic', 'asset', $root));
        $leaf = $this->service->createAccount(100, 10, 1, $this->planA, $this->account('1.1.1', 'Caixa', 'analytic', 'asset', $child));
        self::assertSame(1, (int) $this->repository->findAccount(10, $this->planA, $root)['level']);
        self::assertSame(2, (int) $this->repository->findAccount(10, $this->planA, $child)['level']);
        self::assertSame(3, (int) $this->repository->findAccount(10, $this->planA, $leaf)['level']);
        self::assertCount(3, $this->repository->searchAccounts(10, $this->planA, ['query' => '', 'nature' => 'asset', 'status' => 'active']));
        self::assertSame(4, (int) $this->pdo->query('SELECT COUNT(*) FROM platform_audit_events')->fetchColumn());
        self::assertSame('erp.accounting_account.created', $this->pdo->query("SELECT event_type FROM platform_audit_events WHERE subject_type='accounting_account' ORDER BY id DESC LIMIT 1")->fetchColumn());
        $metadataStatement = $this->pdo->prepare("SELECT metadata FROM platform_audit_events WHERE subject_type='accounting_account' AND subject_id=:account_id");
        $metadataStatement->execute(['account_id' => $child]);
        $metadata = json_decode((string) $metadataStatement->fetchColumn(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('1.1', $metadata['code']);
        self::assertSame($root, $metadata['parent_id']);
        self::assertSame(2, $metadata['level']);
    }

    public function testRejectsDuplicateCodesInvalidParentsNatureAndAnalyticChildren(): void
    {
        $root = $this->service->createAccount(100, 10, 1, $this->planA, $this->account('1', 'Ativo', 'synthetic'));
        $analytic = $this->service->createAccount(100, 10, 1, $this->planA, $this->account('1.9', 'Analítica', 'analytic'));
        foreach ([
            [$this->account('1', 'Duplicada', 'synthetic'), 'código já existe'],
            [$this->account('2', 'Natureza diferente', 'synthetic', 'expense', $root), 'natureza'],
            [$this->account('3', 'Filha de analítica', 'synthetic', 'asset', $analytic), 'sintética e estar ativa'],
            [$this->account('4', 'Pai cross-plan', 'synthetic', 'asset', 999), 'não pertence'],
            [$this->account('5', 'Pai inválido', 'synthetic', 'asset', 'invalid'), 'pai válida'],
            [array_merge($this->account('6', 'Ordenação inválida', 'synthetic'), ['sort_order' => '4294967296']), 'ordenação'],
        ] as [$input, $message]) {
            try {
                $this->service->createAccount(100, 10, 1, $this->planA, $input);
                self::fail('Input should have been rejected.');
            } catch (InvalidArgumentException $exception) {
                if ($message !== '') {
                    self::assertStringContainsString($message, $exception->getMessage());
                }
            }
        }
        $leaf = $this->service->createAccount(100, 10, 1, $this->planA, $this->account('1.1', 'Folha', 'analytic', 'asset', $root));
        try {
            $this->service->createAccount(100, 10, 1, $this->planA, $this->account('1.1.1', 'Filha', 'analytic', 'asset', $leaf));
            self::fail('Analytic account cannot have children.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('sintética', $exception->getMessage());
        }
    }

    public function testRejectsSelfParentCycleAndParentFromAnotherTenantOrPlan(): void
    {
        $root = $this->service->createAccount(100, 10, 1, $this->planA, $this->account('1', 'Ativo', 'synthetic'));
        $child = $this->service->createAccount(100, 10, 1, $this->planA, $this->account('1.1', 'Circulante', 'synthetic', 'asset', $root));
        $otherPlan = $this->service->createPlan(100, 10, 1, ['condominium_id' => 102, 'name' => 'Plano A2']);
        $crossTenantPlan = $this->service->createPlan(200, 20, 2, ['condominium_id' => 201, 'name' => 'Plano B']);
        $foreign = $this->service->createAccount(200, 20, 2, $crossTenantPlan, $this->account('1', 'Ativo B', 'synthetic'));
        foreach ([[$root, 'mesma'], [$child, 'ciclo'], [$foreign, 'não pertence']] as [$parentId, $message]) {
            try {
                $this->service->updateAccount(100, 10, 1, $this->planA, $root, $this->account('1', 'Ativo', 'synthetic', 'asset', $parentId));
                self::fail('Invalid hierarchy change should fail.');
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString($message, $exception->getMessage());
            }
        }
        self::assertNull($this->repository->findAccount(10, $otherPlan, $foreign));
        $constraintFailed = false;
        try {
            $this->pdo->prepare("INSERT INTO erp_accounting_accounts(administrator_id,plan_id,parent_id,code,name,nature,account_type,level,sort_order,status,created_by_user_id,updated_by_user_id) VALUES(10,?,?,?,?,?,'synthetic',2,0,'active',1,1)")
                ->execute([$this->planA, $foreign, '8', 'Cross parent', 'asset']);
        } catch (PDOException) {
            $constraintFailed = true;
        }
        self::assertTrue($constraintFailed, 'Database composite FK rejects cross-plan parent IDs.');
    }

    public function testEditRecalculatesSubtreeAndCannotBreakNatureOrInactivateParent(): void
    {
        $root = $this->service->createAccount(100, 10, 1, $this->planA, $this->account('1', 'Ativo', 'synthetic'));
        $child = $this->service->createAccount(100, 10, 1, $this->planA, $this->account('1.1', 'Circulante', 'synthetic', 'asset', $root));
        $leaf = $this->service->createAccount(100, 10, 1, $this->planA, $this->account('1.1.1', 'Caixa', 'analytic', 'asset', $child));
        $newRoot = $this->service->createAccount(100, 10, 1, $this->planA, $this->account('2', 'Ativo B', 'synthetic'));
        $this->service->updateAccount(100, 10, 1, $this->planA, $child, $this->account('1.1', 'Circulante', 'synthetic', 'asset', $newRoot));
        self::assertSame(2, (int) $this->repository->findAccount(10, $this->planA, $child)['level']);
        self::assertSame(3, (int) $this->repository->findAccount(10, $this->planA, $leaf)['level']);
        foreach ([[$child, 'expense', 'active'], [$newRoot, 'asset', 'inactive']] as [$id, $nature, $status]) {
            try {
                $this->service->updateAccount(100, 10, 1, $this->planA, $id, $this->account((string)$id, 'Alteração', 'synthetic', $nature, null, $status));
                self::fail('Invalid parent update should fail.');
            } catch (InvalidArgumentException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }
    }

    public function testAuditFailureRollsBackAccountAndWrongAdministratorCannotRead(): void
    {
        $this->pdo->exec("CREATE TRIGGER fail_account_audit BEFORE INSERT ON platform_audit_events WHEN NEW.subject_type='accounting_account' BEGIN SELECT RAISE(ABORT,'audit unavailable'); END");
        try {
            $this->service->createAccount(100, 10, 1, $this->planA, $this->account('9', 'Rollback', 'synthetic'));
            self::fail('Audit failure should rollback account.');
        } catch (PDOException $exception) {
            self::assertStringContainsString('audit unavailable', $exception->getMessage());
        }
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM erp_accounting_accounts')->fetchColumn());
        self::assertNull($this->repository->findPlan(20, $this->planA));
        self::assertSame([], $this->repository->searchAccounts(20, $this->planA, ['query' => '', 'nature' => '', 'status' => '']));
    }

    public function testInactivationChecksAllDescendantsAndHierarchyHasEightLevelsMaximum(): void
    {
        $parent = $this->service->createAccount(100, 10, 1, $this->planA, $this->account('1', 'Raiz', 'synthetic'));
        $child = $this->service->createAccount(100, 10, 1, $this->planA, $this->account('1.1', 'Grupo', 'synthetic', 'asset', $parent));
        $grandchild = $this->service->createAccount(100, 10, 1, $this->planA, $this->account('1.1.1', 'Subgrupo', 'synthetic', 'asset', $child));
        $leaf = $this->service->createAccount(100, 10, 1, $this->planA, $this->account('1.1.1.1', 'Folha', 'analytic', 'asset', $grandchild));
        // Simulate older/imported inconsistent data: an active descendant is
        // protected even when an intermediate account is already inactive.
        $this->pdo->exec("UPDATE erp_accounting_accounts SET status='inactive' WHERE id=" . $child);
        try {
            $this->service->updateAccount(100, 10, 1, $this->planA, $parent, $this->account('1', 'Raiz', 'synthetic', 'asset', null, 'inactive'));
            self::fail('Active grandchild must prevent parent inactivation.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('filhas ativas', $exception->getMessage());
        }
        self::assertSame('active', $this->repository->findAccount(10, $this->planA, $parent)['status']);
        self::assertSame('active', $this->repository->findAccount(10, $this->planA, $leaf)['status']);

        $currentParent = $this->service->createAccount(100, 10, 1, $this->planA, $this->account('2', 'Raiz da profundidade', 'synthetic'));
        for ($level = 2; $level <= 8; ++$level) {
            $currentParent = $this->service->createAccount(100, 10, 1, $this->planA, $this->account('L' . $level, 'Grupo ' . $level, 'synthetic', 'asset', $currentParent));
        }
        try {
            $this->service->createAccount(100, 10, 1, $this->planA, $this->account('L9', 'Nível nove', 'analytic', 'asset', $currentParent));
            self::fail('Ninth hierarchy level must be refused.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('oito níveis', $exception->getMessage());
        }
    }

    /** @return array<string,mixed> */
    private function account(string $code, string $name, string $type, string $nature = 'asset', int|string|null $parentId = null, string $status = 'active'): array
    {
        return ['code' => $code, 'name' => $name, 'account_type' => $type, 'nature' => $nature, 'parent_id' => $parentId, 'status' => $status, 'sort_order' => 0];
    }
}
