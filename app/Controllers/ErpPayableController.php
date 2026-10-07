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
use Moves\Modules\Erp\Payables\PayableRepository;
use Moves\Modules\Erp\Payables\PayableService;
use Moves\Modules\Erp\Security\ErpTenantContext;
use Moves\Services\Platform\CompanyService;
use Moves\Services\Platform\PlatformAudit;
use MovesCode\Router\Router;
use Throwable;

final class ErpPayableController extends Controller
{
    public function __construct(Router $router) { parent::__construct($router); }

    public function index(): void
    {
        $context = ErpTenantContext::current();
        $query = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
        echo $this->view->render('pages/erp-payables', $this->base($context) + [
            'payables' => $this->service($context['pdo'])->search($context['administrator_id'], ['query' => $query, 'condominium_id' => null]),
            'query' => $query,
        ]);
    }

    public function new(): void
    {
        $context = ErpTenantContext::current();
        echo $this->view->render('pages/erp-payable-form', $this->base($context) + $this->repository($context['pdo'])->options($context['administrator_id']));
    }

    public function create(): never
    {
        $context = ErpTenantContext::current();
        $this->requireCsrf('/erp/payables/new');
        try {
            $id = $this->service($context['pdo'])->create($context['tenant_id'], $context['administrator_id'], $context['user_id'], $_POST);
            Flash::set('success', 'Obrigação registrada com suas parcelas previstas.');
            Response::to('/erp/payables/' . $id);
        } catch (InvalidArgumentException $exception) {
            Flash::set('error', $exception->getMessage());
            Response::to('/erp/payables/new');
        } catch (Throwable) {
            Flash::set('error', 'Não foi possível registrar a obrigação. Verifique os dados e tente novamente.');
            Response::to('/erp/payables/new');
        }
    }

    /** @param array<string,string> $route */
    public function show(array $route = []): void
    {
        $context = ErpTenantContext::current();
        $payable = $this->service($context['pdo'])->detail($context['administrator_id'], max(0, (int) ($route['payable_id'] ?? 0)));
        if ($payable === null) {
            throw new HttpException(404, 'Obrigação não encontrada.');
        }
        $audit = $context['pdo']->prepare("SELECT e.actor_user_id,u.name AS actor_name,e.event_type,e.created_at,e.metadata FROM platform_audit_events e LEFT JOIN users u ON u.id=e.actor_user_id WHERE e.tenant_id=:tenant_id AND e.subject_type='payable' AND e.subject_id=:subject_id ORDER BY e.id DESC LIMIT 20");
        $audit->execute(['tenant_id' => $context['tenant_id'], 'subject_id' => (int) $payable['id']]);
        echo $this->view->render('pages/erp-payable-detail', $this->base($context) + ['payable' => $payable, 'auditEvents' => array_values($audit->fetchAll(\PDO::FETCH_ASSOC))]);
    }

    /** @param array{pdo:\PDO,tenant_id:int,administrator_id:int,user_id:int} $context @return array<string,mixed> */
    private function base(array $context): array
    {
        return ['title'=>'Contas a pagar','productName'=>'ERP','activeProduct'=>'erp','currentPage'=>'payables','company'=>(new CompanyService($context['pdo']))->find($context['tenant_id'])];
    }

    private function repository(\PDO $pdo): PayableRepository { return new PayableRepository($pdo); }
    private function service(\PDO $pdo): PayableService { return new PayableService($pdo, $this->repository($pdo), new PlatformAudit($pdo)); }

    private function requireCsrf(string $fallback): void
    {
        if (!Request::isMethod('POST')) Response::to($fallback);
        if (!Csrf::validate((string) Request::post('_token', ''))) {
            Flash::set('error', 'Sessão expirada. Atualize a página e tente novamente.');
            Response::to($fallback);
        }
    }
}
