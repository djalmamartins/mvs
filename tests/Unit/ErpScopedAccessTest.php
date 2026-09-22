<?php

declare(strict_types=1);

use Moves\Modules\Erp\Security\ScopeGrantRepository;
use Moves\Modules\Erp\Security\ScopedAccess;
use PDO;
use PHPUnit\Framework\TestCase;

final class ErpScopedAccessTest extends TestCase
{
    private PDO $pdo;
    private ScopedAccess $access;

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

        $this->access = new ScopedAccess(new ScopeGrantRepository($this->pdo));
    }

    public function testAllowsOnlyActiveGrantMatchingTrustedRouteScope(): void
    {
        $this->pdo->exec("INSERT INTO erp_scope_grants (user_id, capability, scope_type, scope_id, revoked_at) VALUES
            (7, 'erp.condominium.read', 'condominium', 12, NULL),
            (7, 'erp.condominium.write', 'condominium', 12, '2026-09-21 20:00:00')");

        self::assertTrue($this->access->allows(7, 'erp.condominium.read', [
            'scope_type' => 'condominium',
            'scope_id' => 12,
        ]));
        self::assertFalse($this->access->allows(7, 'erp.condominium.write', [
            'scope_type' => 'condominium',
            'scope_id' => 12,
        ]));
    }

    public function testDeniesDifferentUserOrScope(): void
    {
        $this->pdo->exec("INSERT INTO erp_scope_grants (user_id, capability, scope_type, scope_id, revoked_at)
            VALUES (7, 'erp.condominium.read', 'condominium', 12, NULL)");

        self::assertFalse($this->access->allows(8, 'erp.condominium.read', [
            'scope_type' => 'condominium',
            'scope_id' => 12,
        ]));
        self::assertFalse($this->access->allows(7, 'erp.condominium.read', [
            'scope_type' => 'condominium',
            'scope_id' => 13,
        ]));
    }

    public function testDeniesMissingOrUntrustedScopeContext(): void
    {
        self::assertFalse($this->access->allows(7, 'erp.condominium.read', []));
        self::assertFalse($this->access->allows(7, 'erp.condominium.read', [
            'condominium_id' => 12,
        ]));
        self::assertFalse($this->access->allows(0, 'erp.condominium.read', [
            'scope_type' => 'condominium',
            'scope_id' => 12,
        ]));
    }
}
