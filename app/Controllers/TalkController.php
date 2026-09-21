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
use Moves\Services\Talk\TalkMetadataService;

final class TalkController extends Controller
{
    private TalkService $talk;
    private TalkMetadataService $metadata;

    public function __construct(Router $router)
    {
        parent::__construct($router);
        $this->talk = new TalkService();
        $this->metadata = new TalkMetadataService();
    }

    public function dashboard(): void
    {
        $user=Auth::user(); if($user!==null){$this->talk->heartbeat((int)$user->id);$this->talk->autoAssign();}
        $this->page('Visão geral','dashboard','Acompanhe a operação do atendimento em um único lugar.',$this->talk->dashboard());
    }

    public function queue(): void
    {
        if (Request::isMethod('POST')) {
            $this->requireCsrf('/talk/queue');
            $user=Auth::user(); $ticketId=max(0,(int)Request::post('ticket_id',0));
            if($user===null || $ticketId===0){Response::to('/talk/queue?error=invalid');}
            $claimed=$this->talk->claim($ticketId,(int)$user->id);
            Response::to('/talk/queue?'.($claimed?'claimed=1':'error=unavailable'));
        }
        $filters=$this->filters();
        $filters['status']='queued';
        $this->page('Fila','queue','Atendimentos aguardando um atendente elegível.',[
            'queue'=>$this->metadata->search($filters), 'filters'=>$filters,
            'queues'=>$this->talk->queues(),'tags'=>$this->metadata->tags(),
        ]);
    }

    public function simulate(): never
    {
        $this->requireCsrf('/talk/queue'); $user=Auth::user(); if($user===null){Response::to('/login');}
        Response::to('/talk/tickets/'.$this->talk->seedSimulation((int)$user->id));
    }

    /** @param array<string,string> $data */
    public function ticket(array $data=[]): void
    {
        $id=max(0,(int)($data['id']??0)); $ticket=$this->talk->ticket($id);
        if($ticket===null){Response::to('/talk/queue');}
        $viewer=Auth::user(); if($viewer===null || !$this->talk->canViewTicket($id,(int)$viewer->id)){Response::to('/talk/my-tickets?error=forbidden');}
        if(Request::isMethod('POST')){
            $this->requireCsrf('/talk/tickets/'.$id); $user=Auth::user(); if($user===null){Response::to('/login');}
            $action=(string)Request::post('action',''); $userId=(int)$user->id;
            if($action==='send'){$this->talk->sendSimulationMessage($id,$userId,mb_substr(trim((string)Request::post('body','')),0,4000));}
            elseif($action==='note'){$this->talk->addNote($id,$userId,mb_substr(trim((string)Request::post('body','')),0,4000));}
            elseif($action==='priority'){$this->metadata->updatePriority($id,$userId,(string)Request::post('priority','normal'));}
            elseif($action==='create_tag'){$tagId=$this->metadata->createTag((string)Request::post('tag_name',''));$this->metadata->attachTag($id,$tagId,$userId);}
            elseif($action==='attach_tag'){$tagId=max(1,(int)Request::post('tag_id',0));$this->metadata->attachTag($id,$tagId,$userId);}
            elseif($action==='detach_tag'){$tagId=max(1,(int)Request::post('tag_id',0));$this->metadata->detachTag($id,$tagId,$userId);}
            elseif($action==='return_queue'){$this->talk->returnToQueue($id,$userId);Response::to('/talk/queue');}
            elseif($action==='close'){$this->talk->close($id,$userId);Response::to('/talk/history');}
            elseif($action==='reopen'){$this->talk->reopen($id,$userId);Response::to('/talk/queue');}
            elseif($action==='transfer'){$toUser=max(0,(int)Request::post('to_user_id',0));$toQueue=max(0,(int)Request::post('to_queue_id',0));$this->talk->transfer($id,$userId,$toUser>0?$toUser:null,$toQueue>0?$toQueue:null,mb_substr(trim((string)Request::post('reason','')),0,500));}
            Response::to('/talk/tickets/'.$id);
        }
        $ticket['tags']=$this->metadata->ticketTags($id); $ticket['available_tags']=$this->metadata->tags();
        echo $this->view->render('pages/talk-ticket',['title'=>'Atendimento '.(string)$ticket['protocol'],'productName'=>'Talk','activeProduct'=>'talk','currentPage'=>'my-tickets','ticket'=>$ticket]);
    }

    public function conversations(): void{$this->page('Conversas','conversations','Conversas ativas e seus respectivos atendimentos.',['conversations'=>$this->talk->conversations()]);}
    public function contacts(): void{$this->page('Contatos','contacts','Pessoas identificadas a partir dos canais conectados.',['contacts'=>$this->talk->contacts()]);}
    public function myTickets(): void
    {
        $user=Auth::user(); if($user===null){Response::to('/login');} $filters=$this->filters();
        $this->page('Meus chamados','my-tickets','Atendimentos atualmente sob sua responsabilidade.',['tickets'=>$this->metadata->search($filters,(int)$user->id),'filters'=>$filters,'queues'=>$this->talk->queues(),'tags'=>$this->metadata->tags()]);
    }
    public function transfers(): void{$this->page('Transferências','transfers','Transferências recebidas, enviadas e pendentes.',['transfers'=>$this->talk->transfers()]);}
    public function history(): void
    {
        $filters=$this->filters(); $filters['status']='closed';
        $this->page('Histórico','history','Atendimentos finalizados e histórico operacional.',['tickets'=>$this->metadata->search($filters),'filters'=>$filters,'queues'=>$this->talk->queues(),'tags'=>$this->metadata->tags()]);
    }
    public function jack(): void{$this->page('Interações do Jack','jack','Revise atendimentos, respostas e decisões do agente virtual.',['jack_interactions'=>$this->talk->jackInteractions()]);}
    public function jackSettings(): void
    {
        $user=Auth::user();if($user===null){Response::to('/login');}
        if(Request::isMethod('POST')){$this->requireCsrf('/talk/jack/settings');$this->talk->saveSettings(['jack.enabled'=>Request::post('jack_enabled')!==null?'1':'0','jack.wait_seconds'=>(string)max(0,(int)Request::post('jack_wait_seconds',60)),'jack.transfer_summary'=>Request::post('jack_transfer_summary')!==null?'1':'0'],(int)$user->id);Response::to('/talk/jack/settings?saved=1');}
        $this->page('Configuração do Jack','jack-settings','Defina quando e como o Jack participa do atendimento.',['settings'=>$this->talk->settings()]);
    }
    public function queues(): void
    {
        $user=Auth::user();if($user===null){Response::to('/login');}$manage=$this->talk->canManage((int)$user->id);
        if(Request::isMethod('POST')){$this->requireCsrf('/talk/queues');if(!$manage){Response::to('/talk/queues?error=forbidden');}$action=(string)Request::post('action','');if($action==='save_queue'){$this->talk->saveQueue(max(0,(int)Request::post('id',0)),mb_substr(trim((string)Request::post('name','')),0,120),($d=max(0,(int)Request::post('department_id',0)))>0?$d:null,max(5,(int)Request::post('auto_assign_after_seconds',30)),(string)Request::post('status','active'));}elseif($action==='save_department'){$this->talk->saveDepartment(max(0,(int)Request::post('id',0)),mb_substr(trim((string)Request::post('name','')),0,120),(string)Request::post('status','active'));}elseif($action==='save_member'){$this->talk->saveQueueMember(max(1,(int)Request::post('queue_id',0)),max(1,(int)Request::post('user_id',0)),(string)Request::post('role','agent'),max(1,(int)Request::post('capacity',5)),(string)Request::post('status','active'));}elseif($action==='remove_member'){$this->talk->removeQueueMember(max(1,(int)Request::post('queue_id',0)),max(1,(int)Request::post('user_id',0)));}Response::to('/talk/queues?saved=1');}
        $queues=$this->talk->queues();$members=[];foreach($queues as $q){$members[(int)$q['id']]=$this->talk->queueMembers((int)$q['id']);}
        $this->page('Filas e departamentos','queues','Organize departamentos, filas e capacidade de atendimento.',['queues'=>$queues,'departments'=>$this->talk->departments(),'members'=>$members,'users'=>$this->talk->eligibleUsers(),'canManage'=>$manage]);
    }
    public function users(): void
    {
        $user=Auth::user();if($user===null){Response::to('/login');}
        if(Request::isMethod('POST')){$this->requireCsrf('/talk/users');$action=(string)Request::post('action','presence');if($action==='presence'){$this->talk->updatePresence((int)$user->id,(string)Request::post('presence','online'));}elseif($action==='user_settings'){if(!$this->talk->canManage((int)$user->id)){Response::to('/talk/users?error=forbidden');}$this->talk->saveUserSettings(max(1,(int)Request::post('user_id',0)),(string)Request::post('talk_role','agent'),max(1,(int)Request::post('capacity',5)));}Response::to('/talk/users');}
        $this->page('Usuários e permissões','users','Gerencie atendentes, supervisores e permissões do Talk.',['users'=>$this->talk->usersWithPresence(),'canManage'=>$this->talk->canManage((int)$user->id)]);
    }
    public function reports(): void{$this->page('Relatórios','reports','Indicadores de fila, atendimento, transferência e SLA.',['reports'=>$this->talk->reports()]);}
    public function settings(): void
    {
        $user=Auth::user();if($user===null){Response::to('/login');}
        if(Request::isMethod('POST')){$this->requireCsrf('/talk/settings');$this->talk->saveSettings(['auto_assign.enabled'=>Request::post('auto_assign_enabled')!==null?'1':'0','auto_assign.default_seconds'=>(string)max(5,(int)Request::post('auto_assign_default_seconds',30))],(int)$user->id);Response::to('/talk/settings?saved=1');}
        $this->page('Configurações','settings','Configurações gerais, canais e regras do Talk.',['settings'=>$this->talk->settings(),'channels'=>$this->talk->channels()]);
    }

    private function filters(): array
    {
        return ['q'=>(string)($_GET['q']??''),'status'=>(string)($_GET['status']??''),'priority'=>(string)($_GET['priority']??''),'queue_id'=>max(0,(int)($_GET['queue_id']??0)),'tag_id'=>max(0,(int)($_GET['tag_id']??0))];
    }
    private function requireCsrf(string $fallback): void{if(!Csrf::validate((string)Request::post('_token',''))){Response::to($fallback.'?error=csrf');}}
    private function page(string $title,string $currentPage,string $description,array $data=[]): void{echo $this->view->render('pages/talk-workspace',array_merge(['title'=>$title,'productName'=>'Talk','activeProduct'=>'talk','currentPage'=>$currentPage,'description'=>$description],$data));}
}
