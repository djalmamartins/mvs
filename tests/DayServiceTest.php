<?php
declare(strict_types=1);
namespace Moves\Tests;
use Moves\Services\Day\DayService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class DayServiceTest extends TestCase
{
    public function testServiceCanBeBoundToExplicitTenant(): void
    {
        $service=new DayService(91);
        $reflection=new ReflectionClass($service);
        $property=$reflection->getProperty('tenantId');
        self::assertSame(91,$property->getValue($service));
    }
    public function testDayPageHasNoProductionFixtureData(): void
    {
        $page=file_get_contents(dirname(__DIR__).'/resources/themes/admin/pages/day.php');
        self::assertIsString($page);
        self::assertStringContainsString('Nenhuma tarefa pendente',$page);
        self::assertStringContainsString('/talk/view/inbox?ticket=',$page);
        self::assertStringContainsString('Agenda de hoje',$page);
        self::assertStringContainsString('href="#day-tasks"',$page);
        self::assertStringContainsString('href="#day-agenda"',$page);
        self::assertStringContainsString('href="#day-talk"',$page);
        self::assertStringNotContainsString('João da Silva',$page);
    }
    public function testDayFoundationEnforcesTenantAndAssigneeInSql(): void
    {
        $service=file_get_contents(dirname(__DIR__).'/app/Services/Day/DayService.php');
        $migration=file_get_contents(dirname(__DIR__).'/database/migrations/20260927_014_create_day_foundation.sql');
        self::assertIsString($service);self::assertIsString($migration);
        self::assertStringContainsString('tenant_id=:tenant AND assigned_user_id=:user',$service);
        self::assertStringContainsString('WHERE id=:id AND tenant_id=:tenant AND assigned_user_id=:user',$service);
        self::assertStringContainsString('FOREIGN KEY (tenant_id) REFERENCES talk_tenants(id)',$migration);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS day_events',$migration);
        self::assertStringContainsString("FROM day_events WHERE tenant_id=:tenant AND assigned_user_id=:user",$service);
    }

    public function testTalkDeepLinkIsPermissionCheckedByTalkController(): void
    {
        $controller=file_get_contents(dirname(__DIR__).'/app/Controllers/TalkController.php');
        self::assertIsString($controller);
        self::assertStringContainsString("canViewTicket(\$selectedId, (int) \$user->id)",$controller);
        self::assertStringContainsString("selectedTicket'=>\$selected",$controller);
    }

    public function testSourceUrlsOnlyAllowInternalPaths(): void
    {
        self::assertSame('/talk/view/inbox?ticket=10',DayService::safeSourceUrl('/talk/view/inbox?ticket=10'));
        self::assertSame('/day',DayService::safeSourceUrl('javascript:alert(1)'));
        self::assertSame('/day',DayService::safeSourceUrl('https://example.com'));
        self::assertSame('/day',DayService::safeSourceUrl('//example.com'));
        self::assertSame('/day',DayService::safeSourceUrl("/talk\r\nLocation:https://example.com"));
        self::assertSame('/day',DayService::safeSourceUrl('/talk\\evil'));
    }

    public function testDayRoutesAndNavigationAreReal(): void
    {
        $routes=file_get_contents(dirname(__DIR__).'/app/Boot/Routes.php');
        $sidebar=file_get_contents(dirname(__DIR__).'/resources/themes/admin/components/product-sidebar.php');
        self::assertIsString($routes);self::assertIsString($sidebar);
        self::assertStringContainsString("DayController:index",$routes);
        self::assertStringContainsString("DayController:task",$routes);
        $controller=file_get_contents(dirname(__DIR__).'/app/Controllers/DayController.php');
        self::assertIsString($controller);
        self::assertStringContainsString('Csrf::validate',$controller);
        self::assertStringNotContainsString('requireCsrf',$controller);
        self::assertStringContainsString("\$activeProduct==='day'",$sidebar);
        self::assertStringNotContainsString('\\n',$sidebar);
    }
}