<?php

declare(strict_types=1);

use Moves\Boot\Modules;
use Moves\Controllers\ErrorController;
use Moves\Core\Config;
use Moves\Core\LogReader;
use Moves\Core\Theme;
use Moves\Core\Validator;
use Moves\Core\Response;
use Moves\Core\Seo;
use Moves\Core\HtmlSanitizer;
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
        $_SERVER['REQUEST_URI'] = '/studio/settings';
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

    public function testLogReaderFiltersAndRedactsSensitiveContext(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'moves-log-');
        self::assertIsString($file);
        file_put_contents($file, implode(PHP_EOL, [
            json_encode(['timestamp' => '2026-09-12T10:00:00-03:00', 'level' => 'info', 'message' => 'Login aceito', 'context' => ['user_id' => 7, 'token' => 'secret']]),
            json_encode(['timestamp' => '2026-09-12T10:01:00-03:00', 'level' => 'error', 'message' => 'Falha controlada', 'context' => ['nested' => ['password' => 'secret'], 'file' => '/private/path']]),
        ]) . PHP_EOL);

        $result = (new LogReader($file))->read('Falha', 'error');
        unlink($file);

        self::assertSame(1, $result['total']);
        self::assertSame('[REDACTED]', $result['entries'][0]['context']['nested']['password']);
        self::assertSame('[REDACTED]', $result['entries'][0]['context']['file']);
        self::assertStringNotContainsString('secret', json_encode($result));
        self::assertStringNotContainsString('/private/path', json_encode($result));
    }

    public function testSeoGeneratesEditorialFieldsAutomatically(): void
    {
        $seo = Seo::contentFields(
            'Criação de Sites em Minas Gerais',
            '',
            '<p>Uma descrição clara para pessoas e mecanismos de busca.</p>'
        );

        self::assertSame('criacao-de-sites-em-minas-gerais', $seo['slug']);
        self::assertSame('Criação de Sites em Minas Gerais', $seo['title']);
        self::assertSame('Uma descrição clara para pessoas e mecanismos de busca.', $seo['description']);
    }

    public function testSeoPreservesManualOverridesWithinSafeLimits(): void
    {
        $seo = Seo::contentFields('Título original', 'Resumo', 'Conteúdo', 'url-manual', 'Título personalizado', str_repeat('a', 200));

        self::assertSame('url-manual', $seo['slug']);
        self::assertSame('Título personalizado', $seo['title']);
        self::assertSame(160, mb_strlen($seo['description']));
    }

    public function testRichTextSanitizerKeepsFormattingAndRejectsExecutableMarkup(): void
    {
        $html = HtmlSanitizer::clean('<h2>Título</h2><p><strong>Texto</strong><script>alert(1)</script><img src="/media/7" onerror="alert(2)"></p>');

        self::assertStringContainsString('<h2>Título</h2>', $html);
        self::assertStringContainsString('<strong>Texto</strong>', $html);
        self::assertStringContainsString('src="/media/7"', $html);
        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('onerror', $html);
    }
}
