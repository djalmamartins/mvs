<?php

declare(strict_types=1);

namespace Moves\Controllers;

use InvalidArgumentException;
use Moves\Core\Controller;
use Moves\Core\Csrf;
use Moves\Core\Flash;
use Moves\Core\HttpException;
use Moves\Core\Request;
use Moves\Core\Response;
use Moves\Modules\Erp\Periods\PeriodAlreadyExists;
use Moves\Modules\Erp\Periods\PeriodRepository;
use Moves\Modules\Erp\Periods\PeriodService;
use Moves\Modules\Erp\Security\ErpTenantContext;
use Moves\Services\Platform\CompanyService;
use Moves\Services\Platform\PlatformAudit;
use Throwable;

final class ErpPeriodController extends Controller
{
    public function index(): void
    {
        $context = ErpTenantContext::current();
        $repository = new PeriodRepository($context['pdo']);
        $condominiumId = filter_var($_GET['condominium'] ?? null, FILTER_VALIDATE_INT);
        if ($condominiumId === false || $condominiumId < 1
            || !$repository->condominiumExists($context['administrator_id'], $condominiumId)) {
            $condominiumId = null;
        }
        $year = filter_var($_GET['year'] ?? null, FILTER_VALIDATE_INT);
        if ($year === false || $year < 2000 || $year > 2100) {
            $year = null;
        }
        $status = ($_GET['status'] ?? '') === 'open' ? 'open' : '';
        $filters = ['condominium_id' => $condominiumId, 'year' => $year, 'status' => $status];
        echo $this->view->render('pages/erp-periods', $this->base($context) + [
            'periods' => $repository->search($context['administrator_id'], $filters),
            'condominiums' => $repository->condominiums($context['administrator_id']),
            'filters' => $filters,
        ]);
    }

    public function new(): void
    {
        $context = ErpTenantContext::current();
        echo $this->view->render('pages/erp-period-form', $this->base($context) + [
            'condominiums' => (new PeriodRepository($context['pdo']))->condominiums($context['administrator_id']),
            'month' => (int) date('n'),
            'year' => (int) date('Y'),
        ]);
    }

    public function create(): never
    {
        $context = ErpTenantContext::current();
        $this->requireCsrf('/erp/periods/new');
        try {
            $service = new PeriodService($context['pdo'], new PeriodRepository($context['pdo']), new PlatformAudit($context['pdo']));
            $id = $service->create($context['tenant_id'], $context['administrator_id'], $context['user_id'], $_POST);
            Flash::set('success', 'Competência aberta cadastrada.');
            Response::to('/erp/periods/' . $id);
        } catch (PeriodAlreadyExists $exception) {
            Flash::set('error', $exception->getMessage());
            Response::to('/erp/periods/new');
        } catch (InvalidArgumentException $exception) {
            Flash::set('error', $exception->getMessage());
            Response::to('/erp/periods/new');
        } catch (Throwable) {
            Flash::set('error', 'Não foi possível cadastrar a competência. Verifique os dados e tente novamente.');
            Response::to('/erp/periods/new');
        }
    }

    /** @param array<string,string> $route */
    public function show(array $route = []): void
    {
        $context = ErpTenantContext::current();
        $id = max(0, (int) ($route['period_id'] ?? 0));
        $repository = new PeriodRepository($context['pdo']);
        $period = $repository->find($context['administrator_id'], $id);
        if ($period === null) {
            throw new HttpException(404, 'Competência não encontrada.');
        }
        $statement = $context['pdo']->prepare(
            "SELECT audit.actor_user_id,actor.name AS actor_name,audit.event_type,audit.created_at,audit.metadata
             FROM platform_audit_events audit LEFT JOIN users actor ON actor.id=audit.actor_user_id
             WHERE audit.tenant_id=:tenant_id AND audit.subject_type='accounting_period' AND audit.subject_id=:subject_id
             ORDER BY audit.id DESC LIMIT 20"
        );
        $statement->execute(['tenant_id' => $context['tenant_id'], 'subject_id' => $id]);
        echo $this->view->render('pages/erp-period-detail', $this->base($context) + [
            'period' => $period,
            'auditEvents' => array_values($statement->fetchAll(\PDO::FETCH_ASSOC)),
        ]);
    }

    /** @param array{pdo:\PDO,tenant_id:int,administrator_id:int,user_id:int} $context @return array<string,mixed> */
    private function base(array $context): array
    {
        return ['title' => 'Competências', 'productName' => 'ERP', 'activeProduct' => 'erp', 'currentPage' => 'periods', 'company' => (new CompanyService($context['pdo']))->find($context['tenant_id'])];
    }

    private function requireCsrf(string $fallback): void
    {
        if (!Request::isMethod('POST')) {
            Response::to($fallback);
        }
        if (!Csrf::validate((string) Request::post('_token', ''))) {
            Flash::set('error', 'Sessão expirada. Atualize a página e tente novamente.');
            Response::to($fallback);
        }
    }
}
