<?php

declare(strict_types=1);

use Moves\Boot\Modules;
use Moves\Controllers\ErrorController;
use Moves\Core\Config;
use Moves\Core\Theme;
use Moves\Core\Validator;
use Moves\Core\Response;
use PHPUnit\Framework\TestCase;
use MovesCode\Router\Router;
use MovesCode\View\Engine;

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

    public function testProductionErrorDoesNotExposeTechnicalDetails(): void
    {
        $_ENV['APP_DEBUG'] = 'false';
        $_SERVER['REQUEST_URI'] = '/';
        $controller = new ErrorController(new Router('http://localhost'));

        ob_start();
        $controller->show(500, new RuntimeException('secret technical detail'));
        $output = (string) ob_get_clean();

        self::assertStringContainsString('Erro interno', $output);
        self::assertStringNotContainsString('secret technical detail', $output);
    }

    public function testExternalRedirectIsRejected(): void
    {
        $_ENV['APP_URL'] = 'http://mvs.lab';

        $this->expectException(\InvalidArgumentException::class);
        Response::redirect('https://attacker.invalid/phishing');
    }

    public function testHeaderInjectionRedirectIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Response::redirect("/app\r\nX-Injected: value");
    }

    public function testDynamicTemplateOutputIsEscaped(): void
    {
        $_SERVER['REQUEST_URI'] = '/';
        $view = new Engine(Theme::path());
        $view->share('theme', Theme::active());
        $view->registerFunction('asset', static fn (string $path): string => Theme::asset($path));
        $payload = '<script>alert(1)</script>';
        $output = $view->render('pages/home', [
            'title' => $payload,
            'description' => $payload,
        ]);

        self::assertStringNotContainsString($payload, $output);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $output);
    }

    public function testDevelopmentErrorShowsControlledDetails(): void
    {
        $_ENV['APP_DEBUG'] = 'true';
        $_SERVER['REQUEST_URI'] = '/';
        $controller = new ErrorController(new Router('http://localhost'));

        ob_start();
        $controller->show(500, new RuntimeException('development diagnostic'));
        $output = (string) ob_get_clean();

        self::assertStringContainsString('development diagnostic', $output);
    }
}
