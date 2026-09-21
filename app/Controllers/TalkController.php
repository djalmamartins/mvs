<?php

declare(strict_types=1);

namespace Moves\Controllers;

use Moves\Core\Controller;
use MovesCode\Router\Router;
use Moves\Services\Talk\TalkService;

/** Moves Talk workspace foundation. */
final class TalkController extends Controller
{
    private TalkService $talk;

    public function __construct(Router $router)
    {
        parent::__construct($router);
        $this->talk = new TalkService();
    }
    public function dashboard(): void { $this->page('Visão geral', 'dashboard', 'Acompanhe a operação do atendimento em um único lugar.', $this->talk->dashboard()); }
    public function queue(): void { $this->page('Fila', 'queue', 'Atendimentos aguardando um atendente elegível.', ['queue' => $this->talk->queue()]); }
    public function conversations(): void { $this->page('Conversas', 'conversations', 'Conversas ativas e seus respectivos atendimentos.', ['conversations' => $this->talk->conversations()]); }
    public function contacts(): void { $this->page('Contatos', 'contacts', 'Pessoas identificadas a partir dos canais conectados.', ['contacts' => $this->talk->contacts()]); }
    public function myTickets(): void { $this->page('Meus chamados', 'my-tickets', 'Atendimentos atualmente sob sua responsabilidade.'); }
    public function transfers(): void { $this->page('Transferências', 'transfers', 'Transferências recebidas, enviadas e pendentes.'); }
    public function history(): void { $this->page('Histórico', 'history', 'Atendimentos finalizados e histórico operacional.'); }
    public function jack(): void { $this->page('Interações do Jack', 'jack', 'Revise atendimentos, respostas e decisões do agente virtual.'); }
    public function jackSettings(): void { $this->page('Configuração do Jack', 'jack-settings', 'Defina quando e como o Jack participa do atendimento.'); }
    public function queues(): void { $this->page('Filas e departamentos', 'queues', 'Organize departamentos, filas e capacidade de atendimento.'); }
    public function users(): void { $this->page('Usuários e permissões', 'users', 'Gerencie atendentes, supervisores e permissões do Talk.'); }
    public function reports(): void { $this->page('Relatórios', 'reports', 'Indicadores de fila, atendimento, transferência e SLA.'); }
    public function settings(): void { $this->page('Configurações', 'settings', 'Configurações gerais, canais e regras do Talk.'); }

    private function page(string $title, string $currentPage, string $description, array $data = []): void
    {
        echo $this->view->render('pages/talk-workspace', array_merge([
            'title' => $title,
            'productName' => 'Talk',
            'activeProduct' => 'talk',
            'currentPage' => $currentPage,
            'description' => $description,
        ], $data));
    }
}
