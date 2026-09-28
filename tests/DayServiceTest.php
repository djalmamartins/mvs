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
        self::assertStringNotContainsString('João da Silva',$page);
    }
}