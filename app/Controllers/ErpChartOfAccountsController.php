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
use Moves\Modules\Erp\ChartOfAccounts\ChartOfAccountsRepository;
use Moves\Modules\Erp\ChartOfAccounts\ChartOfAccountsService;
use Moves\Modules\Erp\Security\ErpTenantContext;
use Moves\Services\Platform\CompanyService;
use Moves\Services\Platform\PlatformAudit;
use Throwable;

final class ErpChartOfAccountsController extends Controller
{
    public function index(): void
    {
        $context = ErpTenantContext::current();
        $repository = new ChartOfAccountsRepository($context['pdo']);
        $query = trim((string) ($_GET['q'] ?? ''));
        echo $this->view->render('pages/erp-chart-of-accounts', $this->base($context) + [
            'plans' => $repository->searchPlans($context['administrator_id'], mb_substr($query, 0, 100)),
            'condominiums' => $repository->condominiumsWithoutPlan($context['administrator_id']),
            'query' => mb_substr($query, 0, 100),
        ]);
    }

    public function newPlan(): void
    {
        $context = ErpTenantContext::current();
        echo $this->view->render('pages/erp-chart-plan-form', $this->base($context) + [
            'condominiums' => (new ChartOfAccountsRepository($context['pdo']))->condominiumsWithoutPlan($context['administrator_id']),
        ]);
    }

    public function createPlan(): never
    {
        $context = ErpTenantContext::current();
        $this->requireCsrf('/erp/chart-of-accounts/new');
        try {
            $id = $this->service($context)->createPlan($context['tenant_id'], $context['administrator_id'], $context['user_id'], $_POST);
            Flash::set('success', 'Plano de contas criado.');
            Response::to('/erp/chart-of-accounts/' . $id);
        } catch (InvalidArgumentException $exception) {
            Flash::set('error', $exception->getMessage());
        } catch (Throwable) {
            Flash::set('error', 'Não foi possível criar o plano de contas. Verifique os dados e tente novamente.');
        }
        Response::to('/erp/chart-of-accounts/new');
    }

    /** @param array<string,string> $route */
    public function show(array $route = []): void
    {
        $context = ErpTenantContext::current();
        $planId = max(0, (int) ($route['plan_id'] ?? 0));
        $repository = new ChartOfAccountsRepository($context['pdo']);
        $plan = $repository->findPlan($context['administrator_id'], $planId);
        if ($plan === null) {
            throw new HttpException(404, 'Plano de contas não encontrado.');
        }
        $query = trim(mb_substr((string) ($_GET['q'] ?? ''), 0, 100));
        $nature = in_array($_GET['nature'] ?? '', ['asset', 'liability', 'equity', 'revenue', 'expense'], true) ? (string) $_GET['nature'] : '';
        $status = in_array($_GET['status'] ?? '', ['active', 'inactive'], true) ? (string) $_GET['status'] : '';
        $filters = ['query' => $query, 'nature' => $nature, 'status' => $status];
        $allAccounts = $repository->searchAccounts($context['administrator_id'], $planId, ['query' => '', 'nature' => '', 'status' => '']);
        $visibleAccounts = $repository->searchAccounts($context['administrator_id'], $planId, $filters);
        $hierarchy = $this->treeOrder($allAccounts);
        $rank = [];
        foreach ($hierarchy as $position => $row) {
            $rank[(int) $row['id']] = $position;
        }
        usort($visibleAccounts, static fn (array $left, array $right): int => ($rank[(int) $left['id']] ?? PHP_INT_MAX) <=> ($rank[(int) $right['id']] ?? PHP_INT_MAX));
        echo $this->view->render('pages/erp-chart-plan-detail', $this->base($context) + [
            'plan' => $plan,
            'accounts' => $visibleAccounts,
            'filters' => $filters,
            'accountCount' => count($allAccounts),
        ]);
    }

    /** @param array<string,string> $route */
    public function newAccount(array $route = []): void
    {
        $context = ErpTenantContext::current();
        $planId = max(0, (int) ($route['plan_id'] ?? 0));
        $this->requirePlan($context, $planId);
        echo $this->view->render('pages/erp-chart-account-form', $this->base($context) + [
            'planId' => $planId, 'account' => null,
            'parents' => (new ChartOfAccountsRepository($context['pdo']))->parentOptions($context['administrator_id'], $planId),
        ]);
    }

    /** @param array<string,string> $route */
    public function createAccount(array $route = []): never
    {
        $context = ErpTenantContext::current();
        $planId = max(0, (int) ($route['plan_id'] ?? 0));
        $fallback = '/erp/chart-of-accounts/' . $planId . '/accounts/new';
        $this->requireCsrf($fallback);
        try {
            $id = $this->service($context)->createAccount($context['tenant_id'], $context['administrator_id'], $context['user_id'], $planId, $_POST);
            Flash::set('success', 'Conta criada no plano.');
            Response::to('/erp/chart-of-accounts/' . $planId . '/accounts/' . $id);
        } catch (InvalidArgumentException $exception) {
            Flash::set('error', $exception->getMessage());
        } catch (Throwable) {
            Flash::set('error', 'Não foi possível criar a conta. Verifique os dados e tente novamente.');
        }
        Response::to($fallback);
    }

    /** @param array<string,string> $route */
    public function account(array $route = []): void
    {
        $context = ErpTenantContext::current();
        [$planId, $accountId, $plan, $account] = $this->resolveAccount($context, $route);
        $statement = $context['pdo']->prepare(
            "SELECT audit.actor_user_id,actor.name AS actor_name,audit.event_type,audit.created_at,audit.metadata
             FROM platform_audit_events audit LEFT JOIN users actor ON actor.id=audit.actor_user_id
             WHERE audit.tenant_id=:tenant_id AND audit.subject_type='accounting_account' AND audit.subject_id=:subject_id
             ORDER BY audit.id DESC LIMIT 30"
        );
        $statement->execute(['tenant_id' => $context['tenant_id'], 'subject_id' => $accountId]);
        echo $this->view->render('pages/erp-chart-account-detail', $this->base($context) + [
            'planId' => $planId, 'plan' => $plan, 'account' => $account,
            'auditEvents' => array_values($statement->fetchAll(\PDO::FETCH_ASSOC)),
        ]);
    }

    /** @param array<string,string> $route */
    public function editAccount(array $route = []): void
    {
        $context = ErpTenantContext::current();
        [$planId, $accountId, $plan, $account] = $this->resolveAccount($context, $route);
        echo $this->view->render('pages/erp-chart-account-form', $this->base($context) + [
            'planId' => $planId, 'account' => $account,
            'parents' => (new ChartOfAccountsRepository($context['pdo']))->parentOptions($context['administrator_id'], $planId, $accountId),
        ]);
    }

    /** @param array<string,string> $route */
    public function updateAccount(array $route = []): never
    {
        $context = ErpTenantContext::current();
        $planId = max(0, (int) ($route['plan_id'] ?? 0));
        $accountId = max(0, (int) ($route['account_id'] ?? 0));
        $fallback = '/erp/chart-of-accounts/' . $planId . '/accounts/' . $accountId . '/edit';
        $this->requireCsrf($fallback);
        try {
            $this->service($context)->updateAccount($context['tenant_id'], $context['administrator_id'], $context['user_id'], $planId, $accountId, $_POST);
            Flash::set('success', 'Conta atualizada.');
            Response::to('/erp/chart-of-accounts/' . $planId . '/accounts/' . $accountId);
        } catch (InvalidArgumentException $exception) {
            Flash::set('error', $exception->getMessage());
        } catch (Throwable) {
            Flash::set('error', 'Não foi possível atualizar a conta. Verifique os dados e tente novamente.');
        }
        Response::to($fallback);
    }

    /** @param array<string,mixed> $context */
    private function service(array $context): ChartOfAccountsService
    {
        return new ChartOfAccountsService($context['pdo'], new ChartOfAccountsRepository($context['pdo']), new PlatformAudit($context['pdo']));
    }

    /** @param array<string,mixed> $context @param array<string,string> $route @return array{int,int,array<string,mixed>,array<string,mixed>} */
    private function resolveAccount(array $context, array $route): array
    {
        $planId = max(0, (int) ($route['plan_id'] ?? 0));
        $accountId = max(0, (int) ($route['account_id'] ?? 0));
        $repository = new ChartOfAccountsRepository($context['pdo']);
        $plan = $repository->findPlan($context['administrator_id'], $planId);
        $account = $repository->findAccount($context['administrator_id'], $planId, $accountId);
        if ($plan === null || $account === null) {
            throw new HttpException(404, 'Conta não encontrada neste plano.');
        }
        return [$planId, $accountId, $plan, $account];
    }

    /** @param array<string,mixed> $context */
    private function requirePlan(array $context, int $planId): void
    {
        if (!(new ChartOfAccountsRepository($context['pdo']))->planExists($context['administrator_id'], $planId)) {
            throw new HttpException(404, 'Plano de contas não encontrado.');
        }
    }

    /** @param list<array<string,mixed>> $accounts @return list<array<string,mixed>> */
    private function treeOrder(array $accounts): array
    {
        $children = [];
        foreach ($accounts as $account) {
            $parentId = $account['parent_id'] === null ? 0 : (int) $account['parent_id'];
            $children[$parentId][] = $account;
        }
        foreach ($children as &$siblings) {
            usort($siblings, static fn (array $left, array $right): int => ((int) $left['sort_order'] <=> (int) $right['sort_order'])
                ?: (strcmp((string) $left['code'], (string) $right['code']))
                ?: ((int) $left['id'] <=> (int) $right['id']));
        }
        unset($siblings);
        $ordered = [];
        $walk = function (int $parentId) use (&$walk, &$children, &$ordered): void {
            foreach ($children[$parentId] ?? [] as $account) {
                $ordered[] = $account;
                $walk((int) $account['id']);
            }
        };
        $walk(0);
        return $ordered;
    }

    /** @param array{pdo:\PDO,tenant_id:int,administrator_id:int,user_id:int} $context @return array<string,mixed> */
    private function base(array $context): array
    {
        return ['title' => 'Plano de Contas', 'productName' => 'ERP', 'activeProduct' => 'erp', 'currentPage' => 'chart-of-accounts', 'company' => (new CompanyService($context['pdo']))->find($context['tenant_id'])];
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
