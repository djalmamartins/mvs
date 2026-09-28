<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Documents;

use PDO;

final class DocumentRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function add(int $condominiumId,string $entityType,int $entityId,string $category,string $title,string $storageKey,string $checksumSha256,int $createdByUserId,?string $mimeType=null,int $versionNo=1): int
    {
        if(!preg_match('/^[a-f0-9]{64}$/i',$checksumSha256)) throw new \InvalidArgumentException('Invalid SHA-256 checksum.');
        if($versionNo<1) throw new \InvalidArgumentException('Document version must be positive.');
        $stmt=$this->pdo->prepare('INSERT INTO erp_documents (condominium_id,entity_type,entity_id,category,title,storage_key,mime_type,checksum_sha256,version_no,created_by_user_id) VALUES (:condominium_id,:entity_type,:entity_id,:category,:title,:storage_key,:mime_type,:checksum,:version_no,:created_by)');
        $stmt->execute(['condominium_id'=>$condominiumId,'entity_type'=>trim($entityType),'entity_id'=>$entityId,'category'=>trim($category),'title'=>trim($title),'storage_key'=>trim($storageKey),'mime_type'=>$mimeType,'checksum'=>strtolower($checksumSha256),'version_no'=>$versionNo,'created_by'=>$createdByUserId]);
        return (int)$this->pdo->lastInsertId();
    }

    /** @return list<array<string,mixed>> */
    public function listForEntity(int $condominiumId,string $entityType,int $entityId): array
    {
        $stmt=$this->pdo->prepare("SELECT id,condominium_id,entity_type,entity_id,category,title,storage_key,mime_type,checksum_sha256,version_no,status,created_by_user_id,created_at FROM erp_documents WHERE condominium_id=:condominium_id AND entity_type=:entity_type AND entity_id=:entity_id AND status='active' ORDER BY created_at DESC,id DESC");
        $stmt->execute(['condominium_id'=>$condominiumId,'entity_type'=>$entityType,'entity_id'=>$entityId]);
        return array_values($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}