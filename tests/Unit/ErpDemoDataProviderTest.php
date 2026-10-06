<?php

declare(strict_types=1);

use Moves\Services\Platform\ErpDemoDataProvider;
use PHPUnit\Framework\TestCase;

final class ErpDemoDataProviderTest extends TestCase
{
    /** @var array<string,mixed> */
    private array $previousEnv = [];

    protected function setUp(): void
    {
        foreach (['APP_ENV', 'ERP_DEMO_DATA_ENABLED'] as $key) {
            $this->previousEnv[$key] = $_ENV[$key] ?? getenv($key);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->previousEnv as $key => $value) {
            if ($value === false) {
                unset($_ENV[$key]);
                putenv($key);
            } else {
                $_ENV[$key] = $value;
                putenv($key . '=' . (string) $value);
            }
        }
    }

    public function testDemoIsNeverEnabledInProductionEvenWhenFlagIsOn(): void
    {
        $_ENV['APP_ENV'] = 'production';
        $_ENV['ERP_DEMO_DATA_ENABLED'] = '1';

        self::assertFalse(ErpDemoDataProvider::enabled());
    }

    public function testDevelopmentDemoCanBeDisabledExplicitly(): void
    {
        $_ENV['APP_ENV'] = 'development';
        $_ENV['ERP_DEMO_DATA_ENABLED'] = '0';

        self::assertFalse(ErpDemoDataProvider::enabled());
    }

    public function testDevelopmentDemoIsAvailableByDefault(): void
    {
        $_ENV['APP_ENV'] = 'development';
        unset($_ENV['ERP_DEMO_DATA_ENABLED']);
        putenv('ERP_DEMO_DATA_ENABLED');

        self::assertTrue(ErpDemoDataProvider::enabled());
    }

    public function testDataRowsUseOnlyTheCurrentTenantCondominiumNames(): void
    {
        $tenantCondominiums = [
            ['id'=>42, 'legal_name'=>'Residencial Tenant A', 'trade_name'=>'Residencial A', 'tax_id'=>'', 'status'=>'active'],
        ];

        $page = ErpDemoDataProvider::page('payables', $tenantCondominiums);
        $names = array_column($page['rows'], 'condominium');

        self::assertContains('Residencial A', $names);
        self::assertNotContains('Condomínio do Tenant B', $names);
        self::assertSame('R$ 4.820,35', $page['rows'][0]['amount']);
    }

    public function testAllInitialOperationalScreensHaveRealisticDemoRowsAndColumns(): void
    {
        $sections = ['payables','receivables','billing','condominiums','people','units','bank-accounts','reconciliation'];
        foreach ($sections as $section) {
            $page = ErpDemoDataProvider::page($section, []);
            self::assertNotSame('', $page['title'], $section);
            self::assertNotEmpty($page['columns'], $section);
            self::assertNotEmpty($page['rows'], $section);
        }
    }

    public function testDashboardCashflowChangesWithTheSelectedPeriod(): void
    {
        $week = ErpDemoDataProvider::dashboard([], '7');
        $month = ErpDemoDataProvider::dashboard([], '30');
        $calendarMonth = ErpDemoDataProvider::dashboard([], 'month');

        self::assertSame('7 dias', $week['period_label']);
        self::assertCount(7, $week['cashflow']);
        self::assertSame('R$ 89.800,00', $week['inflows']);
        self::assertSame('30 dias', $month['period_label']);
        self::assertCount(6, $month['cashflow']);
        self::assertSame('R$ 302.000,00', $month['inflows']);
        self::assertSame('este mês', $calendarMonth['period_label']);
        self::assertCount(6, $calendarMonth['cashflow']);
        self::assertSame('7 dias', ErpDemoDataProvider::dashboard([], 'invalid')['period_label']);
    }
}
