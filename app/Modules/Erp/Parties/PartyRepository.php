<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Parties;

use PDO;

final class PartyRepository
{
    private const KINDS = ['supplier', 'employee', 'contractor'];

    public function __construct(private readonly PDO $pdo) {}

    public function create(int $condominiumId, string $kind, string $legalName, ?string $tradeName = null, ?string $taxId = null, ?string $email = null, ?string $phone = null): int
    {
        $kind = strtolower(trim($kind));
        if (!in_array($kind, self::KINDS, true)) throw new \InvalidArgumentException('Unsupported party kind.');
        if (trim($legalName) === '') throw new \InvalidArgumentException('Party legal name is required.');
        $stmt=$this->pdo->prepare('INSERT INTO erp_parties (condominium_id,kind,legal_name,trade_name,tax_id,email,phone) VALUES (:condominium_id,:kind,:legal_name,:trade_name,:tax_id,:email,:phone)');
        $stmt->execute(['condominium_id'=>$condominiumId,'kind'=>$kind,'legal_name'=>trim($legalName),'trade_name'=>$tradeName===null?null:trim($tradeName),'tax_id'=>$taxId===null?null:preg_replace('/[^0-9A-Za-z]/','',$taxId),'email'=>$email===null?null:trim($email),'phone'=>$phone===null?null:trim($phone)]);
        return (int)$this->pdo->lastInsertId();
    }

    /** @return list<array<string,mixed>> */
    public function listByCondominium(int $condominiumId): array
    {
        $stmt=$this->pdo->prepare('SELECT id,condominium_id,kind,legal_name,trade_name,tax_id,email,phone,status,created_at,updated_at FROM erp_parties WHERE condominium_id=:condominium_id ORDER BY legal_name,id');
        $stmt->execute(['condominium_id'=>$condominiumId]);
        return array_values($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function storeBankData(int $partyId,int $condominiumId,string $ciphertext,string $keyId): bool
    {
        $stmt=$this->pdo->prepare('UPDATE erp_parties SET bank_data_ciphertext=:ciphertext,bank_key_version=:key_id WHERE id=:id AND condominium_id=:condominium_id');
        $stmt->execute(['ciphertext'=>$ciphertext,'key_id'=>$keyId,'id'=>$partyId,'condominium_id'=>$condominiumId]);
        return $stmt->rowCount()===1;
    }

    public function createLink(int $partyId,int $condominiumId,string $relationshipType,?string $contractReference,string $startsAt,?string $endsAt=null): int
    {
        if($endsAt!==null && $endsAt<$startsAt) throw new \InvalidArgumentException('Link end date cannot precede start date.');
        $stmt=$this->pdo->prepare('INSERT INTO erp_party_links (party_id,condominium_id,relationship_type,contract_reference,starts_at,ends_at) SELECT id,:condominium_id,:relationship_type,:contract_reference,:starts_at,:ends_at FROM erp_parties WHERE id=:party_id AND condominium_id=:condominium_id');
        $stmt->execute(['party_id'=>$partyId,'condominium_id'=>$condominiumId,'relationship_type'=>trim($relationshipType),'contract_reference'=>$contractReference,'starts_at'=>$startsAt,'ends_at'=>$endsAt]);
        if($stmt->rowCount()!==1) throw new \InvalidArgumentException('Party does not belong to condominium.');
        return (int)$this->pdo->lastInsertId();
    }

    public function addReview(int $partyId,int $condominiumId,int $rating,?string $notes,int $createdByUserId): int
    {
        if($rating<1||$rating>5) throw new \InvalidArgumentException('Rating must be between 1 and 5.');
        $stmt=$this->pdo->prepare('INSERT INTO erp_party_reviews (party_id,condominium_id,rating,notes,created_by_user_id) SELECT id,:condominium_id,:rating,:notes,:created_by FROM erp_parties WHERE id=:party_id AND condominium_id=:condominium_id');
        $stmt->execute(['party_id'=>$partyId,'condominium_id'=>$condominiumId,'rating'=>$rating,'notes'=>$notes,'created_by'=>$createdByUserId]);
        if($stmt->rowCount()!==1) throw new \InvalidArgumentException('Party does not belong to condominium.');
        return (int)$this->pdo->lastInsertId();
    }
}