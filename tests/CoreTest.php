<?php

declare(strict_types=1);

use Moves\Boot\Modules;
use Moves\Core\Config;
use Moves\Core\Theme;
use Moves\Core\Validator;
use PHPUnit\Framework\TestCase;

/**
 * Moves | Core Test
 *
 * Valida componentes centrais sem dependências externas.
 *
 * @author Djalma Martins
 */
final class CoreTest extends TestCase
{
    public function testConfigurationBooleanAndEnvironment(): void
    {
        $_ENV['APP_ENV'] = 'production';
        $_ENV['APP_DEBUG'] = 'false';

        self::assertTrue(Config::isProduction());
        self::assertFalse(Config::debug());
    }

    public function testThemeFollowsRequestContext(): void
    {
        $_SERVER['REQUEST_URI'] = '/admin/settings';
        self::assertSame('admin', Theme::active());

        $_SERVER['REQUEST_URI'] = '/app/profile';
        self::assertSame('app', Theme::active());

        $_SERVER['REQUEST_URI'] = '/login';
        self::assertSame('site', Theme::active());
    }

    public function testValidatorCollectsErrors(): void
    {
        $validator = (new Validator())
            ->required('name', '')
            ->email('email', 'invalid');

        self::assertTrue($validator->fails());
        self::assertCount(2, $validator->errors());
    }

    public function testModulePermissionsAreDeduplicated(): void
    {
        Modules::register('test', null, ['user' => ['test.view', 'test.view']]);

        self::assertContains('test.view', Modules::permissions('user'));
        self::assertSame(
            Modules::permissions('user'),
            array_values(array_unique(Modules::permissions('user')))
        );
    }
}
