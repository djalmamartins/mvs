<?php

declare(strict_types=1);

use Moves\Boot\Modules;
use Moves\Modules\Erp\ErpModule;
use PHPUnit\Framework\TestCase;

final class ErpModuleTest extends TestCase
{
    public function testAdminReceivesErpAccessPermission(): void
    {
        ErpModule::register();

        self::assertContains('erp.access', Modules::permissions('admin'));
        self::assertNotContains('erp.access', Modules::permissions('user'));
    }

    public function testRepeatedRegistrationDoesNotDuplicatePermission(): void
    {
        ErpModule::register();
        ErpModule::register();

        $permissions = array_values(array_filter(
            Modules::permissions('admin'),
            static fn (string $permission): bool => $permission === 'erp.access'
        ));

        self::assertCount(1, $permissions);
    }
}
