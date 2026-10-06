<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Cadastros;

use PDO;

final readonly class CondominiumReadRepository
{
    public function __construct(private PDO $pdo) {}

    /** @return list<array<string,mixed>> */
    public function search(int $administratorId, array $filters): array
    {
        $where = ['c.administrator_id=:administrator_id'];
        $params = ['administrator_id'=>$administratorId];
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(c.legal_name LIKE :name OR c.trade_name LIKE :trade OR c.tax_id LIKE :tax_id)';
            $needle = '%'.trim((string)$filters['q']).'%';
            $digits = '%'.(preg_replace('/\D+/', '', (string)$filters['q']) ?? '').'%';
            $params += ['name'=>$needle,'trade'=>$needle,'tax_id'=>$digits];
        }
        if (in_array($filters['status'] ?? '', ['active','inactive'], true)) {
            $where[] = 'c.status=:status';
            $params['status'] = $filters['status'];
        }
        $statement = $this->pdo->prepare(
            'SELECT c.id,c.legal_name,c.trade_name,c.tax_id,c.email,c.phone,c.postal_code,c.street,c.address_number,c.complement,c.district,c.city,c.state,c.status,
                    (SELECT COUNT(*) FROM erp_units u WHERE u.condominium_id=c.id) AS unit_count,
                    (SELECT p.full_name FROM erp_person_links l JOIN erp_people p ON p.administrator_id=l.administrator_id AND p.id=l.person_id
                     WHERE l.administrator_id=c.administrator_id AND l.condominium_id=c.id AND l.role=\'manager\' AND l.status=\'active\'
                       AND l.starts_at<=CURRENT_DATE AND (l.ends_at IS NULL OR l.ends_at>=CURRENT_DATE) AND p.status=\'active\'
                     ORDER BY l.starts_at DESC,l.id DESC LIMIT 1) AS manager_name
             FROM erp_condominiums c WHERE '.implode(' AND ',$where).' ORDER BY c.legal_name,c.id LIMIT 500'
        );
        $statement->execute($params);
        return array_values($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string,mixed>|null */
    public function find(int $administratorId, int $id): ?array
    {
        $statement=$this->pdo->prepare('SELECT c.*,(SELECT COUNT(*) FROM erp_units u WHERE u.condominium_id=c.id) AS unit_count FROM erp_condominiums c WHERE c.administrator_id=:administrator_id AND c.id=:id');
        $statement->execute(['administrator_id'=>$administratorId,'id'=>$id]);
        $row=$statement->fetch(PDO::FETCH_ASSOC);
        return $row===false?null:$row;
    }

    /** @return list<array<string,mixed>> */
    public function units(int $administratorId,int $condominiumId): array
    {
        $statement=$this->pdo->prepare('SELECT u.id,u.code,u.complement,u.status,b.name AS block_name FROM erp_units u LEFT JOIN erp_blocks b ON b.condominium_id=u.condominium_id AND b.id=u.block_id WHERE u.condominium_id=:condominium_id AND EXISTS(SELECT 1 FROM erp_condominiums c WHERE c.id=u.condominium_id AND c.administrator_id=:administrator_id) ORDER BY b.name,u.code');
        $statement->execute(['administrator_id'=>$administratorId,'condominium_id'=>$condominiumId]);
        return array_values($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string,mixed>> */
    public function people(int $administratorId,int $condominiumId): array
    {
        $statement=$this->pdo->prepare('SELECT l.id,l.role,l.starts_at,l.ends_at,l.status,p.id AS person_id,p.full_name,p.document_type,p.document_number,p.email,p.phone,u.code AS unit_code FROM erp_person_links l JOIN erp_people p ON p.administrator_id=l.administrator_id AND p.id=l.person_id LEFT JOIN erp_units u ON u.condominium_id=l.condominium_id AND u.id=l.unit_id WHERE l.administrator_id=:administrator_id AND l.condominium_id=:condominium_id AND p.status=\'active\' ORDER BY l.role,l.status DESC,p.full_name,l.starts_at DESC');
        $statement->execute(['administrator_id'=>$administratorId,'condominium_id'=>$condominiumId]);
        return array_values($statement->fetchAll(PDO::FETCH_ASSOC));
    }
}
