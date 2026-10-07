<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Periods;

use InvalidArgumentException;
use Moves\Services\Platform\PlatformAudit;
use PDO;
use PDOException;

final readonly class PeriodService
{
    public function __construct(
        private PDO $pdo,
        private PeriodRepository $periods,
        private PlatformAudit $audit
    ) {
    }

    /** @param array<string,mixed> $input */
    public function create(int $tenantId, int $administratorId, int $actorId, array $input): int
    {
        $condominiumId = filter_var($input['condominium_id'] ?? null, FILTER_VALIDATE_INT);
        $month = filter_var($input['month'] ?? null, FILTER_VALIDATE_INT);
        $year = filter_var($input['year'] ?? null, FILTER_VALIDATE_INT);
        if ($condominiumId === false || $condominiumId < 1) {
            throw new InvalidArgumentException('Selecione um condomínio válido.');
        }
        if ($month === false || $month < 1 || $month > 12) {
            throw new InvalidArgumentException('Selecione um mês entre janeiro e dezembro.');
        }
        if ($year === false || $year < 2000 || $year > 2100) {
            throw new InvalidArgumentException('Informe um ano entre 2000 e 2100.');
        }
        if (!$this->periods->condominiumExists($administratorId, $condominiumId)) {
            throw new InvalidArgumentException('O condomínio selecionado não pertence à administradora atual.');
        }
        if ($this->periods->periodExists($administratorId, $condominiumId, $year, $month)) {
            throw new PeriodAlreadyExists('Já existe uma competência aberta para este condomínio e mês.');
        }

        try {
            $this->pdo->beginTransaction();
            $statement = $this->pdo->prepare(
                "INSERT INTO erp_accounting_periods(administrator_id,condominium_id,period_year,period_month,status,created_by_user_id,updated_by_user_id)
                 VALUES(:administrator_id,:condominium_id,:period_year,:period_month,'open',:created_by_user_id,:updated_by_user_id)"
            );
            $statement->execute([
                'administrator_id' => $administratorId,
                'condominium_id' => $condominiumId,
                'period_year' => $year,
                'period_month' => $month,
                'created_by_user_id' => $actorId,
                'updated_by_user_id' => $actorId,
            ]);
            $id = (int) $this->pdo->lastInsertId();
            $this->audit->record($tenantId, $actorId, 'erp.accounting_period.created', 'accounting_period', $id, [
                'administrator_id' => $administratorId,
                'condominium_id' => $condominiumId,
                'year' => $year,
                'month' => $month,
                'status' => 'open',
            ]);
            $this->pdo->commit();
            return $id;
        } catch (PDOException $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if (self::isUniqueViolation($exception)) {
                throw new PeriodAlreadyExists('Já existe uma competência aberta para este condomínio e mês.');
            }
            throw $exception;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private static function isUniqueViolation(PDOException $exception): bool
    {
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        $message = strtolower($exception->getMessage());
        return $driverCode === 1062
            || str_contains($message, 'uq_erp_accounting_periods_month')
            || (str_contains($message, 'unique constraint failed') && str_contains($message, 'erp_accounting_periods'));
    }
}
