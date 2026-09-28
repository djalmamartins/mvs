<?php
declare(strict_types=1);

namespace Moves\Controllers;

use Moves\Core\Auth;
use Moves\Core\Controller;
use Moves\Core\Csrf;
use Moves\Core\Flash;
use Moves\Core\Request;
use Moves\Core\Response;
use Moves\Services\Support\TicketService;
use Throwable;

final class SupportTicketController extends Controller
{
    public function create(): void
    {
        echo $this->view->render('pages/support-ticket-create',[
            'title'=>'Novo chamado','productName'=>'Suporte','activeProduct'=>'support','currentPage'=>'tickets',
        ]);
    }

    public function store(): never
    {
        $user=Auth::user();
        if($user===null){Response::to('/login');}
        $token=Request::post('_token');
        if(!is_string($token)||!Csrf::validate($token)){Flash::set('error','Token de segurança inválido.');Response::to('/support/tickets/create');}
        try {
            $ticket=(new TicketService())->create((int)$user->id,[
                'subject'=>Request::post('subject',''),
                'description'=>Request::post('description',''),
                'priority'=>Request::post('priority','normal'),
                'requester_user_id'=>(int)$user->id,
            ]);
            Flash::set('success','Chamado criado com sucesso.');
            Response::to('/support/tickets/'.(int)$ticket['id']);
        } catch(Throwable $exception) {
            Flash::set('error',$exception->getMessage());
            Response::to('/support/tickets/create');
        }
    }

    /** @param array<string,string> $route */
    public function show(array $route=[]): void
    {
        $user=Auth::user();
        if($user===null){Response::to('/login');}
        try {
            $ticket=(new TicketService())->findForUser(max(0,(int)($route['id']??0)),(int)$user->id);
        } catch(Throwable $exception) {
            error_log('[support-ticket] '.$exception->getMessage());
            $ticket=null;
        }
        if($ticket===null){Flash::set('error','Chamado não encontrado ou sem permissão de acesso.');Response::to('/support/my-tickets');}
        echo $this->view->render('pages/support-ticket-show',[
            'title'=>(string)$ticket['protocol'],'productName'=>'Suporte','activeProduct'=>'support','currentPage'=>'tickets','ticket'=>$ticket,
        ]);
    }
}
