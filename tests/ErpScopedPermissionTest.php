<?php

declare(strict_types=1);

namespace Moves\Tests;

use InvalidArgumentException;
use Moves\Modules\Erp\Security\AccessScope;
use Moves\Modules\Erp\Security\ScopedPermission;
use PHPUnit\Framework\TestCase;

final class ErpScopedPermissionTest extends TestCase
{
    public function testGrantRequiresPermissionAndExactScope(): void
    {
        $grant = new ScopedPermission('condominium.read', AccessScope::condominium(12));

        self::assertTrue($grant->allows('condominium.read', AccessScope::condominium(12)));
        self::assertFalse($grant->allows('condominium.write', AccessScope::condominium(12)));
        self::assertFalse($grant->allows('condominium.read', AccessScope::condominium(13)));
        self::assertFalse($grant->allows('condominium.read', AccessScope::administrator(12)));
    }

    public function testWildcardAndMalformedCapabilitiesAreRejected(): void
    {
        foreach (['*', 'condominium', 'Condominium.read', 'condominium.*', ' condominium.read'] as $permission) {
            try {
                new ScopedPermission($permission, AccessScope::administrator(1));
                self::fail('Invalid permission was accepted: ' . $permission);
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }
}
