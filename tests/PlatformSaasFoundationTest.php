<?php

declare(strict_types=1);

use Moves\Boot\Connection;
use Moves\Boot\Environment;
use Moves\Services\Platform\CompanyService;
use Moves\Services\Platform\CondominiumService;
use Moves\Services\Platform\MemberService;
use Moves\Services\Platform\ProductEntitlement;
use Moves\Services\Platform\TenantAuthorization;
use PHPUnit\Framework\TestCase;

final class PlatformSaasFoundationTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        Environment::load(dirname(__DIR__));
        $this->pdo = Connection::getInstance();
        $this->pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
    }

    public function testTwoAdministratorsRemainIsolatedAcrossProductsMembersAndCondominiums(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $ownerA = $this->user('owner-a-' . $suffix . '@example.test');
        $ownerB = $this->user('owner-b-' . $suffix . '@example.test');
        $companies = new CompanyService($this->pdo);
        $tenantA = $companies->create(['name'=>'Admin A','legal_name'=>'Administradora A'], $ownerA, ['talk','support']);
        $tenantB = $companies->create(['name'=>'Admin B','legal_name'=>'Administradora B'], $ownerB, ['erp']);

        $entitlements = new ProductEntitlement($this->pdo);
        self::assertTrue($entitlements->enabled($tenantA, 'talk'));
        self::assertFalse($entitlements->enabled($tenantA, 'erp'));
        self::assertTrue($entitlements->enabled($tenantB, 'erp'));
        self::assertFalse($entitlements->enabled($tenantB, 'talk'));
        $ownerGrants = $this->pdo->prepare(
            "SELECT capability FROM erp_scope_grants WHERE user_id=? AND scope_type='administrator' ORDER BY capability"
        );
        $ownerGrants->execute([$ownerB]);
        self::assertSame(['erp.cadastros.read', 'erp.cadastros.write'], $ownerGrants->fetchAll(PDO::FETCH_COLUMN));
        self::assertTrue((new TenantAuthorization($this->pdo))->can($ownerA, $tenantA, 'settings.manage'));
        self::assertFalse((new TenantAuthorization($this->pdo))->can($ownerA, $tenantB, 'settings.manage'));

        $member = $this->user('agent-' . $suffix . '@example.test');
        $members = new MemberService($this->pdo);
        $agentRoles = array_values(array_filter($members->roles($tenantA), static fn(array $role): bool => $role['slug']==='agent'));
        $members->add($tenantA, 'agent-' . $suffix . '@example.test', (int) $agentRoles[0]['id'], $ownerA);
        self::assertCount(2, $members->all($tenantA));
        self::assertCount(1, $members->all($tenantB));
        self::assertTrue((new TenantAuthorization($this->pdo))->can($member, $tenantA, 'talk.access'));
        self::assertFalse((new TenantAuthorization($this->pdo))->can($member, $tenantA, 'settings.manage'));

        $condominiums = new CondominiumService($this->pdo);
        $condominiums->save($tenantA, ['legal_name'=>'Condomínio A','tax_id'=>'11222333000181'], $ownerA);
        self::assertCount(1, $condominiums->all($tenantA));
        self::assertCount(0, $condominiums->all($tenantB));
        self::assertGreaterThanOrEqual(3, (int) $this->pdo->query('SELECT COUNT(*) FROM platform_audit_events')->fetchColumn());
    }

    public function testCompanyRegistrationPreservesAlphanumericCnpjAsText(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $owner = $this->user('owner-alpha-cnpj-' . $suffix . '@example.test');
        $companies = new CompanyService($this->pdo);
        $tenantId = $companies->create(
            ['name'=>'Administradora Alpha '.$suffix,'legal_name'=>'Administradora Alpha '.$suffix,'tax_id'=>'12.abc.345/01de-35'],
            $owner,
            ['erp']
        );

        $tenantTaxId = $this->pdo->prepare('SELECT tax_id FROM talk_tenants WHERE id=?');
        $tenantTaxId->execute([$tenantId]);
        self::assertSame('12ABC34501DE35', $tenantTaxId->fetchColumn());

        $administratorTaxId = $this->pdo->prepare('SELECT tax_id FROM erp_administrators WHERE tenant_id=?');
        $administratorTaxId->execute([$tenantId]);
        self::assertSame('12ABC34501DE35', $administratorTaxId->fetchColumn());

        $base = 'XY123456ABCD';
        $digit = static function (string $value, array $weights): string {
            $sum = 0;
            foreach ($weights as $index => $weight) {
                $sum += (ord($value[$index]) - 48) * $weight;
            }
            $remainder = $sum % 11;
            return (string) ($remainder < 2 ? 0 : 11 - $remainder);
        };
        $first = $digit($base, [5,4,3,2,9,8,7,6,5,4,3,2]);
        $changedCnpj = $base . $first . $digit($base . $first, [6,5,4,3,2,9,8,7,6,5,4,3,2]);
        $companies->update($tenantId, [
            'name'=>'Administradora Alpha '.$suffix,
            'legal_name'=>'Administradora Alpha '.$suffix,
            'tax_id'=>$changedCnpj,
        ], $owner);
        $tenantTaxId->execute([$tenantId]);
        self::assertSame($changedCnpj, $tenantTaxId->fetchColumn());
        $administratorTaxId->execute([$tenantId]);
        self::assertSame($changedCnpj, $administratorTaxId->fetchColumn());
    }

    private function user(string $email): int
    {
        $statement = $this->pdo->prepare("INSERT INTO users(name,email,password,status,role) VALUES('SaaS test',?,'test-only','active','user')");
        $statement->execute([$email]);
        return (int) $this->pdo->lastInsertId();
    }
}
