<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Payables;

use PDO;

final readonly class PayableRepository
{
    public function __construct(private PDO $pdo) {}

    /** @return array{suppliers:list<array<string,mixed>>,accounts:list<array<string,mixed>>,periods:list<array<string,mixed>>} */
    public function options(int $administratorId): array
    {
        $suppliers = $this->pdo->prepare(
            "SELECT DISTINCT s.id AS supplier_id,link.condominium_id,COALESCE(c.trade_name,c.legal_name) AS condominium_name,
                    p.full_name,p.trade_name
             FROM erp_suppliers s
             JOIN erp_people p ON p.administrator_id=s.administrator_id AND p.id=s.person_id AND p.status='active'
             JOIN erp_supplier_condominiums link ON link.administrator_id=s.administrator_id AND link.supplier_id=s.id
             JOIN erp_condominiums c ON c.administrator_id=link.administrator_id AND c.id=link.condominium_id AND c.status='active'
             WHERE s.administrator_id=:administrator_id AND s.status='active' AND link.status='active'
               AND link.starts_at<=CURRENT_DATE AND (link.ends_at IS NULL OR link.ends_at>=CURRENT_DATE)
             ORDER BY condominium_name,p.full_name,s.id"
        );
        $suppliers->execute(['administrator_id' => $administratorId]);

        $accounts = $this->pdo->prepare(
            "SELECT p.condominium_id,p.id AS plan_id,a.id AS account_id,a.code,a.name,a.nature
             FROM erp_accounting_plans p
             JOIN erp_accounting_accounts a ON a.administrator_id=p.administrator_id AND a.plan_id=p.id
             WHERE p.administrator_id=:administrator_id AND p.status='active' AND a.status='active'
               AND a.account_type='analytic' AND a.nature IN ('liability','expense')
             ORDER BY p.condominium_id,a.nature,a.code"
        );
        $accounts->execute(['administrator_id' => $administratorId]);

        $periods = $this->pdo->prepare(
            "SELECT id,condominium_id,period_year,period_month FROM erp_accounting_periods
             WHERE administrator_id=:administrator_id AND status='open'
             ORDER BY condominium_id,period_year DESC,period_month DESC"
        );
        $periods->execute(['administrator_id' => $administratorId]);

        return [
            'suppliers' => array_values($suppliers->fetchAll(PDO::FETCH_ASSOC)),
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
            $where[] = '(r.description LIKE :description OR p.full_name LIKE :supplier_name OR c.legal_name LIKE :condominium_name)';
            $needle = '%' . $filters['query'] . '%';
            $params += ['description' => $needle, 'supplier_name' => $needle, 'condominium_name' => $needle];
        }
        $statement = $this->pdo->prepare(
            'SELECT r.id,r.condominium_id,r.description,r.total_amount,r.created_at,
                    COALESCE(c.trade_name,c.legal_name) AS condominium_name,p.full_name AS supplier_name,
                    COUNT(i.id) AS installment_count,MIN(i.due_date) AS first_due_date,MAX(i.due_date) AS last_due_date
             FROM erp_payables r
             JOIN erp_condominiums c ON c.administrator_id=r.administrator_id AND c.id=r.condominium_id
             JOIN erp_suppliers s ON s.administrator_id=r.administrator_id AND s.id=r.supplier_id
             JOIN erp_people p ON p.administrator_id=s.administrator_id AND p.id=s.person_id
             LEFT JOIN erp_payable_installments i ON i.administrator_id=r.administrator_id AND i.payable_id=r.id
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY r.id,r.condominium_id,r.description,r.total_amount,r.created_at,c.trade_name,c.legal_name,p.full_name
             ORDER BY r.created_at DESC,r.id DESC LIMIT 500'
        );
        $statement->execute($params);
        return array_values($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string,mixed>|null */
    public function find(int $administratorId, int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT r.*,COALESCE(c.trade_name,c.legal_name) AS condominium_name,p.full_name AS supplier_name,
                    plan.name AS plan_name,liability.code AS liability_account_code,liability.name AS liability_account_name,
                    expense.code AS expense_account_code,expense.name AS expense_account_name,creator.name AS created_by_name
             FROM erp_payables r
             JOIN erp_condominiums c ON c.administrator_id=r.administrator_id AND c.id=r.condominium_id
             JOIN erp_suppliers s ON s.administrator_id=r.administrator_id AND s.id=r.supplier_id
             JOIN erp_people p ON p.administrator_id=s.administrator_id AND p.id=s.person_id
             JOIN erp_accounting_plans plan ON plan.administrator_id=r.administrator_id AND plan.id=r.plan_id
             JOIN erp_accounting_accounts liability ON liability.administrator_id=r.administrator_id AND liability.plan_id=r.plan_id AND liability.id=r.liability_account_id
             JOIN erp_accounting_accounts expense ON expense.administrator_id=r.administrator_id AND expense.plan_id=r.plan_id AND expense.id=r.expense_account_id
             JOIN users creator ON creator.id=r.created_by_user_id
             WHERE r.administrator_id=:administrator_id AND r.id=:id LIMIT 1'
        );
        $statement->execute(['administrator_id' => $administratorId, 'id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @return list<array<string,mixed>> */
    public function installments(int $administratorId, int $payableId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT i.id,i.installment_number,i.accounting_period_id,i.due_date,i.amount,p.period_year,p.period_month
             FROM erp_payable_installments i
             JOIN erp_accounting_periods p ON p.administrator_id=i.administrator_id AND p.id=i.accounting_period_id
             WHERE i.administrator_id=:administrator_id AND i.payable_id=:payable_id
             ORDER BY i.installment_number,i.id'
        );
        $statement->execute(['administrator_id' => $administratorId, 'payable_id' => $payableId]);
        return array_values($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @param list<array{accounting_period_id:int,due_date:string,amount:string}> $installments */
    public function create(int $administratorId, int $condominiumId, int $supplierId, int $planId, int $liabilityAccountId, int $expenseAccountId, string $description, string $total, int $actorId, array $installments): int
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO erp_payables(administrator_id,condominium_id,supplier_id,plan_id,liability_account_id,expense_account_id,description,total_amount,created_by_user_id)
             SELECT :administrator_id,link.condominium_id,link.supplier_id,:plan_id,:liability_account_id,:expense_account_id,:description,:total,:actor_id
             FROM erp_supplier_condominiums link
             JOIN erp_suppliers s ON s.administrator_id=link.administrator_id AND s.id=link.supplier_id AND s.status='active'
             JOIN erp_accounting_plans plan ON plan.administrator_id=:plan_administrator_id AND plan.id=:validated_plan_id AND plan.condominium_id=link.condominium_id AND plan.status='active'
             JOIN erp_accounting_accounts liability ON liability.administrator_id=plan.administrator_id AND liability.plan_id=plan.id AND liability.id=:selected_liability_account AND liability.status='active' AND liability.account_type='analytic' AND liability.nature='liability'
             JOIN erp_accounting_accounts expense ON expense.administrator_id=plan.administrator_id AND expense.plan_id=plan.id AND expense.id=:selected_expense_account AND expense.status='active' AND expense.account_type='analytic' AND expense.nature='expense'
             WHERE link.administrator_id=:link_administrator_id AND link.supplier_id=:supplier_id
               AND link.condominium_id=:condominium_id AND link.status='active' AND link.starts_at<=CURRENT_DATE
               AND (link.ends_at IS NULL OR link.ends_at>=CURRENT_DATE)"
        );
        $statement->execute([
            'administrator_id' => $administratorId, 'plan_id' => $planId,
            'liability_account_id' => $liabilityAccountId, 'expense_account_id' => $expenseAccountId,
            'description' => $description, 'total' => $total, 'actor_id' => $actorId,
            'plan_administrator_id' => $administratorId, 'validated_plan_id' => $planId,
            'selected_liability_account' => $liabilityAccountId, 'selected_expense_account' => $expenseAccountId,
            'link_administrator_id' => $administratorId, 'supplier_id' => $supplierId, 'condominium_id' => $condominiumId,
        ]);
        if ($statement->rowCount() !== 1) {
            throw new \InvalidArgumentException('Fornecedor sem vínculo ativo com este condomínio.');
        }
        $payableId = (int) $this->pdo->lastInsertId();
        $insert = $this->pdo->prepare(
            'INSERT INTO erp_payable_installments(administrator_id,condominium_id,payable_id,installment_number,accounting_period_id,due_date,amount)
             SELECT :administrator_id,r.condominium_id,r.id,:installment_number,:period_id,:due_date,:amount
             FROM erp_payables r
             JOIN erp_accounting_periods period ON period.administrator_id=:period_administrator_id
                  AND period.id=:validated_period_id AND period.condominium_id=r.condominium_id AND period.status=\'open\'
             WHERE r.administrator_id=:row_administrator_id AND r.id=:payable_id'
        );
        foreach ($installments as $index => $installment) {
            $insert->execute([
                'administrator_id' => $administratorId, 'installment_number' => $index + 1,
                'period_id' => $installment['accounting_period_id'], 'due_date' => $installment['due_date'],
                'amount' => $installment['amount'], 'row_administrator_id' => $administratorId, 'payable_id' => $payableId,
                'period_administrator_id' => $administratorId, 'validated_period_id' => $installment['accounting_period_id'],
            ]);
            if ($insert->rowCount() !== 1) {
                throw new \InvalidArgumentException('A competência deve permanecer aberta no mesmo condomínio da obrigação.');
            }
        }
        return $payableId;
    }
}
