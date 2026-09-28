<?php
declare(strict_types=1);
namespace Moves\Modules\Erp\Structure;
use Moves\Modules\Erp\Security\ScopedAccess;
final readonly class PhysicalStructureService {
 public function __construct(private PhysicalStructureRepository $repository, private ScopedAccess $access) {}
 public function createUnit(int $userId,int $condominiumId,?int $blockId,string $code,float $idealFraction=0.0): ?int {
  if(!$this->access->allows($userId,'erp.structure.write',['scope_type'=>'condominium','scope_id'=>$condominiumId])) return null;
  return $this->repository->createUnit($condominiumId,$blockId,$code,$idealFraction);
 }
 public function createParkingSpace(int $userId,int $condominiumId,?int $unitId,string $code,string $kind='vehicle'): ?int {
  if(!$this->access->allows($userId,'erp.structure.write',['scope_type'=>'condominium','scope_id'=>$condominiumId])) return null;
  return $this->repository->createParkingSpace($condominiumId,$unitId,$code,$kind);
 }
 public function createCommonArea(int $userId,int $condominiumId,string $code,string $name,bool $reservable=false,?int $capacity=null): ?int {
  if(!$this->access->allows($userId,'erp.structure.write',['scope_type'=>'condominium','scope_id'=>$condominiumId])) return null;
  return $this->repository->createCommonArea($condominiumId,$code,$name,$reservable,$capacity);
 }
 /** @return list<array<string,mixed>> */
 public function listUnits(int $userId,int $condominiumId): array {
  if(!$this->access->allows($userId,'erp.structure.read',['scope_type'=>'condominium','scope_id'=>$condominiumId])) return [];
  return $this->repository->listUnits($condominiumId);
 }
 /** @param list<array{code:string,block_id?:int|null,ideal_fraction?:float|int}> $rows @return array{created:list<int>,errors:list<array{row:int,message:string}>} */
 public function importUnits(int $userId,int $condominiumId,array $rows): array {
  if(!$this->access->allows($userId,'erp.structure.write',['scope_type'=>'condominium','scope_id'=>$condominiumId])) return ['created'=>[],'errors'=>[['row'=>0,'message'=>'forbidden']]];
  $created=[];$errors=[];
  foreach($rows as $index=>$row){try{
   $code=trim($row['code']); if($code==='') throw new \InvalidArgumentException('Unit code is required.');
   $created[]=$this->repository->createUnit($condominiumId,isset($row['block_id'])?(int)$row['block_id']:null,$code,(float)($row['ideal_fraction']??0));
  }catch(\Throwable $e){$errors[]=['row'=>$index+1,'message'=>$e->getMessage()];}}
  return ['created'=>$created,'errors'=>$errors];
 }
}