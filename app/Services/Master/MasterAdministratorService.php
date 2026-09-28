<?php

declare(strict_types=1);

namespace Moves\Services\Master;

use Moves\Boot\Connection;
use PDO;
use RuntimeException;
use Throwable;

final class MasterAdministratorService
{
    /** @return array{items:list<array<string,mixed>>,total:int,filtered:int,active:int,inactive:int,page:int,pages:int,per_page:int} */
    public function search(string $query = '', string $status = '', int $page = 1, int $perPage = 25): array
    {
        $pdo=Connection::getInstance();$where=['1=1'];$params=[];$query=mb_substr(trim($query),0,120);
        if($query!==''){$where[]='(a.legal_name LIKE :q1 OR a.trade_name LIKE :q2 OR a.tax_id LIKE :q3 OR t.name LIKE :q4)';$like='%'.$query.'%';$params+=['q1'=>$like,'q2'=>$like,'q3'=>$like,'q4'=>$like];}
        if(in_array($status,['active','inactive','suspended'],true)){$where[]='a.status=:status';$params['status']=$status;}
        $whereSql=implode(' AND ',$where);$page=max(1,$page);$perPage=max(10,min(100,$perPage));
        $count=$pdo->prepare('SELECT COUNT(*) FROM mst_administrators a INNER JOIN talk_tenants t ON t.id=a.tenant_id WHERE '.$whereSql);$count->execute($params);$filtered=(int)$count->fetchColumn();$pages=max(1,(int)ceil($filtered/$perPage));$page=min($page,$pages);$offset=($page-1)*$perPage;
        $sql='SELECT a.tenant_id,a.legal_name,a.trade_name,a.tax_id,a.contact_name,a.contact_email,a.contact_phone,a.status,a.updated_at,t.slug,
        (SELECT COUNT(*) FROM talk_tenant_users tu WHERE tu.tenant_id=a.tenant_id AND tu.status=\'active\') users_count,
        (SELECT COUNT(*) FROM talk_channels ch WHERE ch.tenant_id=a.tenant_id AND ch.status=\'active\') channels_count,
        (SELECT GROUP_CONCAT(p.product_key ORDER BY p.product_key SEPARATOR \', \') FROM mst_tenant_products p WHERE p.tenant_id=a.tenant_id AND p.status=\'active\') products
        FROM mst_administrators a INNER JOIN talk_tenants t ON t.id=a.tenant_id WHERE '.$whereSql.' ORDER BY a.legal_name,a.tenant_id LIMIT '.$perPage.' OFFSET '.$offset;
        $statement=$pdo->prepare($sql);$statement->execute($params);$items=$statement->fetchAll(PDO::FETCH_ASSOC);
        $stats=$pdo->query("SELECT COUNT(*) total,SUM(status='active') active,SUM(status<>'active') inactive FROM mst_administrators")->fetch(PDO::FETCH_ASSOC)?:[];
        return ['items'=>array_values($items),'total'=>(int)($stats['total']??0),'filtered'=>$filtered,'active'=>(int)($stats['active']??0),'inactive'=>(int)($stats['inactive']??0),'page'=>$page,'pages'=>$pages,'per_page'=>$perPage];
    }

    /** @return array<string,mixed>|null */
    public function find(int $tenantId): ?array
    {
        $statement = Connection::getInstance()->prepare('SELECT a.*,t.name tenant_name,t.slug FROM mst_administrators a INNER JOIN talk_tenants t ON t.id=a.tenant_id WHERE a.tenant_id=:id LIMIT 1');
        $statement->execute(['id'=>$tenantId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) return null;
        $row['users'] = $this->count('talk_tenant_users','tenant_id=:id AND status=\'active\'',$tenantId);
        $row['channels'] = $this->count('talk_channels','tenant_id=:id AND status=\'active\'',$tenantId);
        $row['tickets'] = $this->count('talk_tickets','tenant_id=:id',$tenantId);
        $audit = Connection::getInstance()->prepare('SELECT a.event_type,a.payload,a.created_at,u.name actor_name FROM mst_audit a LEFT JOIN users u ON u.id=a.actor_user_id WHERE a.tenant_id=:id ORDER BY a.id DESC LIMIT 50');
        $audit->execute(['id'=>$tenantId]);
        $row['audit'] = $audit->fetchAll(PDO::FETCH_ASSOC);
        $products=Connection::getInstance()->prepare('SELECT product_key,status,updated_at FROM mst_tenant_products WHERE tenant_id=:id ORDER BY product_key');$products->execute(['id'=>$tenantId]);$row['products']=$products->fetchAll(PDO::FETCH_ASSOC);
        $users=Connection::getInstance()->prepare('SELECT u.id,u.name,u.email,u.status global_status,tu.role,tu.status membership_status,tu.is_default,tu.updated_at FROM talk_tenant_users tu INNER JOIN users u ON u.id=tu.user_id WHERE tu.tenant_id=:id ORDER BY u.name');$users->execute(['id'=>$tenantId]);$row['memberships']=$users->fetchAll(PDO::FETCH_ASSOC);
        $channels=Connection::getInstance()->prepare('SELECT id,type,name,driver,status,connection_status,phone_number FROM talk_channels WHERE tenant_id=:id ORDER BY type,name');$channels->execute(['id'=>$tenantId]);$row['integrations']=$channels->fetchAll(PDO::FETCH_ASSOC);
        $security=Connection::getInstance()->prepare('SELECT require_mfa,session_timeout_minutes,allowed_email_domains,updated_at FROM mst_security_settings WHERE tenant_id=:id');$security->execute(['id'=>$tenantId]);$row['security']=$security->fetch(PDO::FETCH_ASSOC)?:['require_mfa'=>0,'session_timeout_minutes'=>480,'allowed_email_domains'=>''];
        $onboarding=Connection::getInstance()->prepare('SELECT step,completed_at,updated_at FROM mst_onboarding WHERE tenant_id=:id');$onboarding->execute(['id'=>$tenantId]);$row['onboarding']=$onboarding->fetch(PDO::FETCH_ASSOC)?:['step'=>'created','completed_at'=>null];
        $onboarding=Connection::getInstance()->prepare('SELECT step,completed_at,updated_at FROM mst_onboarding WHERE tenant_id=:id');$onboarding->execute(['id'=>$tenantId]);$row['onboarding']=$onboarding->fetch(PDO::FETCH_ASSOC)?:['step'=>'created','completed_at'=>null];
        return $row;
    }

    /** @param array<string,string> $data */
    public function create(array $data, int $actorUserId): int
    {
        $data = $this->validate($data);
        $pdo = Connection::getInstance();
        $pdo->beginTransaction();
        try {
            $slug = $this->uniqueSlug($data['trade_name'] !== '' ? $data['trade_name'] : $data['legal_name']);
            $tenant = $pdo->prepare("INSERT INTO talk_tenants(name,slug,status) VALUES(:name,:slug,:status)");
            $tenant->execute(['name'=>$data['trade_name'] !== '' ? $data['trade_name'] : $data['legal_name'],'slug'=>$slug,'status'=>$data['status']==='active'?'active':'inactive']);
            $id = (int)$pdo->lastInsertId();
            $insert = $pdo->prepare('INSERT INTO mst_administrators(tenant_id,legal_name,trade_name,tax_id,contact_name,contact_email,contact_phone,status,notes) VALUES(:tenant_id,:legal_name,:trade_name,:tax_id,:contact_name,:contact_email,:contact_phone,:status,:notes)');
            $insert->execute(['tenant_id'=>$id]+$data);
            $pdo->prepare("INSERT INTO mst_onboarding(tenant_id,step) VALUES(:tenant,'created')")->execute(['tenant'=>$id]);
            foreach(['day','talk','support','erp','cms'] as $product){$pdo->prepare("INSERT INTO mst_tenant_products(tenant_id,product_key,status) VALUES(:tenant,:product,'inactive')")->execute(['tenant'=>$id,'product'=>$product]);}
            $pdo->prepare("INSERT INTO mst_security_settings(tenant_id) VALUES(:tenant)")->execute(['tenant'=>$id]);
            $this->audit($id,$actorUserId,'mst.administrator.created',['status'=>$data['status']]);
            $pdo->commit();
            return $id;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public function updateStatus(int $tenantId,string $status,string $confirmation,string $reason,int $actorUserId): void
    {
        $current=$this->find($tenantId);if($current===null)throw new RuntimeException('Administradora não encontrada.');
        if(!in_array($status,['active','inactive','suspended'],true))throw new RuntimeException('Status inválido.');
        if($status!=='active'&&$confirmation!=='CONFIRMAR')throw new RuntimeException('Digite CONFIRMAR para bloquear o acesso da administradora.');
        $reason=mb_substr(trim(strip_tags($reason)),0,500);if($status!=='active'&&mb_strlen($reason)<5)throw new RuntimeException('Informe o motivo da alteração de status.');
        $pdo=Connection::getInstance();$pdo->beginTransaction();try{$pdo->prepare('UPDATE mst_administrators SET status=:status WHERE tenant_id=:id')->execute(['status'=>$status,'id'=>$tenantId]);$pdo->prepare('UPDATE talk_tenants SET status=:status WHERE id=:id')->execute(['status'=>$status==='active'?'active':'inactive','id'=>$tenantId]);$this->audit($tenantId,$actorUserId,'mst.administrator.status_changed',['previous_status'=>$current['status'],'status'=>$status,'reason'=>$reason]);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    /** @param array<string,string> $data */
    public function update(int $tenantId, array $data, int $actorUserId): void
    {
        $current = $this->find($tenantId);
        if ($current === null) throw new RuntimeException('Administradora não encontrada.');
        $data = $this->validate($data);
        $data['status']=(string)$current['status'];
        $pdo = Connection::getInstance();
        $pdo->beginTransaction();
        try {
            $statement=$pdo->prepare('UPDATE mst_administrators SET legal_name=:legal_name,trade_name=:trade_name,tax_id=:tax_id,contact_name=:contact_name,contact_email=:contact_email,contact_phone=:contact_phone,notes=:notes WHERE tenant_id=:tenant_id');
            $statement->execute(['legal_name'=>$data['legal_name'],'trade_name'=>$data['trade_name'],'tax_id'=>$data['tax_id'],'contact_name'=>$data['contact_name'],'contact_email'=>$data['contact_email'],'contact_phone'=>$data['contact_phone'],'notes'=>$data['notes'],'tenant_id'=>$tenantId]);
            $pdo->prepare('UPDATE talk_tenants SET name=:name WHERE id=:id')->execute(['name'=>$data['trade_name']!==''?$data['trade_name']:$data['legal_name'],'id'=>$tenantId]);
            $this->audit($tenantId,$actorUserId,'mst.administrator.updated',['fields'=>'company_contact']);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /** @param array<string,string> $data @return array<string,string> */
    private function validate(array $data): array
    {
        $clean = [];
        foreach (['legal_name','trade_name','tax_id','contact_name','contact_email','contact_phone','status','notes'] as $key) $clean[$key]=trim(strip_tags((string)($data[$key]??'')));
        if (mb_strlen($clean['legal_name']) < 2) throw new RuntimeException('Informe a razão social.');
        if ($clean['tax_id']==='') throw new RuntimeException('Informe o CNPJ/identificador fiscal.');
        if ($clean['contact_email']!=='' && filter_var($clean['contact_email'],FILTER_VALIDATE_EMAIL)===false) throw new RuntimeException('Informe um e-mail válido.');
        if (!in_array($clean['status'],['active','inactive','suspended'],true)) $clean['status']='active';
        $clean['legal_name']=mb_substr($clean['legal_name'],0,180);$clean['trade_name']=mb_substr($clean['trade_name'],0,160);$clean['tax_id']=mb_substr($clean['tax_id'],0,20);
        $clean['contact_name']=mb_substr($clean['contact_name'],0,120);$clean['contact_email']=mb_substr($clean['contact_email'],0,190);$clean['contact_phone']=mb_substr($clean['contact_phone'],0,40);$clean['notes']=mb_substr($clean['notes'],0,1000);
        return $clean;
    }

    /** @param array<string,string> $branding */
    public function updateBranding(int $tenantId,array $branding,int $actorUserId): void
    {
        if($this->find($tenantId)===null)throw new RuntimeException('Administradora não encontrada.');
        $name=mb_substr(trim(strip_tags((string)($branding['trade_name']??''))),0,160);
        $logo=mb_substr(trim((string)($branding['logo_path']??'')),0,500);
        $primary=strtoupper(trim((string)($branding['primary_color']??'#6E00B3')));$secondary=strtoupper(trim((string)($branding['secondary_color']??'')));
        if(!preg_match('/^#[0-9A-F]{6}$/',$primary))throw new RuntimeException('Cor primária inválida.');
        if($secondary!==''&&!preg_match('/^#[0-9A-F]{6}$/',$secondary))throw new RuntimeException('Cor secundária inválida.');
        Connection::getInstance()->prepare('UPDATE mst_administrators SET trade_name=:name,logo_path=:logo,primary_color=:primary,secondary_color=:secondary WHERE tenant_id=:id')->execute(['name'=>$name,'logo'=>$logo?:null,'primary'=>$primary,'secondary'=>$secondary?:null,'id'=>$tenantId]);
        $this->audit($tenantId,$actorUserId,'mst.branding.updated',['primary_color'=>$primary]);
    }

    public function setProduct(int $tenantId,string $product,string $status,int $actorUserId): void
    {
        if(!in_array($product,['day','talk','support','erp','cms'],true)||!in_array($status,['active','inactive'],true))throw new RuntimeException('Produto ou status inválido.');
        $s=Connection::getInstance()->prepare('INSERT INTO mst_tenant_products(tenant_id,product_key,status) VALUES(:tenant,:product,:status) ON DUPLICATE KEY UPDATE status=VALUES(status),updated_at=NOW()');$s->execute(['tenant'=>$tenantId,'product'=>$product,'status'=>$status]);
        $this->audit($tenantId,$actorUserId,'mst.product.updated',['product'=>$product,'status'=>$status]);
    }

    public function saveMembership(int $tenantId,int $userId,string $role,string $status,int $actorUserId): void
    {
        if($this->find($tenantId)===null)throw new RuntimeException('Administradora não encontrada.');
        if(!in_array($role,['admin','supervisor','agent'],true)||!in_array($status,['active','inactive'],true))throw new RuntimeException('Papel ou status inválido.');
        $exists=Connection::getInstance()->prepare('SELECT id FROM users WHERE id=:id');$exists->execute(['id'=>$userId]);if(!(int)$exists->fetchColumn())throw new RuntimeException('Usuário não encontrado.');
        Connection::getInstance()->prepare('INSERT INTO talk_tenant_users(tenant_id,user_id,role,status,is_default) VALUES(:tenant,:user,:role,:status,0) ON DUPLICATE KEY UPDATE role=VALUES(role),status=VALUES(status),updated_at=NOW()')->execute(['tenant'=>$tenantId,'user'=>$userId,'role'=>$role,'status'=>$status]);
        $this->audit($tenantId,$actorUserId,'mst.membership.updated',['user_id'=>$userId,'role'=>$role,'status'=>$status]);
    }

    /** @param array<string,string> $data */
    public function inviteUser(int $tenantId,array $data,int $actorUserId): int
    {
        if($this->find($tenantId)===null)throw new RuntimeException('Administradora não encontrada.');
        $name=mb_substr(trim(strip_tags((string)($data['name']??''))),0,120);$email=mb_strtolower(mb_substr(trim((string)($data['email']??'')),0,190));$role=(string)($data['role']??'agent');
        if(mb_strlen($name)<2||filter_var($email,FILTER_VALIDATE_EMAIL)===false)throw new RuntimeException('Informe nome e e-mail válidos.');
        if(!in_array($role,['admin','supervisor','agent'],true))throw new RuntimeException('Papel inválido.');
        $pdo=Connection::getInstance();$pdo->beginTransaction();
        try{
            $find=$pdo->prepare('SELECT id,status FROM users WHERE email=:email LIMIT 1');$find->execute(['email'=>$email]);$existing=$find->fetch(PDO::FETCH_ASSOC);
            if(is_array($existing)){$userId=(int)$existing['id'];}
            else{$temporary=bin2hex(random_bytes(24));$insert=$pdo->prepare("INSERT INTO users(name,email,password,role,status) VALUES(:name,:email,:password,'user','active')");$insert->execute(['name'=>$name,'email'=>$email,'password'=>password_hash($temporary,PASSWORD_DEFAULT)]);$userId=(int)$pdo->lastInsertId();}
            $pdo->prepare("INSERT INTO talk_tenant_users(tenant_id,user_id,role,status,is_default) VALUES(:tenant,:user,:role,'active',0) ON DUPLICATE KEY UPDATE role=VALUES(role),status='active',updated_at=NOW()")->execute(['tenant'=>$tenantId,'user'=>$userId,'role'=>$role]);
            $this->audit($tenantId,$actorUserId,'mst.membership.invited',['user_id'=>$userId,'email'=>$email,'role'=>$role,'account_created'=>!is_array($existing)]);$pdo->commit();return $userId;
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    /** @param array<string,mixed> $data */
    public function updateSecurity(int $tenantId,array $data,int $actorUserId): void
    {
        if($this->find($tenantId)===null)throw new RuntimeException('Administradora não encontrada.');
        $mfa=!empty($data['require_mfa'])?1:0;$timeout=max(15,min(1440,(int)($data['session_timeout_minutes']??480)));
        $domains=mb_strtolower(trim((string)($data['allowed_email_domains']??'')));$domains=mb_substr(preg_replace('/[^a-z0-9.,\-\s]/','',$domains)??'',0,1000);
        Connection::getInstance()->prepare('INSERT INTO mst_security_settings(tenant_id,require_mfa,session_timeout_minutes,allowed_email_domains) VALUES(:tenant,:mfa,:timeout,:domains) ON DUPLICATE KEY UPDATE require_mfa=VALUES(require_mfa),session_timeout_minutes=VALUES(session_timeout_minutes),allowed_email_domains=VALUES(allowed_email_domains),updated_at=NOW()')->execute(['tenant'=>$tenantId,'mfa'=>$mfa,'timeout'=>$timeout,'domains'=>$domains?:null]);
        $this->audit($tenantId,$actorUserId,'mst.security.updated',['require_mfa'=>$mfa,'session_timeout_minutes'=>$timeout,'allowed_email_domains'=>$domains]);
    }

    /** @return list<array<string,mixed>> */
    public function availableUsers(int $tenantId): array
    {
        $s=Connection::getInstance()->prepare('SELECT u.id,u.name,u.email FROM users u WHERE u.status=\'active\' AND NOT EXISTS(SELECT 1 FROM talk_tenant_users tu WHERE tu.tenant_id=:tenant AND tu.user_id=u.id) ORDER BY u.name LIMIT 200');$s->execute(['tenant'=>$tenantId]);return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    private function uniqueSlug(string $value): string
    {
        $slug=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value)?:$value;$slug=strtolower(trim((string)preg_replace('/[^a-zA-Z0-9]+/','-',$slug),'-'));$slug=$slug!==''?$slug:'administradora';
        $base=$slug;$i=2;$pdo=Connection::getInstance();$check=$pdo->prepare('SELECT COUNT(*) FROM talk_tenants WHERE slug=:slug');
        while(true){$check->execute(['slug'=>$slug]);if((int)$check->fetchColumn()===0)return $slug;$slug=$base.'-'.$i++;}
    }

    private function audit(int $tenantId,int $actorUserId,string $event,array $payload): void
    {
        Connection::getInstance()->prepare('INSERT INTO mst_audit(tenant_id,actor_user_id,event_type,payload) VALUES(:tenant_id,:actor,:event,:payload)')->execute(['tenant_id'=>$tenantId,'actor'=>$actorUserId,'event'=>$event,'payload'=>json_encode($payload,JSON_THROW_ON_ERROR)]);
    }

    private function count(string $table,string $where,int $id): int
    {
        $s=Connection::getInstance()->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where}");$s->execute(['id'=>$id]);return (int)$s->fetchColumn();
    }
}