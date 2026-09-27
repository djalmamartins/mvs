<?php

declare(strict_types=1);

namespace Moves\Controllers;

use Moves\Core\Controller;
use Moves\Boot\Connection;
use Moves\Core\Auth;
use Moves\Core\HttpException;
use Moves\Modules\Erp\Security\AdministratorTenantAccess;

/**
 * Moves Platform | Product entry points.
 */
final class PlatformController extends Controller
{
    public function day(): void
    {
        $this->renderProduct('Meu Dia', 'day');
    }

    public function talk(): void
    {
        $this->renderProduct('Talk', 'talk');
    }

    public function support(): void
    {
        $this->renderProduct('Suporte', 'support');
    }

    public function erp(): void
    {
        $user = Auth::user();
        if ($user === null || !(new AdministratorTenantAccess(Connection::getInstance()))->hasAnyAdministrator((int) $user->id)) {
            throw new HttpException(403, 'ERP indisponível para este usuário.');
        }
        $this->renderProduct('ERP', 'erp');
    }

    private function renderProduct(
        string $productName,
        string $activeProduct
    ): void {
        echo $this->view->render('pages/platform-placeholder', [
            'title' => 'Visão geral',
            'productName' => $productName,
            'activeProduct' => $activeProduct,
            'currentPage' => 'dashboard',
        ]);
    }
}
