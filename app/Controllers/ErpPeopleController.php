<?php

declare(strict_types=1);

namespace Moves\Controllers;

use Moves\Boot\Connection;
use Moves\Core\Auth;
use Moves\Core\Controller;
use Moves\Core\Csrf;
use Moves\Core\Flash;
use Moves\Core\HttpException;
use Moves\Core\Request;
use Moves\Core\Response;
use Moves\Modules\Erp\People\PeopleService;
use Moves\Modules\Erp\People\PersonLinkRepository;
use Moves\Modules\Erp\People\PersonRepository;
use Moves\Modules\Erp\Security\AdministratorTenantAccess;
use Moves\Modules\Erp\Structure\PhysicalStructureRepository;
use Moves\Modules\Erp\Structure\PhysicalStructureService;
use Moves\Services\Platform\CompanyService;
use Moves\Services\Platform\CondominiumService;
use Moves\Services\Platform\PlatformAudit;
use Moves\Services\Platform\TenantContext;
use MovesCode\Router\Router;
use Throwable;

final class ErpPeopleController extends Controller
{
    public function __construct(Router $router)
    {
        parent::__construct($router);
    }

    public function index(): void
    {
        [$pdo,$tenantId,$administratorId,$userId,$condominiums] = $this->context();
        $filters = [
            'q'=>mb_substr(trim((string) ($_GET['q'] ?? '')),0,100),
            'status'=>in_array($_GET['status'] ?? '',['active','inactive'],true)?(string)$_GET['status']:'',
            'condominium'=>$this->validCondominiumFilter((string)($_GET['condominium']??''),$condominiums),
            'role'=>in_array($_GET['role']??'',PersonLinkRepository::ROLES,true)?(string)$_GET['role']:'',
        ];
        echo $this->view->render('pages/erp-people', $this->base($tenantId,$userId,'people') + [
            'people'=>$this->peopleService($pdo)->search($administratorId,$filters), 'filters'=>$filters,'condominiums'=>$condominiums,
        ]);
    }

    public function new(): void
    {
        [$pdo,$tenantId,$administratorId,$userId,$condominiums] = $this->context();
        echo $this->view->render('pages/erp-person-form',$this->base($tenantId,$userId,'people') + [
            'person'=>null,'condominiums'=>$condominiums,'units'=>$this->structureService($pdo)->units($administratorId),'error'=>null,
        ]);
    }

    public function create(): never
    {
        $this->requirePostCsrf('/erp/people/new');
        [$pdo,$tenantId,$administratorId,$userId] = $this->context();
        try {
            $result=$this->peopleService($pdo)->create($tenantId,$administratorId,$userId,$_POST);
            Flash::set('success',$result['created']?'Pessoa cadastrada.':'Pessoa já cadastrada; vínculo atualizado.');
            Response::to('/erp/people/'.$result['id']);
        } catch (Throwable $exception) {
            Flash::set('error',$this->message($exception));
            Response::to('/erp/people/new');
        }
    }

    /** @param array<string,string> $route */
    public function show(array $route=[]): void
    {
        [$pdo,$tenantId,$administratorId,$userId,$condominiums] = $this->context();
        $id=max(0,(int)($route['person_id']??0));
        $detail=$this->peopleService($pdo)->detail($administratorId,$id);
        if($detail===null){$this->notFound();}
        echo $this->view->render('pages/erp-person-detail',$this->base($tenantId,$userId,'people') + [
            'detail'=>$detail,'condominiums'=>$condominiums,'units'=>$this->structureService($pdo)->units($administratorId),
        ]);
    }

    /** @param array<string,string> $route */
    public function addLink(array $route=[]): never
    {
        $id=max(0,(int)($route['person_id']??0));$fallback='/erp/people/'.$id;
        $this->requirePostCsrf($fallback);[$pdo,$tenantId,$administratorId,$userId]=$this->context();
        try{$this->peopleService($pdo)->addLink($tenantId,$administratorId,$userId,$id,$_POST);Flash::set('success','Vínculo registrado.');}
        catch(Throwable $exception){Flash::set('error',$this->message($exception));}
        Response::to($fallback);
    }

    /** @param array<string,string> $route */
    public function closeLink(array $route=[]): never
    {
        $id=max(0,(int)($route['person_id']??0));$link=max(0,(int)($route['link_id']??0));$fallback='/erp/people/'.$id;
        $this->requirePostCsrf($fallback);[$pdo,$tenantId,$administratorId,$userId]=$this->context();
        try{if(!$this->peopleService($pdo)->endLink($tenantId,$administratorId,$userId,$link,(string)Request::post('ends_at',''))){throw new \InvalidArgumentException('Vínculo ativo não encontrado nesta administradora.');}Flash::set('success','Vínculo encerrado e histórico preservado.');}
        catch(Throwable $exception){Flash::set('error',$this->message($exception));}
        Response::to($fallback);
    }

    public function units(): void
    {
        [$pdo,$tenantId,$administratorId,$userId,$condominiums]=$this->context();
        $filters=['q'=>mb_substr(trim((string)($_GET['q']??'')),0,100),'status'=>in_array($_GET['status']??'', ['active','inactive'],true)?(string)$_GET['status']:'','condominium'=>$this->validCondominiumFilter((string)($_GET['condominium']??''),$condominiums)];
        $units=$this->structureService($pdo)->units($administratorId,$filters);$people=$this->peopleService($pdo);
        foreach($units as &$unit){$links=$people->linksForUnit($administratorId,(int)$unit['id']);$unit['owners']=$this->namesForRole($links,['owner']);$unit['residents']=$this->namesForRole($links,['resident','tenant']);}unset($unit);
        echo $this->view->render('pages/erp-units',$this->base($tenantId,$userId,'units') + ['units'=>$units,'filters'=>$filters,'condominiums'=>$condominiums]);
    }

    public function newUnit(): void
    {
        [, $tenantId,, $userId,$condominiums]=$this->context();
        echo $this->view->render('pages/erp-unit-form',$this->base($tenantId,$userId,'units')+['condominiums'=>$condominiums,'unit'=>null]);
    }

    public function createUnit(): never
    {
        $this->requirePostCsrf('/erp/units/new');[$pdo,$tenantId,$administratorId,$userId]=$this->context();
        try{$id=$this->structureService($pdo)->createUnit($tenantId,$administratorId,$userId,$_POST);Flash::set('success','Unidade cadastrada.');Response::to('/erp/units/'.$id);}
        catch(Throwable $exception){Flash::set('error',$this->message($exception));Response::to('/erp/units/new');}
    }

    /** @param array<string,string> $route */
    public function editUnit(array $route=[]): void
    {
        [$pdo,$tenantId,$administratorId,$userId]=$this->context();
        $unit=$this->structureService($pdo)->unit($administratorId,max(0,(int)($route['unit_id']??0)));
        if($unit===null){$this->notFound();}
        echo $this->view->render('pages/erp-unit-form',$this->base($tenantId,$userId,'units')+['condominiums'=>[],'unit'=>$unit]);
    }

    /** @param array<string,string> $route */
    public function updateUnit(array $route=[]): never
    {
        $id=max(0,(int)($route['unit_id']??0));$fallback='/erp/units/'.$id.'/edit';
        $this->requirePostCsrf($fallback);[$pdo,$tenantId,$administratorId,$userId]=$this->context();
        try {
            $updated=$this->structureService($pdo)->updateUnit($tenantId,$administratorId,$userId,$id,$_POST);
        } catch (Throwable $exception) {
            Flash::set('error',$this->message($exception));
            Response::to($fallback);
        }
        if(!$updated){$this->notFound();}
        Flash::set('success','Identificação da unidade atualizada.');
        Response::to('/erp/units/'.$id);
    }

    /** @param array<string,string> $route */
    public function showUnit(array $route=[]): void
    {
        [$pdo,$tenantId,$administratorId,$userId,$condominiums]=$this->context();$unit=$this->structureService($pdo)->unit($administratorId,max(0,(int)($route['unit_id']??0)));
        if($unit===null){$this->notFound();}
        echo $this->view->render('pages/erp-unit-detail',$this->base($tenantId,$userId,'units')+['unit'=>$unit,'links'=>$this->peopleService($pdo)->linksForUnit($administratorId,(int)$unit['id']),'condominiums'=>$condominiums]);
    }

    /** @return array{0:\PDO,1:int,2:int,3:int,4:list<array<string,mixed>>} */
    private function context(): array
    {
        $user=Auth::user();if($user===null){Response::to('/login');}
        $pdo=Connection::getInstance();$userId=(int)$user->id;$tenantId=(new TenantContext($pdo))->currentId($userId);
        if(!(new AdministratorTenantAccess($pdo))->hasAdministrator($userId,$tenantId)){throw new HttpException(403,'ERP indisponível para este usuário.');}
        $statement=$pdo->prepare("SELECT id FROM erp_administrators WHERE tenant_id=:tenant_id AND status='active' ORDER BY id LIMIT 1");$statement->execute(['tenant_id'=>$tenantId]);$administratorId=(int)$statement->fetchColumn();
        if($administratorId<1){throw new HttpException(403,'Administradora ativa não encontrada.');}
        return [$pdo,$tenantId,$administratorId,$userId,(new CondominiumService($pdo))->all($tenantId)];
    }

    private function peopleService(\PDO $pdo): PeopleService { return new PeopleService($pdo,new PersonRepository($pdo),new PersonLinkRepository($pdo),new PhysicalStructureRepository($pdo),new PlatformAudit($pdo)); }
    private function structureService(\PDO $pdo): PhysicalStructureService { return new PhysicalStructureService($pdo,new PhysicalStructureRepository($pdo),new PlatformAudit($pdo)); }
    /** @return array<string,mixed> */ private function base(int $tenantId,int $userId,string $currentPage): array { $pdo=Connection::getInstance();return ['title'=>$currentPage==='people'?'Pessoas':'Unidades','productName'=>'ERP','activeProduct'=>'erp','currentPage'=>$currentPage,'company'=>(new CompanyService($pdo))->find($tenantId),'actorId'=>$userId]; }
    /** @param list<array<string,mixed>> $condominiums */ private function validCondominiumFilter(string $value,array $condominiums): string { if($value===''||!ctype_digit($value))return '';return in_array((int)$value,array_map(static fn(array $row):int=>(int)$row['id'],$condominiums),true)?$value:''; }
    /** @param list<array<string,mixed>> $links @param list<string> $roles @return list<string> */ private function namesForRole(array $links,array $roles): array { $names=[];foreach($links as $link){if(in_array($link['role'],$roles,true)&&(int)$link['is_current']===1){$names[]=(string)$link['full_name'];}}return array_values(array_unique($names)); }
    private function requirePostCsrf(string $fallback): void { if(!Request::isMethod('POST')){Response::to($fallback);}if(!Csrf::validate((string)Request::post('_token',''))){Flash::set('error','Sessão expirada. Atualize a página e tente novamente.');Response::to($fallback);} }
    private function message(Throwable $exception): string { return $exception instanceof \InvalidArgumentException?$exception->getMessage():'Não foi possível salvar os dados agora. Verifique os campos e tente novamente.'; }
    private function notFound(): never { throw new HttpException(404,'Registro não encontrado.'); }
}
