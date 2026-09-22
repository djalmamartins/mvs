<?php

declare(strict_types=1);

use Moves\Core\HttpException;
use Moves\Modules\Erp\Security\ScopeGrantRepository;
use Moves\Modules\Erp\Security\ScopedAccess;
use Moves\Modules\Erp\Security\ScopedAccessMiddleware;
use PDO;
use PHPUnit\Framework\TestCase;

final class ErpScopedAccessMiddlewareTest extends TestCase
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

    public function testAllowsAuthenticatedUserWithActiveGrantAndTrustedRouteScope(): void
    {
        $this->pdo->exec("INSERT INTO erp_scope_grants (user_id, capability, scope_type, scope_id, revoked_at)
            VALUES (7, 'erp.condominium.read', 'condominium', 12, NULL)");

        $middleware = new ScopedAccessMiddleware(
            $this->access,
            'erp.condominium.read',
            static fn (): ?int => 7,
            static fn (): array => ['scope_type' => 'condominium', 'scope_id' => 12]
        );

        self::assertSame('ok', $middleware->handle(static fn (): string => 'ok'));
    }

    public function testDeniesMissingAuthenticatedIdentity(): void
    {
        $middleware = new ScopedAccessMiddleware(
            $this->access,
            'erp.condominium.read',
            static fn (): ?int => null,
            static fn (): array => ['scope_type' => 'condominium', 'scope_id' => 12]
        );

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(0);
        $middleware->handle(static fn (): string => 'must-not-run');
    }

    public function testDeniesUntrustedOrDifferentRouteScope(): void
    {
        $this->pdo->exec("INSERT INTO erp_scope_grants (user_id, capability, scope_type, scope_id, revoked_at)
            VALUES (7, 'erp.condominium.read', 'condominium', 12, NULL)");

        foreach ([
            ['condominium_id' => 12],
            ['scope_type' => 'condominium', 'scope_id' => 13],
        ] as $attributes) {
            $middleware = new ScopedAccessMiddleware(
                $this->access,
                'erp.condominium.read',
                static fn (): ?int => 7,
                static fn (): array => $attributes
            );

            try {
                $middleware->handle(static fn (): string => 'must-not-run');
                self::fail('Expected scoped access to be denied.');
            } catch (HttpException $exception) {
                self::assertSame(403, $exception->statusCode());
            }
        }
    }
}
