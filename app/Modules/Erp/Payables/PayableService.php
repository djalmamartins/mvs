<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Payables;

use InvalidArgumentException;
use Moves\Services\Platform\PlatformAudit;
use PDO;
use Throwable;

final readonly class PayableService
{
    public function __construct(private PDO $pdo, private PayableRepository $repository, private PlatformAudit $audit) {}

    /** @param array<string,mixed> $data */
    public function create(int $tenantId, int $administratorId, int $actorId, array $data): int
    {
        $description = trim(strip_tags((string) ($data['description'] ?? '')));
        if ($description === '' || mb_strlen($description) > 200) {
            throw new InvalidArgumentException('Informe uma descrição com até 200 caracteres.');
        }
        $supplierId = $this->positiveId($data['supplier_id'] ?? null, 'Selecione um fornecedor ativo do condomínio.');
        $condominiumId = $this->positiveId($data['condominium_id'] ?? null, 'Selecione um condomínio válido.');
        $liabilityAccountId = $this->positiveId($data['liability_account_id'] ?? null, 'Selecione a conta de passivo.');
        $expenseAccountId = $this->positiveId($data['expense_account_id'] ?? null, 'Selecione a conta de despesa.');
        $total = $this->money($data['total_amount'] ?? null);

        $options = $this->repository->options($administratorId);
        if ($this->findOption($options['suppliers'], 'supplier_id', $supplierId, $condominiumId) === null) {
            throw new InvalidArgumentException('Fornecedor sem vínculo ativo com este condomínio.');
        }
        $liability = $this->findOption($options['accounts'], 'account_id', $liabilityAccountId, $condominiumId);
        $expense = $this->findOption($options['accounts'], 'account_id', $expenseAccountId, $condominiumId);
        if ($liability === null || $liability['nature'] !== 'liability') {
            throw new InvalidArgumentException('A conta de obrigação deve ser analítica, ativa e de passivo no plano do condomínio.');
        }
        if ($expense === null || $expense['nature'] !== 'expense') {
            throw new InvalidArgumentException('A conta de despesa deve ser analítica, ativa e pertencer ao plano do condomínio.');
        }
        if ((int) $liability['plan_id'] !== (int) $expense['plan_id']) {
            throw new InvalidArgumentException('As duas contas devem pertencer ao mesmo plano do condomínio.');
        }

        $installments = $this->installments($data['installments'] ?? null, $options['periods'], $condominiumId);
        $sumCents = array_sum(array_map(fn (array $row): int => $this->moneyCents($row['amount']), $installments));
        if ($sumCents !== $this->moneyCents($total)) {
            throw new InvalidArgumentException('A soma das parcelas precisa ser igual ao total da obrigação.');
        }

        $this->pdo->beginTransaction();
        try {
            $id = $this->repository->create(
                $administratorId, $condominiumId, $supplierId, (int) $liability['plan_id'],
                $liabilityAccountId, $expenseAccountId, $description, $total, $actorId, $installments,
            );
            $this->audit->record($tenantId, $actorId, 'erp.payable.created', 'payable', $id, [
                'administrator_id' => $administratorId, 'condominium_id' => $condominiumId,
                'supplier_id' => $supplierId, 'plan_id' => (int) $liability['plan_id'],
                'liability_account_id' => $liabilityAccountId, 'expense_account_id' => $expenseAccountId,
                'total_amount' => $total, 'installment_count' => count($installments),
            ]);
            $this->pdo->commit();
            return $id;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @param array<string,mixed> $filters @return list<array<string,mixed>> */
    public function search(int $administratorId, array $filters): array
    {
        return $this->repository->search($administratorId, [
            'query' => mb_substr(trim((string) ($filters['query'] ?? '')), 0, 100),
            'condominium_id' => isset($filters['condominium_id']) && (int) $filters['condominium_id'] > 0 ? (int) $filters['condominium_id'] : null,
        ]);
    }

    /** @return array<string,mixed>|null */
    public function detail(int $administratorId, int $id): ?array
    {
        $payable = $this->repository->find($administratorId, $id);
        if ($payable !== null) {
            $payable['installments'] = $this->repository->installments($administratorId, $id);
        }
        return $payable;
    }

    private function positiveId(mixed $value, string $message): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT);
        if (!is_int($id) || $id < 1) {
            throw new InvalidArgumentException($message);
        }
        return $id;
    }

    private function money(mixed $value): string
    {
        $value = trim((string) $value);
        if (!preg_match('/^(?:0|[1-9][0-9]{0,10})(?:\.[0-9]{1,2})?$/D', $value)) {
            throw new InvalidArgumentException('Informe valores monetários positivos com até duas casas decimais.');
        }
        if ($this->moneyCents($value) < 1) {
            throw new InvalidArgumentException('O total e cada parcela devem ser maiores que zero.');
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        return $whole . '.' . str_pad($fraction, 2, '0');
    }

    private function moneyCents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    /** @param mixed $raw @param list<array<string,mixed>> $periodOptions @return list<array{accounting_period_id:int,due_date:string,amount:string}> */
    private function installments(mixed $raw, array $periodOptions, int $condominiumId): array
    {
        if (!is_array($raw) || !isset($raw['amount'], $raw['due_date'], $raw['period_id'])
            || !is_array($raw['amount']) || !is_array($raw['due_date']) || !is_array($raw['period_id'])) {
            throw new InvalidArgumentException('Informe pelo menos uma parcela com competência, vencimento e valor.');
        }
        $count = count($raw['amount']);
        if ($count < 1 || $count !== count($raw['due_date']) || $count !== count($raw['period_id']) || $count > 120) {
            throw new InvalidArgumentException('As parcelas precisam ter competência, vencimento e valor preenchidos (até 120 parcelas).');
        }
        $result = [];
        foreach (array_keys($raw['amount']) as $index) {
            $date = trim((string) ($raw['due_date'][$index] ?? ''));
            $parsedDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if ($parsedDate === false || $parsedDate->format('Y-m-d') !== $date) {
                throw new InvalidArgumentException('Informe um vencimento válido para cada parcela.');
            }
            $periodId = $this->positiveId($raw['period_id'][$index] ?? null, 'Selecione uma competência aberta para cada parcela.');
            $period = $this->findOption($periodOptions, 'id', $periodId, $condominiumId);
            if ($period === null) {
                throw new InvalidArgumentException('A competência deve estar aberta no mesmo condomínio da obrigação.');
            }
            $result[] = ['accounting_period_id' => $periodId, 'due_date' => $date, 'amount' => $this->money($raw['amount'][$index] ?? null)];
        }
        return $result;
    }

    /** @param list<array<string,mixed>> $options @return array<string,mixed>|null */
    private function findOption(array $options, string $key, int $id, ?int $condominiumId = null): ?array
    {
        foreach ($options as $option) {
            if ((int) ($option[$key] ?? 0) === $id && ($condominiumId === null || (int) ($option['condominium_id'] ?? 0) === $condominiumId)) {
                return $option;
            }
        }
        return null;
    }
}
