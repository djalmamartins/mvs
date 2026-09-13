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
        echo $this->view->render('pages/home', [
            'title' => 'Estratégia, Design e Tecnologia — MOVES',
            'description' => 'Moves: sites, sistemas web, identidade visual e automação para colocar ideias em movimento.',
            ...$this->pageMetadata('/'),
        ]);
    }

    public function services(): void
    {
        echo $this->view->render('pages/servicos', [
            'title' => 'Serviços — MOVES',
            'description' => 'Sites, sistemas web, aplicativos e hospedagem: conheça as quatro soluções da Moves.',
            ...$this->pageMetadata('/servicos'),
        ]);
    }

    public function projects(): void
    {
        echo $this->view->render('pages/projetos', [
            'title' => 'Projetos — MOVES',
            'description' => 'Apresentações de sites e projetos digitais com filtros por categoria.',
            ...$this->pageMetadata('/projetos'),
        ]);
    }

    public function about(): void
    {
        echo $this->view->render('pages/sobre', [
            'title' => 'Sobre a Moves — Estratégia, Design e Tecnologia',
            'description' => 'Conheça a Moves, uma equipe que conecta estratégia, design e tecnologia para transformar ideias em soluções digitais.',
            ...$this->pageMetadata('/sobre'),
        ]);
    }

    public function content(): void
    {
        echo $this->view->render('pages/conteudo', [
            'title' => 'Conteúdo — MOVES',
            'description' => 'Guias sobre planejamento de sites, automação e produtos digitais.',
            ...$this->pageMetadata('/conteudo'),
        ]);
    }

    public function contact(): void
    {
        echo $this->view->render('pages/contato', [
            'title' => 'Contato e solicitação de orçamento — MOVES',
            'description' => 'Conte seu projeto para a Moves e prepare sua solicitação de proposta em design e tecnologia.',
            ...$this->pageMetadata('/contato'),
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

    /**
     * @return array{canonical: ?string, robots: string}
     */
    private function pageMetadata(string $path): array
    {
        $applicationUrl = rtrim((string) Config::get('APP_URL', ''), '/');
        $canonical = null;

        if (Config::isProduction() && $applicationUrl !== '') {
            $canonical = $applicationUrl . ($path === '/' ? '/' : $path);
        }

        return [
            'canonical' => $canonical,
            'robots' => Config::isProduction() ? 'index, follow' : 'noindex, nofollow',
        ];
    }
}
