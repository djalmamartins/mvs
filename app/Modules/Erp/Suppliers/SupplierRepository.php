<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Suppliers;

use PDO;

final readonly class SupplierRepository
{
    public function __construct(private PDO $pdo) {}

    /** @return list<array<string,mixed>> */
    public function search(int $administratorId,array $filters): array
    {
        $where=['s.administrator_id=:administrator_id'];$params=['administrator_id'=>$administratorId];
        if(($filters['q']??'')!==''){$where[]='(p.full_name LIKE :name OR p.trade_name LIKE :trade OR p.document_number LIKE :document)';$needle='%'.trim((string)$filters['q']).'%';$digits='%'.(preg_replace('/\D+/','',(string)$filters['q'])??'').'%';$params+=['name'=>$needle,'trade'=>$needle,'document'=>$digits];}
        if(($filters['status']??'')!==''){$where[]='s.status=:status';$params['status']=$filters['status'];}
        if(($filters['category']??'')!==''){$where[]='s.category_id=:category_id';$params['category_id']=(int)$filters['category'];}
        if(($filters['condominium']??'')!==''){$where[]="EXISTS(SELECT 1 FROM erp_supplier_condominiums link WHERE link.administrator_id=s.administrator_id AND link.supplier_id=s.id AND link.condominium_id=:condominium_id AND link.status='active' AND link.starts_at<=CURRENT_DATE AND (link.ends_at IS NULL OR link.ends_at>=CURRENT_DATE))";$params['condominium_id']=(int)$filters['condominium'];}
        $statement=$this->pdo->prepare("SELECT s.id,s.person_id,s.category_id,s.status,p.entity_type,p.full_name,p.trade_name,p.document_type,p.document_number,p.email,p.phone,c.name AS category_name,
                (SELECT GROUP_CONCAT(DISTINCT COALESCE(NULLIF(condo.trade_name,''),condo.legal_name) ORDER BY condo.legal_name SEPARATOR ', ')
                 FROM erp_supplier_condominiums link JOIN erp_condominiums condo ON condo.administrator_id=link.administrator_id AND condo.id=link.condominium_id
                 WHERE link.administrator_id=s.administrator_id AND link.supplier_id=s.id AND link.status='active' AND link.starts_at<=CURRENT_DATE AND (link.ends_at IS NULL OR link.ends_at>=CURRENT_DATE)) AS condominiums_label
             FROM erp_suppliers s JOIN erp_people p ON p.administrator_id=s.administrator_id AND p.id=s.person_id
             JOIN erp_supplier_categories c ON c.administrator_id=s.administrator_id AND c.id=s.category_id
             WHERE ".implode(' AND ',$where).' ORDER BY p.full_name,s.id LIMIT 500');
        $statement->execute($params);
        $rows=array_values($statement->fetchAll(PDO::FETCH_ASSOC));
        foreach($rows as &$row){$row['document_display']=self::documentDisplay($row['document_type']??null,$row['document_number']??null);$row['contact_display']=trim(implode(' · ',array_filter([(string)($row['email']??''),(string)($row['phone']??'')])))?:'—';$row['condominiums_label']=$row['condominiums_label']?:'—';}unset($row);
        return $rows;
    }

    /** @return array<string,mixed>|null */
    public function find(int $administratorId,int $supplierId): ?array
    {
        $statement=$this->pdo->prepare('SELECT s.id,s.person_id,s.category_id,s.status,s.created_at,p.entity_type,p.full_name,p.trade_name,p.document_type,p.document_number,p.email,p.phone,c.name AS category_name FROM erp_suppliers s JOIN erp_people p ON p.administrator_id=s.administrator_id AND p.id=s.person_id JOIN erp_supplier_categories c ON c.administrator_id=s.administrator_id AND c.id=s.category_id WHERE s.administrator_id=:administrator_id AND s.id=:id');
        $statement->execute(['administrator_id'=>$administratorId,'id'=>$supplierId]);$row=$statement->fetch(PDO::FETCH_ASSOC);return $row===false?null:$row;
    }

    /** @return list<array<string,mixed>> */
    public function condominiums(int $administratorId,int $supplierId): array
    {
        $statement=$this->pdo->prepare('SELECT link.id,link.condominium_id,link.starts_at,link.ends_at,link.status,c.legal_name,c.trade_name FROM erp_supplier_condominiums link JOIN erp_condominiums c ON c.administrator_id=link.administrator_id AND c.id=link.condominium_id WHERE link.administrator_id=:administrator_id AND link.supplier_id=:supplier_id ORDER BY link.status DESC,link.starts_at DESC,link.id DESC');
        $statement->execute(['administrator_id'=>$administratorId,'supplier_id'=>$supplierId]);return array_values($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function createProfile(int $administratorId,int $personId,int $categoryId,int $actorId): int
    {
        $statement=$this->pdo->prepare('INSERT INTO erp_suppliers(administrator_id,person_id,category_id,status,created_by_user_id) SELECT :administrator_id,p.id,c.id,\'active\',:actor_id FROM erp_people p JOIN erp_supplier_categories c ON c.administrator_id=:category_administrator AND c.id=:category_id AND c.status=\'active\' WHERE p.administrator_id=:person_administrator AND p.id=:person_id AND p.status=\'active\'');
        $statement->execute(['administrator_id'=>$administratorId,'actor_id'=>$actorId,'category_administrator'=>$administratorId,'category_id'=>$categoryId,'person_administrator'=>$administratorId,'person_id'=>$personId]);
        if($statement->rowCount()!==1)throw new \InvalidArgumentException('Pessoa ou categoria não encontrada nesta administradora.');
        return (int)$this->pdo->lastInsertId();
    }

    public function hasProfileForPerson(int $administratorId,int $personId): bool
    {
        $statement=$this->pdo->prepare('SELECT 1 FROM erp_suppliers WHERE administrator_id=:administrator_id AND person_id=:person_id LIMIT 1');$statement->execute(['administrator_id'=>$administratorId,'person_id'=>$personId]);return $statement->fetchColumn()!==false;
    }

    public function updateStatus(int $administratorId,int $supplierId,string $status): bool
    {
        $statement=$this->pdo->prepare('UPDATE erp_suppliers SET status=:status WHERE administrator_id=:administrator_id AND id=:id');$statement->execute(['status'=>$status,'administrator_id'=>$administratorId,'id'=>$supplierId]);return $statement->rowCount()===1;
    }

    public function addCondominium(int $administratorId,int $supplierId,int $condominiumId,int $actorId,string $startsAt,?string $endsAt): int
    {
        $this->assertDate($startsAt);if($endsAt!==null){$this->assertDate($endsAt);if($endsAt<$startsAt)throw new \InvalidArgumentException('O fim do atendimento não pode anteceder o início.');}
        $exists=$this->pdo->prepare("SELECT 1 FROM erp_supplier_condominiums WHERE administrator_id=:administrator_id AND supplier_id=:supplier_id AND condominium_id=:condominium_id AND status='active' AND starts_at<=COALESCE(:ends_at,'9999-12-31') AND (ends_at IS NULL OR ends_at>=:starts_at) LIMIT 1");
        $exists->execute(['administrator_id'=>$administratorId,'supplier_id'=>$supplierId,'condominium_id'=>$condominiumId,'ends_at'=>$endsAt,'starts_at'=>$startsAt]);if($exists->fetchColumn()!==false)throw new \InvalidArgumentException('Já existe vínculo vigente do fornecedor com este condomínio nesse período.');
        $statement=$this->pdo->prepare('INSERT INTO erp_supplier_condominiums(administrator_id,supplier_id,condominium_id,starts_at,ends_at,status,created_by_user_id) SELECT s.administrator_id,s.id,c.id,:starts_at,:ends_at,\'active\',:actor_id FROM erp_suppliers s JOIN erp_condominiums c ON c.administrator_id=s.administrator_id AND c.id=:condominium_id WHERE s.administrator_id=:administrator_id AND s.id=:supplier_id AND s.status=\'active\'');
        $statement->execute(['starts_at'=>$startsAt,'ends_at'=>$endsAt,'actor_id'=>$actorId,'condominium_id'=>$condominiumId,'administrator_id'=>$administratorId,'supplier_id'=>$supplierId]);if($statement->rowCount()!==1)throw new \InvalidArgumentException('Fornecedor ou condomínio não encontrado nesta administradora.');return (int)$this->pdo->lastInsertId();
    }

    public function endCondominium(int $administratorId,int $supplierId,int $linkId,int $actorId,string $endsAt): bool
    {
        $this->assertDate($endsAt);$statement=$this->pdo->prepare("UPDATE erp_supplier_condominiums SET ends_at=:new_ends_at,status='inactive',ended_by_user_id=:actor_id WHERE administrator_id=:administrator_id AND supplier_id=:supplier_id AND id=:id AND status='active' AND starts_at<=:valid_on");$statement->execute(['new_ends_at'=>$endsAt,'valid_on'=>$endsAt,'actor_id'=>$actorId,'administrator_id'=>$administratorId,'supplier_id'=>$supplierId,'id'=>$linkId]);return $statement->rowCount()===1;
    }

    private static function assertDate(string $date): void
    {
        $value=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);if($value===false||$value->format('Y-m-d')!==$date)throw new \InvalidArgumentException('Informe uma data válida.');
    }

    private static function documentDisplay(mixed $type,mixed $number): string
    {
        if(!is_string($type)||!is_string($number)||$number==='')return '—';$digits=preg_replace('/\D+/','',$number)??'';
        if($type==='cpf'&&strlen($digits)===11)return 'CPF · ***.***.'.substr($digits,6,3).'-'.substr($digits,9,2);
        if($type==='cnpj'&&strlen($digits)===14)return 'CNPJ · '.substr($digits,0,2).'.***.***/'.substr($digits,8,4).'-'.substr($digits,12,2);
        return 'Documento cadastrado';
    }
}
