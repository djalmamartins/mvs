<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Structure;

use PDO;

final readonly class PhysicalStructureRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<array<string,mixed>> */
    public function listUnits(int $administratorId, array $filters = []): array
    {
        $conditions = ['c.administrator_id=:administrator_id'];
        $params = ['administrator_id'=>$administratorId];
        if (($filters['condominium'] ?? '') !== '') {
            $conditions[] = 'u.condominium_id=:condominium_id';
            $params['condominium_id'] = (int) $filters['condominium'];
        }
        if (($filters['q'] ?? '') !== '') {
            $conditions[] = '(c.legal_name LIKE :legal_name OR c.trade_name LIKE :trade_name OR b.name LIKE :block_name OR u.code LIKE :unit_code OR u.complement LIKE :complement)';
            $needle = '%' . $filters['q'] . '%';
            $params += ['legal_name'=>$needle,'trade_name'=>$needle,'block_name'=>$needle,'unit_code'=>$needle,'complement'=>$needle];
        }
        if (($filters['status'] ?? '') !== '') {
            $conditions[] = 'u.status=:unit_status';
            $params['unit_status'] = $filters['status'];
        }
        $statement = $this->pdo->prepare(
            'SELECT u.id,u.condominium_id,u.block_id,u.code,u.complement,u.status,u.created_at,u.updated_at,
                    c.legal_name AS condominium_legal_name,c.trade_name AS condominium_name,b.name AS block_name
             FROM erp_units u JOIN erp_condominiums c ON c.id=u.condominium_id
             LEFT JOIN erp_blocks b ON b.id=u.block_id AND b.condominium_id=u.condominium_id
             WHERE ' . implode(' AND ', $conditions) . ' ORDER BY c.legal_name,b.name,u.code,u.id LIMIT 500'
        );
        $statement->execute($params);
        return array_values($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string,mixed>|null */
    public function findUnit(int $administratorId, int $unitId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT u.id,u.condominium_id,u.block_id,u.code,u.complement,u.status,u.created_at,u.updated_at,
                    c.legal_name AS condominium_legal_name,c.trade_name AS condominium_name,b.name AS block_name
             FROM erp_units u JOIN erp_condominiums c ON c.id=u.condominium_id
             JOIN erp_administrators a ON a.id=c.administrator_id
             LEFT JOIN erp_blocks b ON b.id=u.block_id AND b.condominium_id=u.condominium_id
             WHERE a.id=:administrator_id AND u.id=:id'
        );
        $statement->execute(['administrator_id'=>$administratorId,'id'=>$unitId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function condominiumExists(int $administratorId, int $condominiumId): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM erp_condominiums WHERE administrator_id=:administrator_id AND id=:id');
        $statement->execute(['administrator_id'=>$administratorId,'id'=>$condominiumId]);
        return $statement->fetchColumn() !== false;
    }

    public function findOrCreateBlock(int $condominiumId, string $name): int
    {
        $statement = $this->pdo->prepare('SELECT id FROM erp_blocks WHERE condominium_id=:condominium_id AND LOWER(name)=LOWER(:name) AND status=\'active\' LIMIT 1');
        $statement->execute(['condominium_id'=>$condominiumId,'name'=>$name]);
        $existing = $statement->fetchColumn();
        if ($existing !== false) {
            return (int) $existing;
        }
        $code = 'B-' . substr(hash('sha256', mb_strtolower($name)), 0, 32);
        $insert = $this->pdo->prepare('INSERT INTO erp_blocks(condominium_id,code,name) VALUES(:condominium_id,:code,:name)');
        $insert->execute(['condominium_id'=>$condominiumId,'code'=>$code,'name'=>$name]);
        return (int) $this->pdo->lastInsertId();
    }

    public function createUnit(int $condominiumId, ?int $blockId, string $code, ?string $complement): int
    {
        $statement = $this->pdo->prepare('INSERT INTO erp_units(condominium_id,block_id,code,complement,status) VALUES(:condominium_id,:block_id,:code,:complement,\'active\')');
        $statement->execute(['condominium_id'=>$condominiumId,'block_id'=>$blockId,'code'=>$code,'complement'=>$complement]);
        return (int) $this->pdo->lastInsertId();
    }
}
