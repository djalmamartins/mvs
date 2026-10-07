<?php

declare(strict_types=1);

use Moves\Modules\Erp\People\PersonRepository;
use Moves\Modules\Erp\Security\AdministratorTenantAccess;
use Moves\Modules\Erp\Suppliers\SupplierRepository;
use Moves\Modules\Erp\Suppliers\SupplierService;
use Moves\Services\Platform\PlatformAudit;
use PHPUnit\Framework\TestCase;

final class ErpSupplierServiceTest extends TestCase
{
    private PDO $pdo;
    private SupplierService $service;
    private SupplierRepository $suppliers;

    protected function setUp():void
    {
        $this->pdo=new PDO('sqlite::memory:');$this->pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$this->pdo->exec('PRAGMA foreign_keys=ON');
        $this->pdo->exec('CREATE TABLE users(id INTEGER PRIMARY KEY,name TEXT,email TEXT,password TEXT,status TEXT,role TEXT)');$this->pdo->exec('INSERT INTO users VALUES(1,\'Owner A\',\'a@example.test\',\'x\',\'active\',\'user\'),(2,\'Owner B\',\'b@example.test\',\'x\',\'active\',\'user\')');
        $this->pdo->exec('CREATE TABLE erp_administrators(id INTEGER PRIMARY KEY,tenant_id INTEGER,status TEXT)');$this->pdo->exec("INSERT INTO erp_administrators VALUES(10,100,'active'),(20,200,'active')");
        $this->pdo->exec('CREATE TABLE erp_condominiums(id INTEGER PRIMARY KEY,administrator_id INTEGER,legal_name TEXT,trade_name TEXT,status TEXT,UNIQUE(administrator_id,id),FOREIGN KEY(administrator_id) REFERENCES erp_administrators(id))');$this->pdo->exec("INSERT INTO erp_condominiums VALUES(101,10,'Condo A1','A1','active'),(102,10,'Condo A2','A2','active'),(201,20,'Condo B','B','active')");
        $this->pdo->exec('CREATE TABLE erp_people(id INTEGER PRIMARY KEY AUTOINCREMENT,administrator_id INTEGER,entity_type TEXT,full_name TEXT,trade_name TEXT,document_type TEXT,document_number TEXT,email TEXT,phone TEXT,status TEXT,created_by_user_id INTEGER,UNIQUE(administrator_id,document_type,document_number),UNIQUE(administrator_id,id),FOREIGN KEY(created_by_user_id) REFERENCES users(id))');
        $this->pdo->exec('CREATE TABLE erp_supplier_categories(id INTEGER PRIMARY KEY,administrator_id INTEGER,name TEXT,slug TEXT,status TEXT,UNIQUE(administrator_id,id))');$this->pdo->exec("INSERT INTO erp_supplier_categories VALUES(1,10,'Elevadores','elevadores','active'),(2,10,'Elétrica','eletrica','active'),(3,20,'Limpeza','limpeza','active')");
        $this->pdo->exec('CREATE TABLE erp_suppliers(id INTEGER PRIMARY KEY AUTOINCREMENT,administrator_id INTEGER,person_id INTEGER,category_id INTEGER,status TEXT,created_by_user_id INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(administrator_id,id),UNIQUE(administrator_id,person_id),FOREIGN KEY(administrator_id,person_id) REFERENCES erp_people(administrator_id,id),FOREIGN KEY(administrator_id,category_id) REFERENCES erp_supplier_categories(administrator_id,id),FOREIGN KEY(created_by_user_id) REFERENCES users(id))');
        $this->pdo->exec('CREATE TABLE erp_supplier_condominiums(id INTEGER PRIMARY KEY AUTOINCREMENT,administrator_id INTEGER,supplier_id INTEGER,condominium_id INTEGER,starts_at TEXT,ends_at TEXT,status TEXT,created_by_user_id INTEGER,ended_by_user_id INTEGER,UNIQUE(administrator_id,supplier_id,condominium_id,starts_at),FOREIGN KEY(administrator_id,supplier_id) REFERENCES erp_suppliers(administrator_id,id),FOREIGN KEY(administrator_id,condominium_id) REFERENCES erp_condominiums(administrator_id,id),FOREIGN KEY(created_by_user_id) REFERENCES users(id),FOREIGN KEY(ended_by_user_id) REFERENCES users(id))');
        $this->pdo->exec('CREATE TABLE platform_audit_events(id INTEGER PRIMARY KEY AUTOINCREMENT,tenant_id INTEGER,actor_user_id INTEGER,event_type TEXT,subject_type TEXT,subject_id INTEGER,metadata TEXT)');
        $this->suppliers=new SupplierRepository($this->pdo);$this->service=new SupplierService($this->pdo,new PersonRepository($this->pdo),$this->suppliers,new PlatformAudit($this->pdo));
    }

    public function testCanCreateNaturalPersonSupplierAndAssociateSeveralCondominiums():void
    {
        $result=$this->service->create(100,10,1,['entity_type'=>'person','full_name'=>'Electricista Autônomo','document_type'=>'cpf','document_number'=>'529.982.247-25','category_id'=>'2','condominium_ids'=>['101','102'],'starts_at'=>'2026-01-01']);
        self::assertTrue($result['created_person']);self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM erp_people WHERE entity_type=\'person\'')->fetchColumn());self::assertSame(2,(int)$this->pdo->query('SELECT COUNT(*) FROM erp_supplier_condominiums WHERE administrator_id=10')->fetchColumn());
    }

    public function testCanCreateOrganizationSupplierWithAlphanumericCnpj():void
    {
        $base='CD1234567890';$digit=static function(string $value,array $weights):string{$sum=0;foreach($weights as $index=>$weight)$sum+=(ord($value[$index])-48)*$weight;$remainder=$sum%11;return(string)($remainder<2?0:11-$remainder);};
        $cnpj=$base.$digit($base,[5,4,3,2,9,8,7,6,5,4,3,2]);$cnpj.=$digit($cnpj,[6,5,4,3,2,9,8,7,6,5,4,3,2]);
        $supplier=$this->service->create(100,10,1,['entity_type'=>'organization','full_name'=>'Prestador alfanumérico','document_type'=>'cnpj','document_number'=>strtolower($cnpj),'category_id'=>'1']);
        $person=(new PersonRepository($this->pdo))->find(10,$supplier['person_id']);
        self::assertSame($cnpj,$person['document_number']);
        self::assertSame($supplier['id'],$this->suppliers->find(10,$supplier['id'])['id']);
    }

    public function testCanQualifyExistingOrganizationAndRejectDuplicateDocument():void
    {
        $person=(new PersonRepository($this->pdo))->createOrFind(10,1,['entity_type'=>'organization','full_name'=>'Elevadores XYZ','trade_name'=>null,'document_type'=>'cnpj','document_number'=>'11222333000181','email'=>null,'phone'=>null]);
        $result=$this->service->create(100,10,1,['existing_person_id'=>(string)$person['id'],'category_id'=>'1']);self::assertFalse($result['created_person']);self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM erp_people')->fetchColumn());
        try{$this->service->create(100,10,1,['entity_type'=>'organization','full_name'=>'Nome diferente para o mesmo CNPJ','document_type'=>'cnpj','document_number'=>'11222333000181','category_id'=>'1']);self::fail('A pessoa já qualificada não pode virar fornecedor duplicado.');}catch(InvalidArgumentException $exception){self::assertStringContainsString('já está cadastrada',$exception->getMessage());}
        self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM erp_suppliers')->fetchColumn());
    }

    public function testRejectsInvalidDocumentsAndEveryCrossTenantRelationship():void
    {
        try{$this->service->create(100,10,1,['entity_type'=>'person','full_name'=>'CPF inválido','document_type'=>'cpf','document_number'=>'11111111111','category_id'=>'1']);self::fail('CPF inválido deveria ser recusado.');}catch(InvalidArgumentException $exception){self::assertStringContainsString('CPF válido',$exception->getMessage());}
        $supplier=$this->service->create(100,10,1,['entity_type'=>'organization','full_name'=>'Fornecedor A','document_type'=>'cnpj','document_number'=>'11222333000181','category_id'=>'1']);
        try{$this->service->addCondominium(100,10,1,$supplier['id'],['condominium_id'=>'201','starts_at'=>'2026-01-01']);self::fail('Condomínio de B não deve aceitar fornecedor de A.');}catch(InvalidArgumentException $exception){self::assertStringContainsString('não encontrado',$exception->getMessage());}
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM erp_supplier_condominiums')->fetchColumn());
        try{$this->service->create(200,20,2,['existing_person_id'=>(string)$supplier['person_id'],'category_id'=>'3']);self::fail('Pessoa de A não deve ser selecionável no tenant B.');}catch(InvalidArgumentException $exception){self::assertStringContainsString('Pessoa não encontrada',$exception->getMessage());}
        $supplierB=$this->service->create(200,20,2,['entity_type'=>'person','full_name'=>'Prestador B','document_type'=>'cpf','document_number'=>'52998224725','category_id'=>'3']);
        try{$this->service->addCondominium(200,20,2,$supplierB['id'],['condominium_id'=>'101','starts_at'=>'2026-01-01']);self::fail('Fornecedor B não deve se associar ao condomínio de A.');}catch(InvalidArgumentException $exception){self::assertStringContainsString('não encontrado',$exception->getMessage());}
    }

    public function testEndingCondominiumLinkPreservesItsTemporalHistoryAndAudits():void
    {
        $supplier=$this->service->create(100,10,1,['entity_type'=>'organization','full_name'=>'Fornecedor Temporal','document_type'=>'cnpj','document_number'=>'11222333000181','category_id'=>'1','condominium_ids'=>['101'],'starts_at'=>'2026-01-01']);
        $link=$this->pdo->query('SELECT id FROM erp_supplier_condominiums WHERE administrator_id=10 AND supplier_id='.(int)$supplier['id'])->fetchColumn();

        self::assertTrue($this->service->endCondominium(100,10,1,(int)$supplier['id'],(int)$link,'2026-10-07'));
        $statement=$this->pdo->query('SELECT starts_at,ends_at,status,ended_by_user_id FROM erp_supplier_condominiums WHERE id='.(int)$link);
        self::assertSame(['starts_at'=>'2026-01-01','ends_at'=>'2026-10-07','status'=>'inactive','ended_by_user_id'=>1],$statement->fetch(PDO::FETCH_ASSOC));
        self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM platform_audit_events WHERE event_type='erp.supplier_condominium.ended'")->fetchColumn());
    }
}
