<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Parties;

use Moves\Modules\Erp\Security\ScopedAccess;
use Moves\Modules\Erp\Security\SecurityAuditRepository;
use Moves\Modules\Erp\Security\SensitiveDataCipher;

final readonly class PartyService
{
    public function __construct(private PartyRepository $parties,private ScopedAccess $access,private ?SecurityAuditRepository $audit=null,private ?SensitiveDataCipher $cipher=null){}

    public function create(int $userId,int $condominiumId,string $kind,string $legalName,?string $tradeName=null,?string $taxId=null,?string $email=null,?string $phone=null): ?int
    {
        if(!$this->allows($userId,'erp.parties.write',$condominiumId)) return null;
        $id=$this->parties->create($condominiumId,$kind,$legalName,$tradeName,$taxId,$email,$phone);
        $this->audit?->append('erp.party.created',$userId,null,['party_id'=>$id,'condominium_id'=>$condominiumId,'kind'=>strtolower(trim($kind))]);
        return $id;
    }

    /** @return list<array<string,mixed>> */
    public function list(int $userId,int $condominiumId): array
    {
        return $this->allows($userId,'erp.parties.read',$condominiumId)?$this->parties->listByCondominium($condominiumId):[];
    }

    /** @param array<string,scalar|null> $bankData */
    public function setBankData(int $userId,int $partyId,int $condominiumId,array $bankData): bool
    {
        if(!$this->allows($userId,'erp.parties.bank.write',$condominiumId)) return false;
        if($this->cipher===null) throw new \RuntimeException('Sensitive data cipher is not configured.');
        $encrypted=$this->cipher->encrypt(json_encode($bankData,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));
        $stored=$this->parties->storeBankData($partyId,$condominiumId,$encrypted['ciphertext'],$encrypted['key_id']);
        if($stored)$this->audit?->append('erp.party.bank_data.updated',$userId,null,['party_id'=>$partyId,'condominium_id'=>$condominiumId]);
        return $stored;
    }

    public function createLink(int $userId,int $partyId,int $condominiumId,string $type,?string $contractReference,string $startsAt,?string $endsAt=null): ?int
    {
        if(!$this->allows($userId,'erp.parties.write',$condominiumId)) return null;
        $id=$this->parties->createLink($partyId,$condominiumId,$type,$contractReference,$startsAt,$endsAt);
        $this->audit?->append('erp.party.link.created',$userId,null,['party_id'=>$partyId,'condominium_id'=>$condominiumId,'link_id'=>$id]);
        return $id;
    }

    public function addReview(int $userId,int $partyId,int $condominiumId,int $rating,?string $notes=null): ?int
    {
        if(!$this->allows($userId,'erp.parties.review.write',$condominiumId)) return null;
        $id=$this->parties->addReview($partyId,$condominiumId,$rating,$notes,$userId);
        $this->audit?->append('erp.party.review.created',$userId,null,['party_id'=>$partyId,'condominium_id'=>$condominiumId,'review_id'=>$id,'rating'=>$rating]);
        return $id;
    }

    private function allows(int $userId,string $capability,int $condominiumId): bool
    {
        return $this->access->allows($userId,$capability,['scope_type'=>'condominium','scope_id'=>$condominiumId]);
    }
}