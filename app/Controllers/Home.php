<?php

declare(strict_types=1);

namespace Moves\Controllers;

use Moves\Core\Controller;

/**
 * Moves | Home Controller
 *
 * Gerencia a página inicial da aplicação.
 *
 * @author Djalma Martins
 * @package Moves\Controllers
 */
final class Home extends Controller
{
    public function index(): void
    {
        echo $this->view->render('pages/home', [
            'title' => 'Moves',
            'description' => 'Moves application platform',
        ]);
    }

    /**
     * Exibe o painel inicial do usuário autenticado.
     */
    public function app(): void
    {
        echo $this->view->render('pages/home', [
            'title' => 'Minha aplicação',
            'description' => 'Área autenticada do Moves.',
        ]);
    }
}
