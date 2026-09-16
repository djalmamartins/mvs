<?php

declare(strict_types=1);

namespace Moves\Controllers;

use Moves\Core\Controller;

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