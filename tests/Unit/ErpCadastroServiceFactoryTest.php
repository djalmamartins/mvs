<?php

declare(strict_types=1);

use Moves\Modules\Erp\Cadastros\CadastroServiceFactory;
use PHPUnit\Framework\TestCase;

final class ErpCadastroServiceFactoryTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, status TEXT)');
        $this->pdo->exec('CREATE TABLE talk_tenants (id INTEGER PRIMARY KEY, status TEXT)');
        $this->pdo->exec('CREATE TABLE talk_tenant_users (tenant_id INTEGER, user_id INTEGER, status TEXT)');
        $this->pdo->exec('CREATE TABLE platform_tenant_products (tenant_id INTEGER, product TEXT, enabled INTEGER)');
        $this->pdo->exec('CREATE TABLE erp_administrators (id INTEGER PRIMARY KEY, tenant_id INTEGER, legal_name TEXT, trade_name TEXT, tax_id TEXT, status TEXT DEFAULT \'active\', created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->pdo->exec('CREATE TABLE erp_condominiums (id INTEGER PRIMARY KEY AUTOINCREMENT, administrator_id INTEGER, legal_name TEXT, trade_name TEXT, tax_id TEXT, timezone TEXT, status TEXT DEFAULT \'active\', created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->pdo->exec('CREATE TABLE erp_scope_grants (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, capability TEXT, scope_type TEXT, scope_id INTEGER, revoked_at TEXT)');
        $this->pdo->exec('CREATE TABLE erp_security_audit (id INTEGER PRIMARY KEY AUTOINCREMENT, event_type TEXT, actor_user_id INTEGER, subject_user_id INTEGER, metadata_json TEXT, created_at TEXT)');
        $this->pdo->exec("INSERT INTO users(id,status) VALUES(7,'active'),(8,'active')");
        $this->pdo->exec("INSERT INTO talk_tenants(id,status) VALUES(100,'active')");
        $this->pdo->exec("INSERT INTO talk_tenant_users(tenant_id,user_id,status) VALUES(100,7,'active')");
        $this->pdo->exec("INSERT INTO platform_tenant_products(tenant_id,product,enabled) VALUES(100,'erp',1)");
        $this->pdo->exec("INSERT INTO erp_administrators (id,tenant_id,legal_name,tax_id) VALUES (10,100,'Admin A','A')");
    }

    public function testAccessFactoryKeepsScopedAuthorization(): void
    {
        $this->pdo->exec("INSERT INTO erp_scope_grants (user_id, capability, scope_type, scope_id) VALUES (7, 'erp.cadastros.read', 'administrator', 10)");
        $service = CadastroServiceFactory::access($this->pdo);

        self::assertNotNull($service->findAdministrator(7, 10));
        self::assertNull($service->findAdministrator(8, 10));
    }

    public function testWriteFactoryKeepsAuthorizationAndAppendOnlyAudit(): void
    {
        $this->pdo->exec("INSERT INTO erp_scope_grants (user_id, capability, scope_type, scope_id) VALUES (7, 'erp.cadastros.write', 'administrator', 10)");
        $service = CadastroServiceFactory::write($this->pdo);

        $id = $service->createCondominium(7, 10, 'Condo Factory', null, 'CF');

        self::assertNotNull($id);
        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM erp_security_audit WHERE event_type = 'erp.cadastros.condominium.created'")->fetchColumn());
        self::assertNull($service->createCondominium(8, 10, 'Blocked', null, 'B'));
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM erp_condominiums')->fetchColumn());
    }

    public function testFactoryDeniesRevokedTenantOrProduct(): void
    {
        $this->pdo->exec("INSERT INTO erp_scope_grants (user_id, capability, scope_type, scope_id) VALUES (7, 'erp.cadastros.read', 'administrator', 10)");
        $service = CadastroServiceFactory::access($this->pdo);
        self::assertNotNull($service->findAdministrator(7, 10));

        $this->pdo->exec("UPDATE platform_tenant_products SET enabled=0 WHERE tenant_id=100");
        self::assertNull($service->findAdministrator(7, 10));
        $this->pdo->exec("UPDATE platform_tenant_products SET enabled=1 WHERE tenant_id=100");
        $this->pdo->exec("UPDATE talk_tenant_users SET status='inactive' WHERE tenant_id=100 AND user_id=7");
        self::assertNull($service->findAdministrator(7, 10));
    }
}
