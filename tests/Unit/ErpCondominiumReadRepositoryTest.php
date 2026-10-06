<?php

declare(strict_types=1);

use Moves\Modules\Erp\Cadastros\CondominiumReadRepository;
use PHPUnit\Framework\TestCase;

final class ErpCondominiumReadRepositoryTest extends TestCase
{
    private PDO $pdo;
    private CondominiumReadRepository $repository;

    protected function setUp():void
    {
        $this->pdo=new PDO('sqlite::memory:');$this->pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE erp_condominiums(id INTEGER PRIMARY KEY,administrator_id INTEGER,legal_name TEXT,trade_name TEXT,tax_id TEXT,email TEXT,phone TEXT,postal_code TEXT,street TEXT,address_number TEXT,complement TEXT,district TEXT,city TEXT,state TEXT,status TEXT)');
        $this->pdo->exec("INSERT INTO erp_condominiums VALUES(1,10,'Residencial Alfa','Alfa','11222333000181','sindico@example.test','11999990000','01000000','Rua A','10',NULL,'Centro','São Paulo','SP','active'),(2,20,'Residencial Beta','Beta','11444777000161',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'active')");
        $this->pdo->exec('CREATE TABLE erp_units(id INTEGER PRIMARY KEY,condominium_id INTEGER,block_id INTEGER,code TEXT,complement TEXT,status TEXT)');$this->pdo->exec("INSERT INTO erp_units VALUES(1,1,NULL,'101',NULL,'active'),(2,1,NULL,'102',NULL,'active'),(3,2,NULL,'201',NULL,'active')");
        $this->pdo->exec('CREATE TABLE erp_blocks(id INTEGER PRIMARY KEY,condominium_id INTEGER,name TEXT)');
        $this->pdo->exec('CREATE TABLE erp_people(id INTEGER PRIMARY KEY,administrator_id INTEGER,full_name TEXT,document_type TEXT,document_number TEXT,email TEXT,phone TEXT,status TEXT)');$this->pdo->exec("INSERT INTO erp_people VALUES(1,10,'Síndica Alfa',NULL,NULL,NULL,NULL,'active'),(2,20,'Síndico Beta',NULL,NULL,NULL,NULL,'active')");
        $this->pdo->exec('CREATE TABLE erp_person_links(id INTEGER PRIMARY KEY,administrator_id INTEGER,person_id INTEGER,condominium_id INTEGER,unit_id INTEGER,role TEXT,starts_at TEXT,ends_at TEXT,status TEXT)');$this->pdo->exec("INSERT INTO erp_person_links VALUES(1,10,1,1,NULL,'manager','2025-01-01',NULL,'active'),(2,20,2,2,NULL,'manager','2025-01-01',NULL,'active')");
        $this->repository=new CondominiumReadRepository($this->pdo);
    }

    public function testSearchCountsUnitsAndResolvesCurrentManagerWithinAdministrator():void
    {
        $rows=$this->repository->search(10,['q'=>'Alfa','status'=>'active']);self::assertCount(1,$rows);self::assertSame(2,(int)$rows[0]['unit_count']);self::assertSame('Síndica Alfa',$rows[0]['manager_name']);self::assertNull($this->repository->find(10,2));
        self::assertCount(0,$this->repository->search(10,['q'=>'11444777000161','status'=>'']));
    }
}
