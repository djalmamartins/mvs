<?php

declare(strict_types=1);

namespace Moves\Controllers;

use Moves\Boot\Connection;
use Moves\Core\Controller;
use Moves\Core\Csrf;
use Moves\Core\Flash;
use Moves\Core\HttpException;
use Moves\Core\Request;
use Moves\Core\Response;
use Moves\Modules\Erp\Cadastros\CondominiumReadRepository;
use Moves\Modules\Erp\Security\ErpTenantContext;
use Moves\Services\Platform\CompanyService;
use Moves\Services\Platform\CondominiumService;
use Throwable;

final class ErpCondominiumController extends Controller
{
    public function index(): void
    {
        $context=ErpTenantContext::current();$filters=['q'=>mb_substr(trim((string)($_GET['q']??'')),0,100),'status'=>in_array($_GET['status']??'', ['active','inactive'],true)?(string)$_GET['status']:''];
        echo $this->view->render('pages/erp-condominiums',$this->base($context)+['condominiums'=>(new CondominiumReadRepository($context['pdo']))->search($context['administrator_id'],$filters),'filters'=>$filters]);
    }

    public function new(): void
    {
        $context=ErpTenantContext::current();echo $this->view->render('pages/erp-condominium-form',$this->base($context)+['condominium'=>null]);
    }

    /** @param array<string,string> $route */
    public function edit(array $route=[]): void
    {
        $context=ErpTenantContext::current();$id=max(0,(int)($route['condominium_id']??0));$condominium=(new CondominiumReadRepository($context['pdo']))->find($context['administrator_id'],$id);if($condominium===null)throw new HttpException(404,'Condomínio não encontrado.');
        echo $this->view->render('pages/erp-condominium-form',$this->base($context)+['condominium'=>$condominium]);
    }

    public function create(): never
    {
        $context=ErpTenantContext::current();$this->requireCsrf('/erp/condominiums/new');
        try{$id=(new CondominiumService($context['pdo']))->save($context['tenant_id'],$_POST,$context['user_id']);Flash::set('success','Condomínio cadastrado.');Response::to('/erp/condominiums/'.$id);}catch(Throwable $exception){Flash::set('error',$this->message($exception));Response::to('/erp/condominiums/new');}
    }

    /** @param array<string,string> $route */
    public function update(array $route=[]): never
    {
        $id=max(0,(int)($route['condominium_id']??0));$fallback='/erp/condominiums/'.$id.'/edit';$context=ErpTenantContext::current();$this->requireCsrf($fallback);
        try{(new CondominiumService($context['pdo']))->save($context['tenant_id'],$_POST,$context['user_id'],$id);Flash::set('success','Dados do condomínio atualizados.');Response::to('/erp/condominiums/'.$id);}catch(Throwable $exception){Flash::set('error',$this->message($exception));Response::to($fallback);}
    }

    /** @param array<string,string> $route */
    public function show(array $route=[]): void
    {
        $context=ErpTenantContext::current();$id=max(0,(int)($route['condominium_id']??0));$repository=new CondominiumReadRepository($context['pdo']);$condominium=$repository->find($context['administrator_id'],$id);if($condominium===null)throw new HttpException(404,'Condomínio não encontrado.');
        $people=$repository->people($context['administrator_id'],$id);$manager=null;foreach($people as $person){if($person['role']==='manager'&&$person['status']==='active'&&$person['starts_at']<=date('Y-m-d')&&($person['ends_at']===null||$person['ends_at']>=date('Y-m-d'))){$manager=$person;break;}}
        echo $this->view->render('pages/erp-condominium-detail',$this->base($context)+['condominium'=>$condominium,'units'=>$repository->units($context['administrator_id'],$id),'people'=>$people,'manager'=>$manager,'roles'=>['owner'=>'Proprietário','tenant'=>'Inquilino','resident'=>'Morador','manager'=>'Síndico','deputy_manager'=>'Subsíndico','council'=>'Conselheiro','proxy'=>'Procurador']]);
    }

    /** @param array{pdo:\PDO,tenant_id:int,administrator_id:int,user_id:int} $context @return array<string,mixed> */
    private function base(array $context): array
    {
        return ['title'=>'Condomínios','productName'=>'ERP','activeProduct'=>'erp','currentPage'=>'condominiums','company'=>(new CompanyService($context['pdo']))->find($context['tenant_id'])];
    }

    private function requireCsrf(string $fallback): void
    {
        if(!Request::isMethod('POST'))Response::to($fallback);if(!Csrf::validate((string)Request::post('_token',''))){Flash::set('error','Sessão expirada. Atualize a página e tente novamente.');Response::to($fallback);}
    }

    private function message(Throwable $exception): string
    {
        return $exception instanceof \InvalidArgumentException?$exception->getMessage():'Não foi possível salvar o condomínio. Verifique os dados e tente novamente.';
    }
}
