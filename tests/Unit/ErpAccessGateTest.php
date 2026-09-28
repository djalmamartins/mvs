<?php

declare(strict_types=1);

use Moves\Modules\Erp\Security\AccessGate;
use Moves\Modules\Erp\Security\AccessScope;
use Moves\Modules\Erp\Security\ScopeGrantRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class ErpAccessGateTest extends TestCase
{
    private PDO $pdo;
    private AccessGate $gate;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE erp_scope_grants (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                capability TEXT NOT NULL,
                scope_type TEXT NOT NULL,
                scope_id INTEGER NOT NULL,
                revoked_at TEXT NULL
            )'
        );
        $this->pdo->exec("INSERT INTO erp_scope_grants (user_id, capability, scope_type, scope_id, revoked_at) VALUES
            (7, 'erp.condominium.read', 'condominium', 12, NULL),
            (7, 'erp.condominium.write', 'condominium', 12, '2026-09-21 20:00:00')");

        $this->gate = new AccessGate(new ScopeGrantRepository($this->pdo));
    }

    public function testAllowsOnlyMatchingActivePersistedGrant(): void
    {
        self::assertTrue($this->gate->allows(7, 'erp.condominium.read', AccessScope::condominium(12)));
        self::assertFalse($this->gate->allows(7, 'erp.condominium.write', AccessScope::condominium(12)));
        self::assertFalse($this->gate->allows(7, 'erp.condominium.read', AccessScope::condominium(99)));
    }

    public function testMissingIdentityOrTrustedScopeIsDenied(): void
    {
        self::assertFalse($this->gate->allows(0, 'erp.condominium.read', AccessScope::condominium(12)));
        self::assertFalse($this->gate->allows(7, 'erp.condominium.read', null));
    }

    public function testRepositoryFailureFailsClosed(): void
    {
        $this->pdo->exec('DROP TABLE erp_scope_grants');

        self::assertFalse($this->gate->allows(7, 'erp.condominium.read', AccessScope::condominium(12)));
    }
}
