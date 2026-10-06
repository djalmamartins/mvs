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
use Moves\Modules\Erp\People\PersonRepository;
use Moves\Modules\Erp\Security\ErpTenantContext;
use Moves\Modules\Erp\Suppliers\SupplierCategoryRepository;
use Moves\Modules\Erp\Suppliers\SupplierRepository;
use Moves\Modules\Erp\Suppliers\SupplierService;
use Moves\Services\Platform\CompanyService;
use Moves\Services\Platform\PlatformAudit;
use Throwable;

final class ErpSupplierController extends Controller
{
    public function index(): void
    {
        $context=ErpTenantContext::current();$categories=new SupplierCategoryRepository($context['pdo']);$categories->ensureDefaults($context['administrator_id']);$allCategories=$categories->all($context['administrator_id']);$condominiums=$this->condominiums($context['pdo'],$context['administrator_id']);
        $filters=['q'=>mb_substr(trim((string)($_GET['q']??'')),0,100),'status'=>in_array($_GET['status']??'', ['active','inactive'],true)?(string)$_GET['status']:'','category'=>$this->validOption((string)($_GET['category']??''),array_column($allCategories,'id')),'condominium'=>$this->validOption((string)($_GET['condominium']??''),array_column($condominiums,'id'))];
        echo $this->view->render('pages/erp-suppliers',$this->base($context)+['suppliers'=>(new SupplierRepository($context['pdo']))->search($context['administrator_id'],$filters),'filters'=>$filters,'categories'=>$allCategories,'condominiums'=>$condominiums]);
    }

    public function new(): void
    {
        $context=ErpTenantContext::current();$categories=new SupplierCategoryRepository($context['pdo']);$categories->ensureDefaults($context['administrator_id']);
        $people=$context['pdo']->prepare("SELECT id,entity_type,full_name,document_type,document_number,email FROM erp_people WHERE administrator_id=:administrator_id AND status='active' ORDER BY full_name LIMIT 500");$people->execute(['administrator_id'=>$context['administrator_id']]);
        echo $this->view->render('pages/erp-supplier-form',$this->base($context)+['categories'=>$categories->all($context['administrator_id']),'condominiums'=>$this->condominiums($context['pdo'],$context['administrator_id']),'people'=>array_values($people->fetchAll(\PDO::FETCH_ASSOC))]);
    }

    public function create(): never
    {
        $context=ErpTenantContext::current();$this->requireCsrf('/erp/suppliers/new');
        try{$service=$this->service($context['pdo']);$result=$service->create($context['tenant_id'],$context['administrator_id'],$context['user_id'],$_POST);Flash::set('success',$result['created_person']?'Pessoa e fornecedor cadastrados.':'Fornecedor associado à pessoa existente.');Response::to('/erp/suppliers/'.$result['id']);}catch(Throwable $exception){Flash::set('error',$this->message($exception));Response::to('/erp/suppliers/new');}
    }

    /** @param array<string,string> $route */
    public function show(array $route=[]): void
    {
        $context=ErpTenantContext::current();$id=max(0,(int)($route['supplier_id']??0));$repository=new SupplierRepository($context['pdo']);$supplier=$repository->find($context['administrator_id'],$id);if($supplier===null)throw new HttpException(404,'Fornecedor não encontrado.');$links=$repository->condominiums($context['administrator_id'],$id);
        echo $this->view->render('pages/erp-supplier-detail',$this->base($context)+['supplier'=>$supplier,'links'=>$links,'condominiums'=>$this->condominiums($context['pdo'],$context['administrator_id'])]);
    }

    /** @param array<string,string> $route */
    public function updateStatus(array $route=[]): never
    {
        $id=max(0,(int)($route['supplier_id']??0));$fallback='/erp/suppliers/'.$id;$context=ErpTenantContext::current();$this->requireCsrf($fallback);
        try{if(!$this->service($context['pdo'])->setStatus($context['tenant_id'],$context['administrator_id'],$context['user_id'],$id,(string)Request::post('status',''))){throw new \InvalidArgumentException('Fornecedor não encontrado.');}Flash::set('success','Situação do fornecedor atualizada.');}catch(Throwable $exception){Flash::set('error',$this->message($exception));}Response::to($fallback);
    }

    /** @param array<string,string> $route */
    public function addCondominium(array $route=[]): never
    {
        $id=max(0,(int)($route['supplier_id']??0));$fallback='/erp/suppliers/'.$id;$context=ErpTenantContext::current();$this->requireCsrf($fallback);
        try{$this->service($context['pdo'])->addCondominium($context['tenant_id'],$context['administrator_id'],$context['user_id'],$id,$_POST);Flash::set('success','Condomínio associado ao fornecedor.');}catch(Throwable $exception){Flash::set('error',$this->message($exception));}Response::to($fallback);
    }

    /** @param array<string,string> $route */
    public function closeCondominium(array $route=[]): never
    {
        $id=max(0,(int)($route['supplier_id']??0));$linkId=max(0,(int)($route['link_id']??0));$fallback='/erp/suppliers/'.$id;$context=ErpTenantContext::current();$this->requireCsrf($fallback);
        try{if(!$this->service($context['pdo'])->endCondominium($context['tenant_id'],$context['administrator_id'],$context['user_id'],$id,$linkId,(string)Request::post('ends_at',''))){throw new \InvalidArgumentException('Atendimento vigente não encontrado.');}Flash::set('success','Atendimento encerrado; histórico preservado.');}catch(Throwable $exception){Flash::set('error',$this->message($exception));}Response::to($fallback);
    }

    /** @return array<string,mixed> */
    private function base(array $context): array
    {
        return ['title'=>'Fornecedores','productName'=>'ERP','activeProduct'=>'erp','currentPage'=>'suppliers','company'=>(new CompanyService($context['pdo']))->find($context['tenant_id'])];
    }

    private function service(\PDO $pdo): SupplierService
    {
        return new SupplierService($pdo,new PersonRepository($pdo),new SupplierRepository($pdo),new PlatformAudit($pdo));
    }

    /** @return list<array<string,mixed>> */
    private function condominiums(\PDO $pdo,int $administratorId): array
    {
        $statement=$pdo->prepare("SELECT id,legal_name,trade_name FROM erp_condominiums WHERE administrator_id=:administrator_id AND status='active' ORDER BY legal_name");$statement->execute(['administrator_id'=>$administratorId]);return array_values($statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    /** @param list<mixed> $options */
    private function validOption(string $value,array $options): string
    {
        if($value===''||!ctype_digit($value))return '';foreach($options as $option)if((int)$option===(int)$value)return $value;return '';
    }

    private function requireCsrf(string $fallback): void
    {
        if(!Request::isMethod('POST'))Response::to($fallback);if(!Csrf::validate((string)Request::post('_token',''))){Flash::set('error','Sessão expirada. Atualize a página e tente novamente.');Response::to($fallback);}
    }

    private function message(Throwable $exception): string
    {
        return $exception instanceof \InvalidArgumentException?$exception->getMessage():'Não foi possível salvar o fornecedor. Verifique os dados e tente novamente.';
    }
}
