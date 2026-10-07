<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Periods;

use PDO;

final readonly class PeriodRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @param array{condominium_id:int|null,year:int|null,status:string} $filters
     *  @return list<array<string,mixed>>
     */
    public function search(int $administratorId, array $filters): array
    {
        $where = ['p.administrator_id = :administrator_id'];
        $params = ['administrator_id' => $administratorId];
        if ($filters['condominium_id'] !== null) {
            $where[] = 'p.condominium_id = :condominium_id';
            $params['condominium_id'] = $filters['condominium_id'];
        }
        if ($filters['year'] !== null) {
            $where[] = 'p.period_year = :period_year';
            $params['period_year'] = $filters['year'];
        }
        if ($filters['status'] !== '') {
            $where[] = 'p.status = :status';
            $params['status'] = $filters['status'];
        }
        $statement = $this->pdo->prepare(
            'SELECT p.id,p.condominium_id,p.period_year,p.period_month,p.status,p.created_at,
                    c.legal_name,c.trade_name
             FROM erp_accounting_periods p
             JOIN erp_condominiums c ON c.administrator_id=p.administrator_id AND c.id=p.condominium_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY p.period_year DESC,p.period_month DESC,p.id DESC'
        );
        $statement->execute($params);
        return array_values($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string,mixed>|null */
    public function find(int $administratorId, int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT p.id,p.condominium_id,p.period_year,p.period_month,p.status,p.created_at,p.updated_at,
                    p.created_by_user_id,p.updated_by_user_id,c.legal_name,c.trade_name,
                    creator.name AS created_by_name,updater.name AS updated_by_name
             FROM erp_accounting_periods p
             JOIN erp_condominiums c ON c.administrator_id=p.administrator_id AND c.id=p.condominium_id
             JOIN users creator ON creator.id=p.created_by_user_id
             LEFT JOIN users updater ON updater.id=p.updated_by_user_id
             WHERE p.administrator_id=:administrator_id AND p.id=:id LIMIT 1'
        );
        $statement->execute(['administrator_id' => $administratorId, 'id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @return list<array<string,mixed>> */
    public function condominiums(int $administratorId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT id,legal_name,trade_name FROM erp_condominiums WHERE administrator_id=:administrator_id AND status='active' ORDER BY legal_name,id"
        );
        $statement->execute(['administrator_id' => $administratorId]);
        return array_values($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function condominiumExists(int $administratorId, int $condominiumId): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT 1 FROM erp_condominiums WHERE administrator_id=:administrator_id AND id=:condominium_id LIMIT 1'
        );
        $statement->execute(['administrator_id' => $administratorId, 'condominium_id' => $condominiumId]);
        return $statement->fetchColumn() !== false;
    }

    public function periodExists(int $administratorId, int $condominiumId, int $year, int $month): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT 1 FROM erp_accounting_periods
             WHERE administrator_id=:administrator_id AND condominium_id=:condominium_id
               AND period_year=:period_year AND period_month=:period_month LIMIT 1'
        );
        $statement->execute([
            'administrator_id' => $administratorId,
            'condominium_id' => $condominiumId,
            'period_year' => $year,
            'period_month' => $month,
        ]);
        return $statement->fetchColumn() !== false;
    }
}
