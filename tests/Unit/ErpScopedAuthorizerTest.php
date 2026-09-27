<?php

declare(strict_types=1);

use Moves\Modules\Erp\Security\AccessScope;
use Moves\Modules\Erp\Security\ScopedAuthorizer;
use PHPUnit\Framework\TestCase;

final class ErpScopedAuthorizerTest extends TestCase
{
    public function testAllowsOnlyWhenPermissionAndScopeMatch(): void
    {
        self::assertTrue(ScopedAuthorizer::allows(
            'erp.access',
            AccessScope::condominium(10),
            ['erp.access'],
            [AccessScope::administrator(2), AccessScope::condominium(10)],
        ));
    }

    public function testDeniesWhenPermissionIsMissing(): void
    {
        self::assertFalse(ScopedAuthorizer::allows(
            'erp.access',
            AccessScope::condominium(10),
            [],
            [AccessScope::condominium(10)],
        ));
    }

    public function testDeniesWhenScopeIsMissingOrDifferent(): void
    {
        self::assertFalse(ScopedAuthorizer::allows(
            'erp.access',
            AccessScope::condominium(10),
            ['erp.access'],
            [AccessScope::condominium(11)],
        ));

        self::assertFalse(ScopedAuthorizer::allows(
            'erp.access',
            AccessScope::condominium(10),
            ['erp.access'],
            [],
        ));
    }
}
