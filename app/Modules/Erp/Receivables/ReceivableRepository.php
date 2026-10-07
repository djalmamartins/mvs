<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Receivables;

use PDO;

final readonly class ReceivableRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array{links:list<array<string,mixed>>,accounts:list<array<string,mixed>>,periods:list<array<string,mixed>>} */
    public function options(int $administratorId): array
    {
        $links = $this->pdo->prepare(
            "SELECT l.id AS person_link_id,l.administrator_id,l.condominium_id,l.unit_id,l.person_id,
                    p.full_name,p.trade_name,u.code AS unit_code,b.name AS block_name,
                    c.legal_name AS condominium_name,COALESCE(c.trade_name,c.legal_name) AS condominium_trade_name
             FROM erp_person_links l
             JOIN erp_people p ON p.administrator_id=l.administrator_id AND p.id=l.person_id AND p.status='active'
             JOIN erp_units u ON u.condominium_id=l.condominium_id AND u.id=l.unit_id AND u.status='active'
             JOIN erp_condominiums c ON c.administrator_id=l.administrator_id AND c.id=l.condominium_id AND c.status='active'
             LEFT JOIN erp_blocks b ON b.condominium_id=u.condominium_id AND b.id=u.block_id
             WHERE l.administrator_id=:administrator_id AND l.unit_id IS NOT NULL AND l.status='active'
               AND l.role IN ('owner','tenant','resident') AND l.starts_at<=CURRENT_DATE
               AND (l.ends_at IS NULL OR l.ends_at>=CURRENT_DATE)
             ORDER BY c.legal_name,u.code,p.full_name,l.id"
        );
        $links->execute(['administrator_id' => $administratorId]);

        $accounts = $this->pdo->prepare(
            "SELECT p.condominium_id,p.id AS plan_id,a.id AS account_id,a.code,a.name,a.nature,a.account_type
             FROM erp_accounting_plans p
             JOIN erp_accounting_accounts a ON a.administrator_id=p.administrator_id AND a.plan_id=p.id
             WHERE p.administrator_id=:administrator_id AND p.status='active' AND a.status='active'
               AND a.account_type='analytic' AND a.nature IN ('asset','revenue')
             ORDER BY p.condominium_id,a.nature,a.code"
        );
        $accounts->execute(['administrator_id' => $administratorId]);

        $periods = $this->pdo->prepare(
            "SELECT id,condominium_id,period_year,period_month
             FROM erp_accounting_periods
             WHERE administrator_id=:administrator_id AND status='open'
             ORDER BY condominium_id,period_year DESC,period_month DESC"
        );
        $periods->execute(['administrator_id' => $administratorId]);

        return [
            'links' => array_values($links->fetchAll(PDO::FETCH_ASSOC)),
            'accounts' => array_values($accounts->fetchAll(PDO::FETCH_ASSOC)),
            'periods' => array_values($periods->fetchAll(PDO::FETCH_ASSOC)),
        ];
    }

    /** @param array{query:string,condominium_id:?int} $filters @return list<array<string,mixed>> */
    public function search(int $administratorId, array $filters): array
    {
        $where = ['r.administrator_id=:administrator_id'];
        $params = ['administrator_id' => $administratorId];
        if ($filters['condominium_id'] !== null) {
            $where[] = 'r.condominium_id=:condominium_id';
            $params['condominium_id'] = $filters['condominium_id'];
        }
        if ($filters['query'] !== '') {
            $where[] = '(r.description LIKE :description OR p.full_name LIKE :person_name OR u.code LIKE :unit_code)';
            $needle = '%' . $filters['query'] . '%';
            $params += ['description' => $needle, 'person_name' => $needle, 'unit_code' => $needle];
        }
        $statement = $this->pdo->prepare(
            'SELECT r.id,r.condominium_id,r.description,r.total_amount,r.created_at,
                    COALESCE(c.trade_name,c.legal_name) AS condominium_name,
                    p.full_name,u.code AS unit_code,b.name AS block_name,
                    COUNT(i.id) AS installment_count,MIN(i.due_date) AS first_due_date,MAX(i.due_date) AS last_due_date
             FROM erp_receivables r
             JOIN erp_condominiums c ON c.administrator_id=r.administrator_id AND c.id=r.condominium_id
             JOIN erp_person_links l ON l.administrator_id=r.administrator_id AND l.id=r.person_link_id
             JOIN erp_people p ON p.administrator_id=l.administrator_id AND p.id=l.person_id
             JOIN erp_units u ON u.condominium_id=r.condominium_id AND u.id=r.unit_id
             LEFT JOIN erp_blocks b ON b.condominium_id=u.condominium_id AND b.id=u.block_id
             LEFT JOIN erp_receivable_installments i ON i.administrator_id=r.administrator_id AND i.receivable_id=r.id
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY r.id,r.condominium_id,r.description,r.total_amount,r.created_at,c.trade_name,c.legal_name,p.full_name,u.code,b.name
             ORDER BY r.created_at DESC,r.id DESC LIMIT 500'
        );
        $statement->execute($params);
        return array_values($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string,mixed>|null */
    public function find(int $administratorId, int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT r.*,COALESCE(c.trade_name,c.legal_name) AS condominium_name,
                    p.full_name,p.trade_name,u.code AS unit_code,b.name AS block_name,
                    rp.name AS plan_name,ra.code AS receivable_account_code,ra.name AS receivable_account_name,
                    va.code AS revenue_account_code,va.name AS revenue_account_name,creator.name AS created_by_name
             FROM erp_receivables r
             JOIN erp_condominiums c ON c.administrator_id=r.administrator_id AND c.id=r.condominium_id
             JOIN erp_person_links l ON l.administrator_id=r.administrator_id AND l.id=r.person_link_id
             JOIN erp_people p ON p.administrator_id=l.administrator_id AND p.id=l.person_id
             JOIN erp_units u ON u.condominium_id=r.condominium_id AND u.id=r.unit_id
             LEFT JOIN erp_blocks b ON b.condominium_id=u.condominium_id AND b.id=u.block_id
             JOIN erp_accounting_plans rp ON rp.administrator_id=r.administrator_id AND rp.id=r.plan_id
             JOIN erp_accounting_accounts ra ON ra.administrator_id=r.administrator_id AND ra.plan_id=r.plan_id AND ra.id=r.receivable_account_id
             JOIN erp_accounting_accounts va ON va.administrator_id=r.administrator_id AND va.plan_id=r.plan_id AND va.id=r.revenue_account_id
             JOIN users creator ON creator.id=r.created_by_user_id
             WHERE r.administrator_id=:administrator_id AND r.id=:id LIMIT 1'
        );
        $statement->execute(['administrator_id' => $administratorId, 'id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @return list<array<string,mixed>> */
    public function installments(int $administratorId, int $receivableId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT i.id,i.installment_number,i.accounting_period_id,i.due_date,i.amount,p.period_year,p.period_month,p.status
             FROM erp_receivable_installments i
             JOIN erp_accounting_periods p ON p.administrator_id=i.administrator_id AND p.id=i.accounting_period_id
             WHERE i.administrator_id=:administrator_id AND i.receivable_id=:receivable_id
             ORDER BY i.installment_number,i.id'
        );
        $statement->execute(['administrator_id' => $administratorId, 'receivable_id' => $receivableId]);
        return array_values($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @param list<array{accounting_period_id:int,due_date:string,amount:string}> $installments */
    public function create(int $administratorId, int $unitId, int $personLinkId, int $planId, int $assetAccountId, int $revenueAccountId, string $description, string $total, int $actorId, array $installments): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO erp_receivables(administrator_id,condominium_id,unit_id,person_link_id,plan_id,receivable_account_id,revenue_account_id,description,total_amount,created_by_user_id)
             SELECT :administrator_id,l.condominium_id,l.unit_id,l.id,:plan_id,:asset_account_id,:revenue_account_id,:description,:total,:actor_id
             FROM erp_person_links l WHERE l.administrator_id=:link_administrator_id AND l.id=:link_id AND l.unit_id=:unit_id'
        );
        $statement->execute([
            'administrator_id' => $administratorId,
            'plan_id' => $planId,
            'asset_account_id' => $assetAccountId,
            'revenue_account_id' => $revenueAccountId,
            'description' => $description,
            'total' => $total,
            'actor_id' => $actorId,
            'link_administrator_id' => $administratorId,
            'link_id' => $personLinkId,
            'unit_id' => $unitId,
        ]);
        $receivableId = (int) $this->pdo->lastInsertId();
        $insert = $this->pdo->prepare(
            'INSERT INTO erp_receivable_installments(administrator_id,condominium_id,receivable_id,installment_number,accounting_period_id,due_date,amount)
             SELECT :administrator_id,r.condominium_id,r.id,:installment_number,:period_id,:due_date,:amount
             FROM erp_receivables r WHERE r.administrator_id=:row_administrator_id AND r.id=:receivable_id'
        );
        foreach ($installments as $index => $installment) {
            $insert->execute([
                'administrator_id' => $administratorId,
                'installment_number' => $index + 1,
                'period_id' => $installment['accounting_period_id'],
                'due_date' => $installment['due_date'],
                'amount' => $installment['amount'],
                'row_administrator_id' => $administratorId,
                'receivable_id' => $receivableId,
            ]);
        }
        return $receivableId;
    }
}
