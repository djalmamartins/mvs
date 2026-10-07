<?php

declare(strict_types=1);

namespace Moves\Controllers;

use InvalidArgumentException;
use Moves\Core\Access;
use Moves\Core\Controller;
use Moves\Core\Csrf;
use Moves\Core\Flash;
use Moves\Core\HttpException;
use Moves\Core\Request;
use Moves\Core\Response;
use Moves\Modules\Erp\Security\ErpTenantContext;
use Moves\Services\Day\OperationalPendingService;
use Moves\Services\Platform\CompanyService;
use Moves\Services\Platform\CondominiumService;
use MovesCode\Router\Router;
use RuntimeException;
use Throwable;

final class ErpPendingController extends Controller
{
    public function __construct(Router $router) { parent::__construct($router); }

    public function index(): void
    {
        $context = ErpTenantContext::current();
        $filters = [
            'status' => in_array($_GET['status'] ?? '', ['pending','in_progress','done'], true) ? (string) $_GET['status'] : '',
            'priority' => in_array($_GET['priority'] ?? '', ['low','normal','high','urgent'], true) ? (string) $_GET['priority'] : '',
            'responsible' => (string) ($_GET['responsible'] ?? ''),
            'overdue' => ($_GET['overdue'] ?? '') === '1' ? '1' : '',
            'condominium_id' => filter_var($_GET['condominium_id'] ?? null, FILTER_VALIDATE_INT) ?: '',
        ];
        $service = new OperationalPendingService($context['pdo']);
        echo $this->view->render('pages/erp-pending', $this->base($context) + [
            'tasks' => $service->list($context['tenant_id'], $context['administrator_id'], $filters),
            'assignees' => $service->assignees($context['tenant_id'], $context['administrator_id']),
            'condominiums' => $service->condominiums($context['tenant_id'], $context['administrator_id']),
            'filters' => $filters,
        ]);
    }

    /** @param array<string,string> $route */
    public function show(array $route = []): void
    {
        $context = ErpTenantContext::current();
        $service = new OperationalPendingService($context['pdo']);
        $taskId = max(0, (int) ($route['task_id'] ?? 0));
        $task = $service->detail($context['tenant_id'], $context['administrator_id'], $taskId);
        if ($task === null) { throw new HttpException(404, 'Pendência não encontrada.'); }
        echo $this->view->render('pages/erp-pending-detail', $this->base($context) + [
            'task' => $task,
            'canViewCondominium' => (new CondominiumService($context['pdo']))->canView($context['tenant_id'], $context['administrator_id'], (int) $task['condominium_id'], $context['user_id']),
            'canEditCondominium' => (new CondominiumService($context['pdo']))->canEdit($context['tenant_id'], $context['administrator_id'], (int) $task['condominium_id'], $context['user_id']),
            'assignees' => $service->assignees($context['tenant_id'], $context['administrator_id'], (int) $task['condominium_id']),
            'history' => $service->history($context['tenant_id'], $taskId),
        ]);
    }

    /** @param array<string,string> $route */
    public function update(array $route = []): never
    {
        $context = ErpTenantContext::current();
        $taskId = max(0, (int) ($route['task_id'] ?? 0));
        if (!Request::isMethod('POST') || !Csrf::validate((string) Request::post('_token', ''))) {
            Flash::set('error', 'Sessão expirada. Atualize a página e tente novamente.');
            Response::to('/erp/pending/' . $taskId);
        }
        try {
            (new OperationalPendingService($context['pdo']))->update($context['tenant_id'], $context['administrator_id'], $taskId, $context['user_id'], $_POST);
            Flash::set('success', 'Pendência atualizada.');
        } catch (InvalidArgumentException|RuntimeException $exception) {
            Flash::set('error', $exception->getMessage());
        } catch (Throwable $exception) {
            error_log('[erp-pending] ' . $exception->getMessage());
            Flash::set('error', 'Não foi possível atualizar a pendência.');
        }
        Response::to('/erp/pending/' . $taskId);
    }

    /** @param array{pdo:\PDO,tenant_id:int,administrator_id:int,user_id:int} $context @return array<string,mixed> */
    private function base(array $context): array
    {
        return ['title' => 'Pendências operacionais', 'productName' => 'ERP', 'activeProduct' => 'erp', 'currentPage' => 'pending', 'company' => (new CompanyService($context['pdo']))->find($context['tenant_id'])];
    }
}
