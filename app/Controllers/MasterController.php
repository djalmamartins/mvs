<?php

declare(strict_types=1);

namespace Moves\Controllers;

use Moves\Core\Auth;
use Moves\Core\Controller;
use Moves\Core\Csrf;
use Moves\Core\Flash;
use Moves\Core\Request;
use Moves\Core\Response;
use Moves\Services\Master\MasterAdministratorService;
use RuntimeException;

final class MasterController extends Controller
{
    public function dashboard(): void
    {
        $data=(new MasterAdministratorService())->search();
        echo $this->view->render('pages/master-dashboard',['title'=>'Visão geral','productName'=>'Master','activeProduct'=>'master','currentPage'=>'dashboard','stats'=>$data]);
    }

    public function administrators(): void
    {
        $q=mb_substr(trim((string)($_GET['q']??'')),0,120);$status=(string)($_GET['status']??'');
        $data=(new MasterAdministratorService())->search($q,$status);
        echo $this->view->render('pages/master-administrators',['title'=>'Administradoras','productName'=>'Master','activeProduct'=>'master','currentPage'=>'administrators','data'=>$data,'q'=>$q,'status'=>$status]);
    }

    public function create(): void
    {
        echo $this->view->render('pages/master-administrator-form',['title'=>'Nova administradora','productName'=>'Master','activeProduct'=>'master','currentPage'=>'administrators','administrator'=>null]);
    }

    public function store(): void
    {
        $this->guardCsrf('/master/administrators/create');
        try {
            $id=(new MasterAdministratorService())->create($this->input(),(int)Auth::user()?->id);
            Flash::set('success','Administradora criada com sucesso.');
            Response::to('/master/administrators/'.$id);
        } catch (RuntimeException $e) { Flash::set('error',$e->getMessage());Response::to('/master/administrators/create'); }
    }

    public function show(array $params=[]): void
    {
        $id=(int)($params['id']??0);$administrator=(new MasterAdministratorService())->find($id);
        if($administrator===null){Flash::set('error','Administradora não encontrada.');Response::to('/master/administrators');}
        echo $this->view->render('pages/master-administrator-show',['title'=>(string)$administrator['trade_name']?: (string)$administrator['legal_name'],'productName'=>'Master','activeProduct'=>'master','currentPage'=>'administrators','administrator'=>$administrator]);
    }

    public function edit(array $params=[]): void
    {
        $id=(int)($params['id']??0);$administrator=(new MasterAdministratorService())->find($id);
        if($administrator===null){Flash::set('error','Administradora não encontrada.');Response::to('/master/administrators');}
        echo $this->view->render('pages/master-administrator-form',['title'=>'Editar administradora','productName'=>'Master','activeProduct'=>'master','currentPage'=>'administrators','administrator'=>$administrator]);
    }

    public function update(array $params=[]): void
    {
        $id=(int)($params['id']??0);$this->guardCsrf('/master/administrators/'.$id.'/edit');
        try{(new MasterAdministratorService())->update($id,$this->input(),(int)Auth::user()?->id);Flash::set('success','Administradora atualizada com sucesso.');Response::to('/master/administrators/'.$id);}
        catch(RuntimeException $e){Flash::set('error',$e->getMessage());Response::to('/master/administrators/'.$id.'/edit');}
    }

    private function guardCsrf(string $redirect): void
    {
        $token=Request::post('_token');if(!is_string($token)||!Csrf::validate($token)){Flash::set('error','Token de segurança inválido.');Response::to($redirect);}
    }

    /** @return array<string,string> */
    private function input(): array
    {
        $out=[];foreach(['legal_name','trade_name','tax_id','contact_name','contact_email','contact_phone','status','notes'] as $key)$out[$key]=(string)Request::post($key,'');return $out;
    }
}