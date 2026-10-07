<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\ChartOfAccounts;

use PDO;

final readonly class ChartOfAccountsRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<array<string,mixed>> */
    public function searchPlans(int $administratorId, string $query = ''): array
    {
        $sql = 'SELECT p.id,p.condominium_id,p.name,p.status,p.created_at,c.legal_name,c.trade_name,
                       COUNT(a.id) AS account_count
                FROM erp_accounting_plans p
                JOIN erp_condominiums c ON c.administrator_id=p.administrator_id AND c.id=p.condominium_id
                LEFT JOIN erp_accounting_accounts a ON a.administrator_id=p.administrator_id AND a.plan_id=p.id
                WHERE p.administrator_id=:administrator_id';
        $params = ['administrator_id' => $administratorId];
        if ($query !== '') {
            $sql .= ' AND (p.name LIKE :query_name OR c.legal_name LIKE :query_legal OR c.trade_name LIKE :query_trade)';
            $params += ['query_name' => '%' . $query . '%', 'query_legal' => '%' . $query . '%', 'query_trade' => '%' . $query . '%'];
        }
        $sql .= ' GROUP BY p.id,p.condominium_id,p.name,p.status,p.created_at,c.legal_name,c.trade_name ORDER BY c.legal_name,p.id';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return array_values($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string,mixed>> */
    public function condominiumsWithoutPlan(int $administratorId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT c.id,c.legal_name,c.trade_name FROM erp_condominiums c
             LEFT JOIN erp_accounting_plans p ON p.administrator_id=c.administrator_id AND p.condominium_id=c.id
             WHERE c.administrator_id=:administrator_id AND c.status='active' AND p.id IS NULL ORDER BY c.legal_name,c.id"
        );
        $statement->execute(['administrator_id' => $administratorId]);
        return array_values($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string,mixed>|null */
    public function findPlan(int $administratorId, int $planId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT p.id,p.administrator_id,p.condominium_id,p.name,p.status,p.created_at,p.updated_at,
                    p.created_by_user_id,p.updated_by_user_id,c.legal_name,c.trade_name
             FROM erp_accounting_plans p
             JOIN erp_condominiums c ON c.administrator_id=p.administrator_id AND c.id=p.condominium_id
             WHERE p.administrator_id=:administrator_id AND p.id=:plan_id LIMIT 1'
        );
        $statement->execute(['administrator_id' => $administratorId, 'plan_id' => $planId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @param array{query:string,nature:string,status:string} $filters @return list<array<string,mixed>> */
    public function searchAccounts(int $administratorId, int $planId, array $filters): array
    {
        $where = ['administrator_id=:administrator_id', 'plan_id=:plan_id'];
        $params = ['administrator_id' => $administratorId, 'plan_id' => $planId];
        if ($filters['query'] !== '') {
            $where[] = '(code LIKE :code_query OR name LIKE :name_query)';
            $params['code_query'] = '%' . $filters['query'] . '%';
            $params['name_query'] = '%' . $filters['query'] . '%';
        }
        if ($filters['nature'] !== '') {
            $where[] = 'nature=:nature';
            $params['nature'] = $filters['nature'];
        }
        if ($filters['status'] !== '') {
            $where[] = 'status=:status';
            $params['status'] = $filters['status'];
        }
        $statement = $this->pdo->prepare(
            'SELECT id,administrator_id,plan_id,parent_id,code,name,nature,account_type,level,sort_order,status,created_at,updated_at
             FROM erp_accounting_accounts WHERE ' . implode(' AND ', $where) . ' ORDER BY level,sort_order,code,id'
        );
        $statement->execute($params);
        return array_values($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string,mixed>|null */
    public function findAccount(int $administratorId, int $planId, int $accountId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id,administrator_id,plan_id,parent_id,code,name,nature,account_type,level,sort_order,status,created_by_user_id,updated_by_user_id,created_at,updated_at
             FROM erp_accounting_accounts WHERE administrator_id=:administrator_id AND plan_id=:plan_id AND id=:account_id LIMIT 1'
        );
        $statement->execute(['administrator_id' => $administratorId, 'plan_id' => $planId, 'account_id' => $accountId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @return list<array<string,mixed>> */
    public function parentOptions(int $administratorId, int $planId, ?int $excludeId = null): array
    {
        $sql = "SELECT id,parent_id,code,name,nature,account_type,level FROM erp_accounting_accounts
                WHERE administrator_id=:administrator_id AND plan_id=:plan_id AND account_type='synthetic' AND status='active'";
        $params = ['administrator_id' => $administratorId, 'plan_id' => $planId];
        if ($excludeId !== null) {
            $sql .= ' AND id<>:exclude_id';
            $params['exclude_id'] = $excludeId;
        }
        $sql .= ' ORDER BY code,sort_order,id';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return array_values($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function planExists(int $administratorId, int $planId): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM erp_accounting_plans WHERE administrator_id=:administrator_id AND id=:plan_id');
        $statement->execute(['administrator_id' => $administratorId, 'plan_id' => $planId]);
        return $statement->fetchColumn() !== false;
    }
}
