<?php

declare(strict_types=1);

namespace Moves\Controllers;

use Moves\Boot\Connection;
use Moves\Core\Auth;
use Moves\Core\Controller;
use Moves\Core\Csrf;
use Moves\Core\Flash;
use Moves\Core\Request;
use Moves\Core\Response;
use Moves\Services\Platform\CompanyService;
use Moves\Services\Platform\CondominiumService;
use Moves\Services\Platform\MemberService;
use Moves\Services\Platform\ProductEntitlement;
use Moves\Services\Platform\TenantContext;
use Throwable;

final class PlatformSettingsController extends Controller
{
    public function index(): void
    {
        [$pdo, $userId, $tenantId] = $this->context();
        $members = new MemberService($pdo);
        echo $this->view->render('pages/platform-settings', [
            'title' => 'Configurações da plataforma', 'activeProduct' => 'day', 'productName' => 'Moves', 'currentPage' => 'settings',
            'company' => (new CompanyService($pdo))->find($tenantId),
            'products' => (new ProductEntitlement($pdo))->all($tenantId),
            'members' => $members->all($tenantId), 'roles' => $members->roles($tenantId),
            'condominiums' => (new CondominiumService($pdo))->all($tenantId),
            'tenantId' => $tenantId, 'userId' => $userId,
        ]);
    }

    public function save(): void
    {
        if (!Csrf::validate((string) Request::post('_token', ''))) {
            Flash::set('error', 'Token de segurança inválido.'); Response::to('/settings');
        }
        [$pdo, $userId, $tenantId] = $this->context();
        try {
            $action = (string) Request::post('action', '');
            if ($action === 'company') {
                (new CompanyService($pdo))->update($tenantId, $_POST, $userId);
            } elseif ($action === 'products') {
                $selected = Request::post('products', []); $selected = is_array($selected) ? $selected : [];
                $products = new ProductEntitlement($pdo);
                foreach (ProductEntitlement::PRODUCTS as $product) { $products->set($tenantId, $product, in_array($product, $selected, true), $userId); }
            } elseif ($action === 'member') {
                (new MemberService($pdo))->add($tenantId, (string) Request::post('email', ''), (int) Request::post('role_id', 0), $userId);
            } elseif ($action === 'condominium') {
                (new CondominiumService($pdo))->save($tenantId, $_POST, $userId, (int) Request::post('id', 0) ?: null);
            } else {
                throw new \InvalidArgumentException('Ação inválida.');
            }
            Flash::set('success', 'Configuração salva.');
        } catch (Throwable $exception) {
            Flash::set('error', $exception instanceof \InvalidArgumentException ? $exception->getMessage() : 'Não foi possível salvar.');
        }
        Response::to('/settings');
    }

    /** @return array{0:\PDO,1:int,2:int} */
    private function context(): array
    {
        $user = Auth::user();
        if ($user === null) { Response::to('/login'); }
        $pdo = Connection::getInstance(); $userId = (int) $user->id;
        return [$pdo, $userId, (new TenantContext($pdo))->currentId($userId)];
    }
}
