<?php

declare(strict_types=1);

namespace Moves\Controllers;

use Moves\Core\Controller;

/**
 * Moves | Error Controller
 *
 * Gerencia a apresentação dos erros HTTP
 * ocorridos durante o fluxo da aplicação.
 *
 * @author Djalma Martins
 * @package Moves\Controllers
 */
final class ErrorController extends Controller
{
    public function show(int $code): void
    {
        if (!in_array($code, [404, 405, 500], true)) {
            $code = 500;
        }

        http_response_code($code);

        echo $this->view->render('pages/error', [
            'title' => 'Erro ' . $code,
            'code' => $code,
        ]);
    }
}