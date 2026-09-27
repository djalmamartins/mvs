<?php

declare(strict_types=1);

use Moves\Modules\Erp\Security\AccessScope;
use Moves\Modules\Erp\Security\ScopeGrantRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class ErpScopeGrantRepositoryTest extends TestCase
{
    private PDO $pdo;

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
    }

    public function testReturnsOnlyActiveGrantsForRequestedUser(): void
    {
        $this->pdo->exec("INSERT INTO erp_scope_grants (user_id, capability, scope_type, scope_id, revoked_at) VALUES
            (7, 'erp.condominium.read', 'condominium', 12, NULL),
            (7, 'erp.condominium.write', 'condominium', 12, '2026-09-21 20:00:00'),
            (8, 'erp.condominium.read', 'condominium', 99, NULL)");

        $grants = (new ScopeGrantRepository($this->pdo))->activeForUser(7);

        self::assertCount(1, $grants);
        self::assertTrue($grants[0]->allows('erp.condominium.read', AccessScope::condominium(12)));
    }

    public function testInvalidUserIdIsDeniedWithoutQuerying(): void
    {
        self::assertSame([], (new ScopeGrantRepository($this->pdo))->activeForUser(0));
        self::assertSame([], (new ScopeGrantRepository($this->pdo))->activeForUser(-1));
    }

    public function testUserWithoutGrantsIsDenied(): void
    {
        self::assertSame([], (new ScopeGrantRepository($this->pdo))->activeForUser(404));
    }
}
