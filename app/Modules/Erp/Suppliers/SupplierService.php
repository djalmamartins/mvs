<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Suppliers;

use InvalidArgumentException;
use Moves\Modules\Erp\People\PeopleService;
use Moves\Modules\Erp\People\PersonRepository;
use Moves\Services\Platform\PlatformAudit;
use PDO;
use Throwable;

final readonly class SupplierService
{
    public function __construct(private PDO $pdo,private PersonRepository $people,private SupplierRepository $suppliers,private PlatformAudit $audit) {}

    /** @param array<string,mixed> $data @return array{id:int,person_id:int,created_person:bool} */
    public function create(int $tenantId,int $administratorId,int $actorId,array $data): array
    {
        $categoryId=filter_var($data['category_id']??null,FILTER_VALIDATE_INT);if(!is_int($categoryId)||$categoryId<1)throw new InvalidArgumentException('Selecione uma categoria válida.');
        $existingId=trim((string)($data['existing_person_id']??''));$createdPerson=false;$person=null;
        if($existingId!==''){$personId=filter_var($existingId,FILTER_VALIDATE_INT);if(!is_int($personId)||$personId<1||$this->people->find($administratorId,$personId)===null)throw new InvalidArgumentException('Pessoa não encontrada nesta administradora.');}
        else{$person=PeopleService::normalizePerson($data);if($person['document_number']!==null&&!self::validDocument((string)$person['document_type'],$person['document_number']))throw new InvalidArgumentException($person['document_type']==='cpf'?'Informe um CPF válido.':'Informe um CNPJ válido.');}
        $condominiumIds=$this->normalizeIds($data['condominium_ids']??[]);
        $startsAt=trim((string)($data['starts_at']??date('Y-m-d')));$endsAt=trim((string)($data['ends_at']??''))?:null;
        $this->assertDate($startsAt,'Data inicial');if($endsAt!==null){$this->assertDate($endsAt,'Data final');if($endsAt<$startsAt)throw new InvalidArgumentException('O fim do atendimento não pode anteceder o início.');}
        $this->pdo->beginTransaction();
        try{
            if($person!==null){$created=$this->people->createOrFind($administratorId,$actorId,$person);$personId=$created['id'];$createdPerson=$created['created'];if($createdPerson)$this->audit->record($tenantId,$actorId,'erp.person.created','person',$personId,['entity_type'=>$person['entity_type']]);}
            if($personId<1)throw new InvalidArgumentException('Selecione ou informe uma pessoa válida.');
            if($this->suppliers->hasProfileForPerson($administratorId,$personId))throw new InvalidArgumentException('Esta pessoa já está cadastrada como fornecedora.');
            $supplierId=$this->suppliers->createProfile($administratorId,$personId,$categoryId,$actorId);
            $this->audit->record($tenantId,$actorId,'erp.supplier.created','supplier',$supplierId,['person_id'=>$personId,'category_id'=>$categoryId]);
            foreach($condominiumIds as $condominiumId){$linkId=$this->suppliers->addCondominium($administratorId,$supplierId,$condominiumId,$actorId,$startsAt,$endsAt);$this->audit->record($tenantId,$actorId,'erp.supplier_condominium.created','supplier_condominium',$linkId,['supplier_id'=>$supplierId,'condominium_id'=>$condominiumId]);}
            $this->pdo->commit();return ['id'=>$supplierId,'person_id'=>$personId,'created_person'=>$createdPerson];
        }catch(Throwable $exception){if($this->pdo->inTransaction())$this->pdo->rollBack();if($exception instanceof \PDOException&&str_contains($exception->getMessage(),'uq_erp_suppliers_person'))throw new InvalidArgumentException('Esta pessoa já está cadastrada como fornecedora.');throw $exception;}
    }

    public function setStatus(int $tenantId,int $administratorId,int $actorId,int $supplierId,string $status): bool
    {
        if(!in_array($status,['active','inactive'],true))throw new InvalidArgumentException('Selecione uma situação válida.');$this->pdo->beginTransaction();
        try{$changed=$this->suppliers->updateStatus($administratorId,$supplierId,$status);if(!$changed){$this->pdo->rollBack();return false;}if($status==='inactive'){$this->pdo->prepare("UPDATE erp_supplier_condominiums SET ends_at=CURRENT_DATE,status='inactive',ended_by_user_id=:actor_id WHERE administrator_id=:administrator_id AND supplier_id=:supplier_id AND status='active' AND starts_at<=CURRENT_DATE")->execute(['actor_id'=>$actorId,'administrator_id'=>$administratorId,'supplier_id'=>$supplierId]);}$this->audit->record($tenantId,$actorId,'erp.supplier.status_changed','supplier',$supplierId,['status'=>$status]);$this->pdo->commit();return true;}catch(Throwable $exception){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $exception;}
    }

    public function addCondominium(int $tenantId,int $administratorId,int $actorId,int $supplierId,array $data): int
    {
        $condominiumId=filter_var($data['condominium_id']??null,FILTER_VALIDATE_INT);if(!is_int($condominiumId)||$condominiumId<1)throw new InvalidArgumentException('Selecione um condomínio válido.');$startsAt=trim((string)($data['starts_at']??''));$endsAt=trim((string)($data['ends_at']??''))?:null;
        $this->pdo->beginTransaction();try{$id=$this->suppliers->addCondominium($administratorId,$supplierId,$condominiumId,$actorId,$startsAt,$endsAt);$this->audit->record($tenantId,$actorId,'erp.supplier_condominium.created','supplier_condominium',$id,['supplier_id'=>$supplierId,'condominium_id'=>$condominiumId]);$this->pdo->commit();return $id;}catch(Throwable $exception){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $exception;}
    }

    public function endCondominium(int $tenantId,int $administratorId,int $actorId,int $supplierId,int $linkId,string $endsAt): bool
    {
        $this->pdo->beginTransaction();try{$ended=$this->suppliers->endCondominium($administratorId,$supplierId,$linkId,$actorId,$endsAt);if($ended)$this->audit->record($tenantId,$actorId,'erp.supplier_condominium.ended','supplier_condominium',$linkId,['supplier_id'=>$supplierId,'ends_at'=>$endsAt]);$this->pdo->commit();return $ended;}catch(Throwable $exception){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $exception;}
    }

    /** @return list<int> */
    private function normalizeIds(mixed $value): array
    {
        if($value===null||$value==='')return [];if(!is_array($value))throw new InvalidArgumentException('Selecione condomínios válidos.');$ids=[];foreach($value as $item){$id=filter_var($item,FILTER_VALIDATE_INT);if(!is_int($id)||$id<1)throw new InvalidArgumentException('Selecione condomínios válidos.');$ids[$id]=$id;}return array_values($ids);
    }

    private function assertDate(string $date,string $label): void
    {
        $value=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);if($value===false||$value->format('Y-m-d')!==$date)throw new InvalidArgumentException($label.' inválida.');
    }

    private static function validDocument(string $type,string $digits): bool
    {
        if($type==='cpf'){
            if(strlen($digits)!==11||preg_match('/^(\d)\1{10}$/',$digits)===1)return false;
            for($position=9;$position<11;$position++){$sum=0;for($index=0;$index<$position;$index++)$sum+=(int)$digits[$index]*(($position+1)-$index);$digit=($sum*10)%11;if($digit===10)$digit=0;if((int)$digits[$position]!==$digit)return false;}return true;
        }
        if($type!=='cnpj'||strlen($digits)!==14||preg_match('/^(\d)\1{13}$/',$digits)===1)return false;
        $digit=static function(string $base,array $weights):int{$sum=0;foreach($weights as $index=>$weight)$sum+=(int)$base[$index]*$weight;$remainder=$sum%11;return $remainder<2?0:11-$remainder;};
        $first=$digit(substr($digits,0,12),[5,4,3,2,9,8,7,6,5,4,3,2]);$second=$digit(substr($digits,0,12).$first,[6,5,4,3,2,9,8,7,6,5,4,3,2]);return substr($digits,12,2)===$first.$second;
    }
}
