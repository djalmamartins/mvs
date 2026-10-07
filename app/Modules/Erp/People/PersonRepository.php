<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\People;

use PDO;
use PDOException;
use Moves\Services\Platform\Cnpj;

final readonly class PersonRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<string,mixed>|null */
    public function find(int $administratorId, int $personId): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM erp_people WHERE administrator_id=:administrator_id AND id=:id');
        $statement->execute(['administrator_id'=>$administratorId,'id'=>$personId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /** @param array{q:string,status:string,condominium:string,role:string} $filters
     *  @return list<array<string,mixed>>
     */
    public function search(int $administratorId, array $filters): array
    {
        $conditions = ['p.administrator_id=:administrator_id'];
        $params = ['administrator_id'=>$administratorId];
        if ($filters['q'] !== '') {
            $conditions[] = '(p.full_name LIKE :name_query OR p.trade_name LIKE :trade_query OR p.document_number LIKE :document_query OR p.email LIKE :email_query OR p.phone LIKE :phone_query)';
            $needle = '%' . $filters['q'] . '%';
            $query = trim($filters['q']);
            $document = '%' . (Cnpj::normalize($query) ?? (preg_replace('/\D+/', '', $query) ?? '')) . '%';
            $params += ['name_query'=>$needle,'trade_query'=>$needle,'document_query'=>$document,'email_query'=>$needle,'phone_query'=>$needle];
        }
        if ($filters['status'] !== '') {
            $conditions[] = 'p.status=:person_status';
            $params['person_status'] = $filters['status'];
        }
        if ($filters['condominium'] !== '' || $filters['role'] !== '') {
            $linkConditions = [
                'l.administrator_id=p.administrator_id',
                'l.person_id=p.id',
                "l.status='active'",
                'l.starts_at<=CURRENT_DATE',
                '(l.ends_at IS NULL OR l.ends_at>=CURRENT_DATE)',
            ];
            if ($filters['condominium'] !== '') {
                $linkConditions[] = 'l.condominium_id=:filter_condominium';
                $params['filter_condominium'] = (int) $filters['condominium'];
            }
            if ($filters['role'] !== '') {
                $linkConditions[] = 'l.role=:filter_role';
                $params['filter_role'] = $filters['role'];
            }
            $conditions[] = 'EXISTS (SELECT 1 FROM erp_person_links l WHERE ' . implode(' AND ', $linkConditions) . ')';
        }
        $statement = $this->pdo->prepare(
            'SELECT p.id,p.administrator_id,p.entity_type,p.full_name,p.trade_name,p.document_type,p.document_number,p.email,p.phone,p.status,p.created_at,p.updated_at
             FROM erp_people p WHERE ' . implode(' AND ', $conditions) . ' ORDER BY p.full_name,p.id LIMIT 250'
        );
        $statement->execute($params);
        return array_values($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array{id:int,created:bool} */
    public function createOrFind(int $administratorId, int $actorId, array $person): array
    {
        if ($person['document_type'] !== null && $person['document_number'] !== null) {
            $existing = $this->findByDocument($administratorId, $person['document_type'], $person['document_number']);
            if ($existing !== null) {
                return ['id'=>(int) $existing['id'],'created'=>false];
            }
        }
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO erp_people(administrator_id,entity_type,full_name,trade_name,document_type,document_number,email,phone,status,created_by_user_id)
                 VALUES(:administrator_id,:entity_type,:full_name,:trade_name,:document_type,:document_number,:email,:phone,\'active\',:created_by_user_id)'
            );
            $statement->execute([
                'administrator_id'=>$administratorId,
                'entity_type'=>$person['entity_type'],
                'full_name'=>$person['full_name'],
                'trade_name'=>$person['trade_name'],
                'document_type'=>$person['document_type'],
                'document_number'=>$person['document_number'],
                'email'=>$person['email'],
                'phone'=>$person['phone'],
                'created_by_user_id'=>$actorId,
            ]);
            return ['id'=>(int) $this->pdo->lastInsertId(),'created'=>true];
        } catch (PDOException $exception) {
            if ($person['document_type'] !== null && $person['document_number'] !== null) {
                $existing = $this->findByDocument($administratorId, $person['document_type'], $person['document_number']);
                if ($existing !== null) {
                    return ['id'=>(int) $existing['id'],'created'=>false];
                }
            }
            throw $exception;
        }
    }

    /** @return array<string,mixed>|null */
    private function findByDocument(int $administratorId, string $type, string $number): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id,administrator_id FROM erp_people WHERE administrator_id=:administrator_id AND document_type=:document_type AND document_number=:document_number LIMIT 1'
        );
        $statement->execute(['administrator_id'=>$administratorId,'document_type'=>$type,'document_number'=>$number]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }
}
