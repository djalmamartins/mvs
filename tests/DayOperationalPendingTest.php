<?php

declare(strict_types=1);

use Moves\Boot\Connection;
use Moves\Boot\Environment;
use Moves\Services\Day\DayService;
use Moves\Services\Day\OperationalPendingService;
use Moves\Services\Platform\CompanyService;
use Moves\Services\Platform\CondominiumService;
use PHPUnit\Framework\TestCase;

final class DayOperationalPendingTest extends TestCase
{
    private PDO $pdo;
    /** @var array<string,int> */
    private array $a = [];
    /** @var array<string,int> */
    private array $b = [];
    private string $suffix;
    private int $readerId = 0;

    protected function setUp(): void
    {
        Environment::load(dirname(__DIR__));
        $this->pdo = Connection::getInstance();
        $this->suffix = bin2hex(random_bytes(6));
        $this->a = $this->fixture('a');
        $this->b = $this->fixture('b');
    }

    protected function tearDown(): void
    {
        foreach ([$this->a, $this->b] as $fixture) {
            if (isset($fixture['tenant'])) {
                $this->pdo->prepare('DELETE FROM day_tasks WHERE tenant_id=?')->execute([$fixture['tenant']]);
                $this->pdo->prepare('DELETE FROM platform_audit_events WHERE tenant_id=?')->execute([$fixture['tenant']]);
                $this->pdo->prepare('DELETE FROM erp_condominiums WHERE administrator_id=?')->execute([$fixture['administrator']]);
                $this->pdo->prepare('DELETE FROM erp_scope_grants WHERE user_id=?')->execute([$fixture['user']]);
                $this->pdo->prepare('DELETE FROM talk_tenant_users WHERE tenant_id=?')->execute([$fixture['tenant']]);
                $this->pdo->prepare('DELETE FROM platform_roles WHERE tenant_id=?')->execute([$fixture['tenant']]);
                $this->pdo->prepare('DELETE FROM erp_administrators WHERE id=?')->execute([$fixture['administrator']]);
                $this->pdo->prepare('DELETE FROM platform_tenant_products WHERE tenant_id=?')->execute([$fixture['tenant']]);
                $this->pdo->prepare('DELETE FROM talk_tenants WHERE id=?')->execute([$fixture['tenant']]);
            }
            if (isset($fixture['user'])) { $this->pdo->prepare('DELETE FROM users WHERE id=?')->execute([$fixture['user']]); }
        }
        if ($this->readerId > 0) {
            $this->pdo->prepare('DELETE FROM erp_scope_grants WHERE user_id=?')->execute([$this->readerId]);
            $this->pdo->prepare('DELETE FROM talk_tenant_users WHERE user_id=?')->execute([$this->readerId]);
            $this->pdo->prepare('DELETE FROM users WHERE id=?')->execute([$this->readerId]);
        }
    }

    public function testMissingCnpjCreatesOneTenantScopedTaskAndValidCnpjResolvesAndReopensIt(): void
    {
        $service = new CondominiumService($this->pdo);
        $condominiumId = $service->save($this->a['tenant'], ['legal_name' => 'Condomínio sem CNPJ'], $this->a['user']);
        $pending = new OperationalPendingService($this->pdo);
        $tasks = $pending->list($this->a['tenant'], $this->a['administrator']);
        self::assertCount(1, $tasks);
        self::assertSame($condominiumId, (int) $tasks[0]['condominium_id']);
        self::assertSame('pending', $tasks[0]['status']);
        self::assertNull($tasks[0]['assigned_user_id']);
        self::assertSame('/erp/condominiums/' . $condominiumId, $this->sourceUrl($this->a['tenant']));

        $service->save($this->a['tenant'], ['legal_name' => 'Condomínio sem CNPJ'], $this->a['user'], $condominiumId);
        $pending->evaluateCondominiumCnpj($this->a['tenant'], $this->a['administrator'], $condominiumId, null, $this->a['user']);
        self::assertCount(1, $pending->list($this->a['tenant'], $this->a['administrator']));
        $taskId = (int) $tasks[0]['id'];
        $pending->update($this->a['tenant'], $this->a['administrator'], $taskId, $this->a['user'], [
            'assigned_user_id' => (string) $this->a['user'], 'status' => 'in_progress', 'priority' => 'high',
            'due_date' => date('Y-m-d', strtotime('+7 days')), 'description' => 'CNPJ em processamento junto à Receita Federal.',
        ]);
        self::assertSame($this->a['user'], (int) $pending->detail($this->a['tenant'], $this->a['administrator'], $taskId)['assigned_user_id']);
        self::assertCount(1, (new DayService($this->a['tenant']))->tasks($this->a['user']));
        self::assertNull($pending->detail($this->b['tenant'], $this->b['administrator'], $taskId));
        try {
            $pending->update($this->b['tenant'], $this->b['administrator'], $taskId, $this->b['user'], ['status' => 'pending']);
            self::fail('Tenant B não pode atualizar tarefa do tenant A.');
        } catch (RuntimeException $exception) {
            self::assertSame('Pendência não encontrada.', $exception->getMessage());
        }

        $service->save($this->a['tenant'], ['legal_name' => 'Condomínio sem CNPJ', 'tax_id' => '12.ABC.345/01DE-35'], $this->a['user'], $condominiumId);
        self::assertSame('done', $pending->detail($this->a['tenant'], $this->a['administrator'], $taskId)['status']);
        self::assertSame([], array_filter((new DayService($this->a['tenant']))->tasks($this->a['user']), static fn(array $row): bool => $row['status'] !== 'done'));
        $this->assertAuditEvent($this->a['tenant'], $taskId, 'erp.pending.resolved');

        $service->save($this->a['tenant'], ['legal_name' => 'Condomínio sem CNPJ', 'tax_id' => ''], $this->a['user'], $condominiumId);
        self::assertSame('pending', $pending->detail($this->a['tenant'], $this->a['administrator'], $taskId)['status']);
        self::assertSame($taskId, (int) $pending->detail($this->a['tenant'], $this->a['administrator'], $taskId)['id']);
        $this->assertAuditEvent($this->a['tenant'], $taskId, 'erp.pending.reopened');
    }

    public function testNumericAndAlphanumericCondominiumsAreNotFlaggedAndFiveScenarioBatchHasTwoPendings(): void
    {
        $service = new CondominiumService($this->pdo);
        $valid = [
            ['Condomínio numérico', '11222333000181'],
            ['Condomínio alfanumérico', '12.ABC.345/01DE-35'],
            ['Condomínio sem CNPJ', ''],
            ['Condomínio CNPJ em processo', ''],
            ['Condomínio cenário complexo', '00.000.000/0001-91'],
        ];
        $ids = [];
        foreach ($valid as [$name, $cnpj]) { $ids[] = $service->save($this->a['tenant'], ['legal_name' => $name, 'tax_id' => $cnpj], $this->a['user']); }
        $pending = new OperationalPendingService($this->pdo);
        $tasks = $pending->list($this->a['tenant'], $this->a['administrator']);
        self::assertCount(2, $tasks);
        self::assertSame([$ids[2], $ids[3]], array_map('intval', array_column($tasks, 'condominium_id')));
        $pending->update($this->a['tenant'], $this->a['administrator'], (int) $tasks[1]['id'], $this->a['user'], [
            'assigned_user_id' => '', 'status' => 'in_progress', 'priority' => 'normal', 'due_date' => '',
            'description' => 'CNPJ em processo; manter acompanhamento sem prazo legal presumido.',
        ]);
        self::assertSame('in_progress', $pending->detail($this->a['tenant'], $this->a['administrator'], (int) $tasks[1]['id'])['status']);
    }

    public function testCondominiumReadGrantDoesNotAllowEditingOrResolvingSourceData(): void
    {
        $service = new CondominiumService($this->pdo);
        $condominiumId = $service->save($this->a['tenant'], ['legal_name' => 'Somente leitura'], $this->a['user']);
        $this->pdo->prepare("INSERT INTO users(name,email,password,status,role) VALUES('Reader A',?,'test-only','active','user')")
            ->execute(['reader-' . $this->suffix . '@example.test']);
        $this->readerId = (int) $this->pdo->lastInsertId();
        $role = $this->pdo->prepare("SELECT id FROM platform_roles WHERE tenant_id=? AND slug='supervisor'");
        $role->execute([$this->a['tenant']]);
        $this->pdo->prepare("INSERT INTO talk_tenant_users(tenant_id,user_id,role,role_id,status,is_default) VALUES(?,?,'supervisor',?,'active',0)")
            ->execute([$this->a['tenant'], $this->readerId, (int) $role->fetchColumn()]);
        $this->pdo->prepare("INSERT INTO erp_scope_grants(user_id,capability,scope_type,scope_id) VALUES(?,'erp.cadastros.read','condominium',?)")
            ->execute([$this->readerId, $condominiumId]);
        self::assertTrue($service->canView($this->a['tenant'], $this->a['administrator'], $condominiumId, $this->readerId));
        self::assertFalse($service->canEdit($this->a['tenant'], $this->a['administrator'], $condominiumId, $this->readerId));
        try {
            $service->save($this->a['tenant'], ['legal_name' => 'Tentativa de alteração', 'tax_id' => '12.ABC.345/01DE-35'], $this->readerId, $condominiumId);
            self::fail('Permissão somente leitura não pode editar nem resolver a pendência.');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString('permissão', $exception->getMessage());
        }
        $taxId = $this->pdo->prepare('SELECT tax_id FROM erp_condominiums WHERE id=?');
        $taxId->execute([$condominiumId]);
        self::assertNull($taxId->fetchColumn());
    }

    private function sourceUrl(int $tenantId): string
    {
        $statement = $this->pdo->prepare('SELECT source_url FROM day_tasks WHERE tenant_id=? LIMIT 1');
        $statement->execute([$tenantId]);
        return (string) $statement->fetchColumn();
    }

    private function assertAuditEvent(int $tenantId, int $taskId, string $event): void
    {
        $statement = $this->pdo->prepare("SELECT COUNT(*) FROM platform_audit_events WHERE tenant_id=? AND event_type=? AND subject_type='day_task' AND subject_id=?");
        $statement->execute([$tenantId, $event, $taskId]);
        self::assertGreaterThan(0, (int) $statement->fetchColumn());
    }

    /** @return array{tenant:int,user:int,administrator:int} */
    private function fixture(string $label): array
    {
        $slug = 'op-pending-' . $this->suffix . '-' . $label;
        $this->pdo->prepare("INSERT INTO users(name,email,password,status,role) VALUES(?,?,?,'active','user')")
            ->execute(['Pending ' . $label, $slug . '@example.test', password_hash('Test-Operational-2026!', PASSWORD_DEFAULT)]);
        $user = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare("INSERT INTO talk_tenants(name,slug,status) VALUES(?,?,'active')")->execute(['Pending ' . $label, $slug]);
        $tenant = (int) $this->pdo->lastInsertId();
        (new CompanyService($this->pdo))->ensureRoles($tenant);
        $role = $this->pdo->prepare("SELECT id FROM platform_roles WHERE tenant_id=? AND slug='owner'");
        $role->execute([$tenant]);
        $this->pdo->prepare("INSERT INTO talk_tenant_users(tenant_id,user_id,role,role_id,status,is_default) VALUES(?,?,'admin',?,'active',1)")->execute([$tenant, $user, (int) $role->fetchColumn()]);
        $this->pdo->prepare("INSERT INTO platform_tenant_products(tenant_id,product,enabled) VALUES(?,'erp',1)")->execute([$tenant]);
        $this->pdo->prepare("INSERT INTO erp_administrators(tenant_id,legal_name,trade_name,tax_id,status) VALUES(?,?,?,?, 'active')")->execute([$tenant, 'Pending ' . $label, 'Pending ' . $label, 'PENDING' . substr(hash('sha256', $slug), 0, 8)]);
        $administrator = (int) $this->pdo->lastInsertId();
        $grant = $this->pdo->prepare("INSERT INTO erp_scope_grants(user_id,capability,scope_type,scope_id) VALUES(?,?,'administrator',?)");
        foreach (['erp.access','erp.cadastros.read','erp.cadastros.write'] as $capability) { $grant->execute([$user, $capability, $administrator]); }
        return compact('tenant', 'user', 'administrator');
    }
}
