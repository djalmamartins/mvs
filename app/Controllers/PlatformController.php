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

        echo $this->view->render('pages/platform-erp', [
            'title' => 'Visão geral',
            'productName' => 'ERP',
            'activeProduct' => 'erp',
            'currentPage' => 'dashboard',
            'company' => (new CompanyService($pdo))->find($tenantId),
            'condominiums' => (new CondominiumService($pdo))->all($tenantId),
        ]);
    }
}
