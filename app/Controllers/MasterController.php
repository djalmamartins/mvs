<?php

declare(strict_types=1);

namespace Moves\Controllers;

use Moves\Core\Auth;
use Moves\Core\Controller;
use Moves\Core\Csrf;
use Moves\Core\Flash;
use Moves\Core\Request;
use Moves\Core\Response;
use Moves\Boot\Connection;
use MovesCode\Storage\Image;
use Moves\Services\Master\MasterAdministratorService;
use RuntimeException;
use Throwable;

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
        $page=max(1,(int)($_GET['page']??1));$data=(new MasterAdministratorService())->search($q,$status,$page);
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
            $service=new MasterAdministratorService();$actor=(int)Auth::user()?->id;$id=$service->create($this->input(),$actor,['primary_color'=>(string)Request::post('primary_color','#6E00B3'),'secondary_color'=>(string)Request::post('secondary_color','')]);
            Flash::set('success','Administradora criada com sucesso.');
            Response::to('/master/administrators/'.$id);
        } catch (RuntimeException $e) { Flash::set('error',$e->getMessage());Response::to('/master/administrators/create'); } catch (Throwable $e) { Flash::set('error','Não foi possível criar a administradora. Tente novamente.');Response::to('/master/administrators/create'); }
    }

    public function show(array $params=[]): void
    {
        $id=(int)($params['id']??0);$administrator=(new MasterAdministratorService())->find($id);
        if($administrator===null){Flash::set('error','Administradora não encontrada.');Response::to('/master/administrators');}
        echo $this->view->render('pages/master-administrator-show',['title'=>(string)$administrator['trade_name']?: (string)$administrator['legal_name'],'productName'=>'Master','activeProduct'=>'master','currentPage'=>'administrators','administrator'=>$administrator,'availableUsers'=>(new MasterAdministratorService())->availableUsers($id)]);
    }

    public function branding(array $params=[]): void
    {
        $id=(int)($params['id']??0);$a=(new MasterAdministratorService())->find($id);if($a===null){Response::to('/master/administrators');}
        echo $this->view->render('pages/master-branding',['title'=>'Identidade visual','productName'=>'Master','activeProduct'=>'master','currentPage'=>'administrators','administrator'=>$a]);
    }
    public function brandingSave(array $params=[]): void
    {
        $id=(int)($params['id']??0);$this->guardCsrf('/master/administrators/'.$id.'/branding');
        try{$logo=(string)Request::post('logo_path','');$upload=$_FILES['logo']??[];if((int)($upload['size']??0)>0){if((int)($upload['size']??0)>4*1024*1024)throw new RuntimeException('O logotipo deve ter no máximo 4 MB.');$path=(new Image(dirname(__DIR__,2).'/storage','media'))->upload($upload,(string)($upload['name']??'logo'),1600);$info=getimagesize($path);$pdo=Connection::getInstance();$statement=$pdo->prepare('INSERT INTO studio_media(name,path,mime,size,width,height,created_by) VALUES(?,?,?,?,?,?,?)');$statement->execute([basename($path),$path,(string)($info['mime']??'application/octet-stream'),filesize($path),$info[0]??null,$info[1]??null,Auth::user()?->id]);$logo='/media/'.(int)$pdo->lastInsertId();}(new MasterAdministratorService())->updateBranding($id,['trade_name'=>(string)Request::post('trade_name',''),'logo_path'=>$logo,'primary_color'=>(string)Request::post('primary_color',''),'secondary_color'=>(string)Request::post('secondary_color','')],(int)Auth::user()?->id);Flash::set('success','Identidade visual atualizada.');}catch(Throwable $e){Flash::set('error',$e->getMessage());}
        Response::to('/master/administrators/'.$id.'/branding');
    }
    public function productSave(array $params=[]): void
    {
        $id=(int)($params['id']??0);$this->guardCsrf('/master/administrators/'.$id);
        try{(new MasterAdministratorService())->setProduct($id,(string)Request::post('product',''),(string)Request::post('status',''),(int)Auth::user()?->id);Flash::set('success','Produto atualizado.');}catch(Throwable $e){Flash::set('error',$e->getMessage());}
        Response::to('/master/administrators/'.$id);
    }
    public function membershipSave(array $params=[]): void
    {
        $id=(int)($params['id']??0);$this->guardCsrf('/master/administrators/'.$id);
        try{(new MasterAdministratorService())->saveMembership($id,max(1,(int)Request::post('user_id',0)),(string)Request::post('role','agent'),(string)Request::post('status','active'),(int)Auth::user()?->id);Flash::set('success','Membership atualizada.');}catch(Throwable $e){Flash::set('error',$e->getMessage());}
        Response::to('/master/administrators/'.$id);
    }

    public function securitySave(array $params=[]): void
    {
        $id=(int)($params['id']??0);$this->guardCsrf('/master/administrators/'.$id);
        try{(new MasterAdministratorService())->updateSecurity($id,['require_mfa'=>Request::post('require_mfa',0),'session_timeout_minutes'=>Request::post('session_timeout_minutes',480),'allowed_email_domains'=>(string)Request::post('allowed_email_domains','')],(int)Auth::user()?->id);Flash::set('success','Política de segurança atualizada.');}catch(Throwable $e){Flash::set('error',$e->getMessage());}
        Response::to('/master/administrators/'.$id);
    }

    public function inviteUser(array $params=[]): void
    {
        $id=(int)($params['id']??0);$this->guardCsrf('/master/administrators/'.$id);
        try{(new MasterAdministratorService())->inviteUser($id,['name'=>(string)Request::post('name',''),'email'=>(string)Request::post('email',''),'role'=>(string)Request::post('role','agent')],(int)Auth::user()?->id);Flash::set('success','Usuário vinculado à administradora.');}catch(Throwable $e){Flash::set('error',$e->getMessage());}
        Response::to('/master/administrators/'.$id);
    }

    public function statusSave(array $params=[]): void
    {
        $id=(int)($params['id']??0);$this->guardCsrf('/master/administrators/'.$id);
        try{(new MasterAdministratorService())->updateStatus($id,(string)Request::post('status',''),(string)Request::post('confirmation',''),(string)Request::post('reason',''),(int)Auth::user()?->id);Flash::set('success','Status da administradora atualizado.');}catch(Throwable $e){Flash::set('error',$e->getMessage());}
        Response::to('/master/administrators/'.$id);
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
        catch(Throwable $e){Flash::set('error','Não foi possível atualizar a administradora. Tente novamente.');Response::to('/master/administrators/'.$id.'/edit');}
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