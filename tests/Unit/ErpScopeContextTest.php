<?php

declare(strict_types=1);

use Moves\Modules\Erp\Security\AccessScope;
use Moves\Modules\Erp\Security\ScopeContext;
use PHPUnit\Framework\TestCase;

final class ErpScopeContextTest extends TestCase
{
    public function testResolvesTrustedRouteScope(): void
    {
        $scope = ScopeContext::fromRoute([
            'scope_type' => 'condominium',
            'scope_id' => '12',
        ]);

        self::assertInstanceOf(AccessScope::class, $scope);
        self::assertTrue($scope->equals(AccessScope::condominium(12)));
    }

    public function testMissingOrInvalidRouteScopeIsDenied(): void
    {
        self::assertNull(ScopeContext::fromRoute([]));
        self::assertNull(ScopeContext::fromRoute(['scope_type' => 'condominium', 'scope_id' => 0]));
        self::assertNull(ScopeContext::fromRoute(['scope_type' => 'condominium', 'scope_id' => '12x']));
        self::assertNull(ScopeContext::fromRoute(['scope_type' => 'unknown', 'scope_id' => 12]));
    }

    public function testPayloadLikeKeysAreNotAcceptedAsScope(): void
    {
        self::assertNull(ScopeContext::fromRoute([
            'condominium_id' => 12,
            'scope' => ['type' => 'condominium', 'id' => 12],
        ]));
    }
}
