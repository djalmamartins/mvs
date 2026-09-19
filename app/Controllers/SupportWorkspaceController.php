<?php

declare(strict_types=1);

namespace Moves\Controllers;

use Moves\Core\Controller;
use Moves\Core\Request;
use Moves\Services\Support\WorkspaceService;

final class SupportWorkspaceController extends Controller
{
    public function dashboard(): void
    {
        $this->render('support-dashboard', 'Visão geral', 'dashboard', (new WorkspaceService())->dashboard());
    }

    public function inbox(): void { $this->structural('Caixa de entrada', 'inbox', 'Conversas recebidas serão organizadas aqui.', 'icon-mail-outline'); }
    public function myTickets(): void { $this->structural('Meus chamados', 'my-tickets', 'Os chamados atribuídos a você aparecerão aqui.', 'icon-headset-outline'); }
    public function tickets(): void { $this->structural('Todos os chamados', 'tickets', 'A operação completa de chamados será exibida aqui.', 'icon-chatbubbles-outline'); }
    public function sla(): void { $this->structural('Políticas de SLA', 'sla', 'As políticas e indicadores de atendimento serão exibidos quando o módulo de chamados estiver conectado.', 'icon-timer-outline'); }

    public function users(): void
    {
        $search = mb_substr(trim((string) Request::get('q', '')), 0, 100);
        $role = (string) Request::get('role', '');
        $status = (string) Request::get('status', '');
        $this->render('support-users', 'Usuários', 'users', [
            'users' => (new WorkspaceService())->users($search, $role, $status),
            'search' => $search, 'role' => $role, 'status' => $status,
        ]);
    }

    public function reports(): void
    {
        $this->render('support-reports', 'Relatórios', 'reports', (new WorkspaceService())->reports());
    }

    public function settings(): void
    {
        $this->render('support-settings', 'Configurações', 'settings');
    }

    private function structural(string $title, string $page, string $description, string $icon): void
    {
        $this->render('support-structural', $title, $page, compact('description', 'icon'));
    }

    private function render(string $view, string $title, string $currentPage, array $data = []): void
    {
        echo $this->view->render('pages/' . $view, array_merge($data, [
            'title' => $title, 'productName' => 'Suporte', 'activeProduct' => 'support', 'currentPage' => $currentPage,
        ]));
    }
}
