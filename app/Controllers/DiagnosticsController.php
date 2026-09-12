<?php

declare(strict_types=1);

namespace Moves\Controllers;

use Moves\Core\Controller;
use Moves\Core\Diagnostics;

/**
 * Moves | Diagnostics Controller
 *
 * Exibe verificações seguras do ambiente administrativo.
 *
 * @author Djalma Martins
 * @package Moves\Controllers
 */
final class DiagnosticsController extends Controller
{
    public function index(): void
    {
        echo $this->view->render('pages/diagnostics', [
            'title' => 'Diagnóstico',
            'checks' => Diagnostics::run(),
        ]);
    }
}
