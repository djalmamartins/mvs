<?php

declare(strict_types=1);

namespace Moves\Controllers;

use Moves\Core\Controller;
use Moves\Boot\Connection;
use Moves\Core\Auth;
use Moves\Core\HttpException;
use Moves\Core\Session;
use Moves\Modules\Erp\Security\AdministratorTenantAccess;
use Moves\Services\Platform\CompanyService;
use Moves\Services\Platform\CondominiumService;
use Moves\Services\Platform\ErpDemoDataProvider;
use Moves\Services\Platform\MemberService;
use Moves\Services\Platform\ProductEntitlement;
use Moves\Services\Platform\TenantContext;

/**
 * Moves Platform | Product entry points.
 */
final class PlatformController extends Controller
{
    public function day(): void
    {
        $user = Auth::user();
        if ($user === null) {
            throw new HttpException(401, 'Autenticação necessária.');
        }

        $company = null;
        $products = array_fill_keys(ProductEntitlement::PRODUCTS, false);
        $members = [];
        $condominiums = [];
        try {
            $pdo = Connection::getInstance();
            $tenantId = (new TenantContext($pdo))->currentId((int) $user->id);
            $company = (new CompanyService($pdo))->find($tenantId);
            $products = (new ProductEntitlement($pdo))->all($tenantId);
            $members = (new MemberService($pdo))->all($tenantId);
            $condominiums = (new CondominiumService($pdo))->all($tenantId);
        } catch (HttpException) {
            // Operators without a tenant receive an actionable empty state.
        }

        echo $this->view->render('pages/platform-day', [
            'title' => 'Meu Dia',
            'productName' => 'Meu Dia',
            'activeProduct' => 'day',
            'currentPage' => 'dashboard',
            'user' => $user,
            'company' => $company,
            'products' => $products,
            'members' => $members,
            'condominiums' => $condominiums,
            'mfaRecommendation' => Session::get(MfaController::RECOMMENDATION_KEY) === true,
        ]);
    }

    public function erp(): void
    {
        $user = Auth::user();
        if ($user === null) {
            throw new HttpException(401, 'Autenticação necessária.');
        }
        $pdo = Connection::getInstance();
        $tenantId = (new TenantContext($pdo))->currentId((int) $user->id);
        if (!(new AdministratorTenantAccess($pdo))->hasAdministrator((int) $user->id, $tenantId)) {
            throw new HttpException(403, 'ERP indisponível para este usuário.');
        }

        $condominiums = (new CondominiumService($pdo))->all($tenantId);
        $selectedCondominiumId = max(0, (int) ($_GET['condominium_id'] ?? 0));
        if ($selectedCondominiumId > 0 && !in_array($selectedCondominiumId, array_map(
            static fn (array $condominium): int => (int) $condominium['id'],
            $condominiums
        ), true)) {
            $selectedCondominiumId = 0;
        }
        $selectedCondominium = null;
        foreach ($condominiums as $condominium) {
            if ((int) $condominium['id'] === $selectedCondominiumId) {
                $selectedCondominium = $condominium;
                break;
            }
        }
        $demoEnabled = ErpDemoDataProvider::enabled();
        $selectedPeriod = (string) ($_GET['period'] ?? '7');
        if (!in_array($selectedPeriod, ['7', '30', 'month'], true)) {
            $selectedPeriod = '7';
        }
        $demo = $demoEnabled ? ErpDemoDataProvider::dashboard($condominiums, $selectedPeriod) : null;

        echo $this->view->render('pages/platform-erp', [
            'title' => 'Visão geral',
            'productName' => 'ERP',
            'activeProduct' => 'erp',
            'currentPage' => 'dashboard',
            'company' => (new CompanyService($pdo))->find($tenantId),
            'condominiums' => $condominiums,
            'selectedCondominiumId' => $selectedCondominiumId,
            'selectedCondominium' => $selectedCondominium,
            'selectedPeriod' => $selectedPeriod,
            'demoEnabled' => $demoEnabled,
            'demo' => $demo,
        ]);
    }

    /** Render the ERP's navigable visual foundation without persisting demo data. */
    public function erpPage(): void
    {
        $user = Auth::user();
        if ($user === null) {
            throw new HttpException(401, 'Autenticação necessária.');
        }
        $pdo = Connection::getInstance();
        $tenantId = (new TenantContext($pdo))->currentId((int) $user->id);
        if (!(new AdministratorTenantAccess($pdo))->hasAdministrator((int) $user->id, $tenantId)) {
            throw new HttpException(403, 'ERP indisponível para este usuário.');
        }

        $section = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
        $section = substr($section, strlen('erp/'));
        $allowed = ['payables', 'receivables', 'billing', 'condominiums', 'people', 'units', 'bank-accounts', 'reconciliation'];
        if (!in_array($section, $allowed, true)) {
            throw new HttpException(404, 'Página ERP não encontrada.');
        }

        $condominiums = (new CondominiumService($pdo))->all($tenantId);
        $demoEnabled = ErpDemoDataProvider::enabled();
        $filters = [
            'q' => mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100),
            'status' => mb_substr(trim((string) ($_GET['status'] ?? '')), 0, 40),
            'condominium' => mb_substr(trim((string) ($_GET['condominium'] ?? '')), 0, 160),
            'supplier' => mb_substr(trim((string) ($_GET['supplier'] ?? '')), 0, 100),
            'period' => mb_substr(trim((string) ($_GET['period'] ?? '30')), 0, 12),
        ];
        $page = $demoEnabled ? ErpDemoDataProvider::page($section, $condominiums) : ($section === 'condominiums' ? ErpDemoDataProvider::realCondominiums($condominiums) : [
            'title' => match ($section) {
                'payables' => 'Contas a pagar', 'receivables' => 'Contas a receber', 'billing' => 'Cobranças',
                'people' => 'Pessoas', 'units' => 'Unidades',
                'bank-accounts' => 'Contas bancárias', default => 'Conciliação',
            },
            'description' => 'Esta tela será conectada aos serviços do ERP quando os respectivos fluxos estiverem disponíveis.',
            'columns' => [], 'rows' => [], 'filters' => [], 'action' => null,
        ]);
        $filterOptions = [
            'condominiums' => array_values(array_unique(array_filter(array_column($page['rows'], 'condominium')))),
            'suppliers' => array_values(array_unique(array_filter(array_column($page['rows'], 'supplier')))),
            'statuses' => array_values(array_unique(array_filter(array_column($page['rows'], 'status')))),
        ];
        $page['rows'] = $this->filterErpRows($page['rows'], $filters);

        echo $this->view->render('pages/platform-erp-list', [
            'title' => $page['title'], 'productName' => 'ERP', 'activeProduct' => 'erp',
            'currentPage' => $section, 'section' => $section, 'page' => $page,
            'filters' => $filters, 'filterOptions' => $filterOptions, 'condominiums' => $condominiums, 'demoEnabled' => $demoEnabled,
            'company' => (new CompanyService($pdo))->find($tenantId),
        ]);
    }

    /** @param list<array<string,string>> $rows
     *  @param array{q:string,status:string,condominium:string,supplier:string,period:string} $filters
     *  @return list<array<string,string>>
     */
    private function filterErpRows(array $rows, array $filters): array
    {
        return array_values(array_filter($rows, static function (array $row) use ($filters): bool {
            $haystack = mb_strtolower(implode(' ', $row));
            if ($filters['q'] !== '' && !str_contains($haystack, mb_strtolower($filters['q']))) {
                return false;
            }
            if ($filters['status'] !== '' && mb_strtolower($row['status'] ?? '') !== mb_strtolower($filters['status'])) {
                return false;
            }
            if ($filters['condominium'] !== '' && ($row['condominium'] ?? '') !== $filters['condominium']) {
                return false;
            }
            if ($filters['supplier'] !== '' && ($row['supplier'] ?? '') !== $filters['supplier']) {
                return false;
            }
            return self::matchesErpPeriod($row, $filters['period']);
        }));
    }

    /** @param array<string,string> $row */
    private static function matchesErpPeriod(array $row, string $period): bool
    {
        $dateText = $row['due'] ?? $row['date'] ?? '';
        if ($dateText === '' || !preg_match('/(\d{1,2})\s+(jan|fev|mar|abr|mai|jun|jul|ago|set|out|nov|dez)/iu', $dateText, $matches)) {
            return true;
        }
        $months = ['jan'=>1,'fev'=>2,'mar'=>3,'abr'=>4,'mai'=>5,'jun'=>6,'jul'=>7,'ago'=>8,'set'=>9,'out'=>10,'nov'=>11,'dez'=>12];
        $monthKey = mb_strtolower($matches[2]);
        $month = $months[$monthKey] ?? (int) date('n');
        $dueDate = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', (int) date('Y'), $month, (int) $matches[1]));
        $today = new \DateTimeImmutable('today');
        if ($period === 'month') {
            return (int) $dueDate->format('n') === (int) $today->format('n');
        }
        $window = $period === '7' ? 7 : 30;
        $days = (int) $today->diff($dueDate)->format('%r%a');
        return $days >= -$window && $days <= $window;
    }
}
