<?php

declare(strict_types=1);

namespace Moves\Controllers;

use Moves\Core\Controller;
use Moves\Core\Auth;
use Moves\Core\Csrf;
use Moves\Core\Request;
use Moves\Core\Response;
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
    public function queue(): void
    {
        if (Request::isMethod('POST')) {
            if (!Csrf::validate((string) Request::post('_token', ''))) {
                Response::json(['ok' => false, 'message' => 'Token inválido.'], 419);
            }
            $user = Auth::user();
            $ticketId = max(0, (int) Request::post('ticket_id', 0));
            if ($user === null || $ticketId === 0) {
                Response::to('/talk/queue?error=invalid');
            }
            $claimed = $this->talk->claim($ticketId, (int) $user->id);
            Response::to('/talk/queue?' . ($claimed ? 'claimed=1' : 'error=unavailable'));
        }

        $this->page('Fila', 'queue', 'Atendimentos aguardando um atendente elegível.', [
            'queue' => $this->talk->queue(),
        ]);
    }
    public function simulate(): never
    {
        $this->requireCsrf('/talk/queue');
        $user = Auth::user();
        if ($user === null) { Response::to('/login'); }
        $ticketId = $this->talk->seedSimulation((int) $user->id);
        Response::to('/talk/tickets/' . $ticketId);
    }

    /** @param array<string,string> $data */
    public function ticket(array $data = []): void
    {
        $id = max(0, (int)($data['id'] ?? 0));
        $ticket = $this->talk->ticket($id);
        if ($ticket === null) { Response::to('/talk/queue'); }

        if (Request::isMethod('POST')) {
            $this->requireCsrf('/talk/tickets/' . $id);
            $user = Auth::user();
            if ($user === null) { Response::to('/login'); }
            $action = (string) Request::post('action', '');
            if ($action === 'send') {
                $body = mb_substr(trim((string)Request::post('body', '')), 0, 4000);
                $this->talk->sendSimulationMessage($id, (int)$user->id, $body);
            }
            Response::to('/talk/tickets/' . $id);
        }

        echo $this->view->render('pages/talk-ticket', [
            'title' => 'Atendimento ' . (string)$ticket['protocol'],
            'productName' => 'Talk',
            'activeProduct' => 'talk',
            'currentPage' => 'my-tickets',
            'ticket' => $ticket,
        ]);
    }

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

    private function requireCsrf(string $fallback): void
    {
        if (!Csrf::validate((string) Request::post('_token', ''))) {
            Response::to($fallback . '?error=csrf');
        }
    }

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
