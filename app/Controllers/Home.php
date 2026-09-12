<?php

declare(strict_types=1);

namespace Moves\Controllers;

use Moves\Core\Config;
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
        $applicationUrl = rtrim((string) Config::get('APP_URL', ''), '/');
        $canonical = null;

        if (Config::isProduction() && $applicationUrl !== '') {
            $canonical = $applicationUrl . '/';
        }

        echo $this->view->render('pages/home', [
            'title' => 'Estratégia, Design e Tecnologia — MOVES',
            'description' => 'Moves: sites, sistemas web, identidade visual e automação para colocar ideias em movimento.',
            'canonical' => $canonical,
            'robots' => Config::isProduction() ? 'index, follow' : 'noindex, nofollow',
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
