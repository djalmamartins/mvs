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
            $userId = (int) $user->id;
            if ($action === 'send') {
                $body = mb_substr(trim((string)Request::post('body', '')), 0, 4000);
                $this->talk->sendSimulationMessage($id, $userId, $body);
            } elseif ($action === 'note') {
                $this->talk->addNote($id, $userId, mb_substr(trim((string)Request::post('body', '')), 0, 4000));
            } elseif ($action === 'return_queue') {
                $this->talk->returnToQueue($id, $userId);
                Response::to('/talk/queue');
            } elseif ($action === 'close') {
                $this->talk->close($id, $userId);
                Response::to('/talk/history');
            } elseif ($action === 'reopen') {
                $this->talk->reopen($id, $userId);
                Response::to('/talk/queue');
            } elseif ($action === 'transfer') {
                $toUser = max(0, (int)Request::post('to_user_id', 0));
                $toQueue = max(0, (int)Request::post('to_queue_id', 0));
                $this->talk->transfer($id, $userId, $toUser > 0 ? $toUser : null, $toQueue > 0 ? $toQueue : null, mb_substr(trim((string)Request::post('reason','')),0,500));
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
    public function myTickets(): void
    {
        $user=Auth::user(); if($user===null){Response::to('/login');}
        $this->page('Meus chamados','my-tickets','Atendimentos atualmente sob sua responsabilidade.',['tickets'=>$this->talk->myTickets((int)$user->id)]);
    }
    public function transfers(): void { $this->page('Transferências','transfers','Transferências recebidas, enviadas e pendentes.',['transfers'=>$this->talk->transfers()]); }
    public function history(): void { $this->page('Histórico','history','Atendimentos finalizados e histórico operacional.',['tickets'=>$this->talk->history()]); }
    public function jack(): void { $this->page('Interações do Jack','jack','Revise atendimentos, respostas e decisões do agente virtual.',['jack_interactions'=>$this->talk->jackInteractions()]); }
    public function jackSettings(): void
    {
        $user=Auth::user(); if($user===null){Response::to('/login');}
        if(Request::isMethod('POST')){
            $this->requireCsrf('/talk/jack/settings');
            $this->talk->saveSettings([
                'jack.enabled'=>Request::post('jack_enabled')!==null?'1':'0',
                'jack.wait_seconds'=>(string)max(0,(int)Request::post('jack_wait_seconds',60)),
                'jack.transfer_summary'=>Request::post('jack_transfer_summary')!==null?'1':'0',
            ],(int)$user->id);
            Response::to('/talk/jack/settings?saved=1');
        }
        $this->page('Configuração do Jack','jack-settings','Defina quando e como o Jack participa do atendimento.',['settings'=>$this->talk->settings()]);
    }
    public function queues(): void { $this->page('Filas e departamentos','queues','Organize departamentos, filas e capacidade de atendimento.',['queues'=>$this->talk->queues()]); }
    public function users(): void { $this->page('Usuários e permissões','users','Gerencie atendentes, supervisores e permissões do Talk.',['users'=>$this->talk->eligibleUsers()]); }
    public function reports(): void { $this->page('Relatórios','reports','Indicadores de fila, atendimento, transferência e SLA.',['reports'=>$this->talk->reports()]); }
    public function settings(): void
    {
        $user=Auth::user(); if($user===null){Response::to('/login');}
        if(Request::isMethod('POST')){
            $this->requireCsrf('/talk/settings');
            $this->talk->saveSettings([
                'auto_assign.enabled'=>Request::post('auto_assign_enabled')!==null?'1':'0',
                'auto_assign.default_seconds'=>(string)max(5,(int)Request::post('auto_assign_default_seconds',30)),
            ],(int)$user->id);
            Response::to('/talk/settings?saved=1');
        }
        $this->page('Configurações','settings','Configurações gerais, canais e regras do Talk.',['settings'=>$this->talk->settings(),'channels'=>$this->talk->channels()]);
    }

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
