<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Receivables;

use InvalidArgumentException;
use Moves\Services\Platform\PlatformAudit;
use PDO;
use Throwable;

final readonly class ReceivableService
{
    public function __construct(
        private PDO $pdo,
        private ReceivableRepository $repository,
        private PlatformAudit $audit,
    ) {
    }

    /** @param array<string,mixed> $data */
    public function create(int $tenantId, int $administratorId, int $actorId, array $data): int
    {
        $description = trim(strip_tags((string) ($data['description'] ?? '')));
        if ($description === '' || mb_strlen($description) > 200) {
            throw new InvalidArgumentException('Informe uma descrição com até 200 caracteres.');
        }
        $personLinkId = $this->positiveId($data['person_link_id'] ?? null, 'Selecione a pessoa responsável e a unidade.');
        $receivableAccountId = $this->positiveId($data['receivable_account_id'] ?? null, 'Selecione a conta de recebíveis.');
        $revenueAccountId = $this->positiveId($data['revenue_account_id'] ?? null, 'Selecione a conta de receita.');
        $total = $this->money($data['total_amount'] ?? null);

        $linkOptions = $this->repository->options($administratorId);
        $link = $this->findOption($linkOptions['links'], 'person_link_id', $personLinkId);
        if ($link === null) {
            throw new InvalidArgumentException('A pessoa não possui vínculo ativo com uma unidade desta administradora.');
        }
        $condominiumId = (int) $link['condominium_id'];
        $unitId = (int) $link['unit_id'];

        $asset = $this->findOption($linkOptions['accounts'], 'account_id', $receivableAccountId);
        $revenue = $this->findOption($linkOptions['accounts'], 'account_id', $revenueAccountId);
        if ($asset === null || (int) $asset['condominium_id'] !== $condominiumId || $asset['nature'] !== 'asset') {
            throw new InvalidArgumentException('A conta de recebíveis deve ser analítica, ativa e pertencer ao plano do condomínio.');
        }
        if ($revenue === null || (int) $revenue['condominium_id'] !== $condominiumId || $revenue['nature'] !== 'revenue') {
            throw new InvalidArgumentException('A conta de receita deve ser analítica, ativa e pertencer ao plano do condomínio.');
        }
        if ((int) $asset['plan_id'] !== (int) $revenue['plan_id']) {
            throw new InvalidArgumentException('As duas contas devem pertencer ao mesmo plano do condomínio.');
        }

        $installments = $this->installments($data['installments'] ?? null, $linkOptions['periods'], $condominiumId);
        $sumCents = array_sum(array_map(fn (array $installment): int => $this->moneyCents($installment['amount']), $installments));
        if ($sumCents !== $this->moneyCents($total)) {
            throw new InvalidArgumentException('A soma das parcelas precisa ser igual ao total do título.');
        }

        $this->pdo->beginTransaction();
        try {
            $id = $this->repository->create(
                $administratorId,
                $unitId,
                $personLinkId,
                (int) $asset['plan_id'],
                $receivableAccountId,
                $revenueAccountId,
                $description,
                $total,
                $actorId,
                $installments,
            );
            $this->audit->record($tenantId, $actorId, 'erp.receivable.created', 'receivable', $id, [
                'condominium_id' => $condominiumId,
                'unit_id' => $unitId,
                'person_link_id' => $personLinkId,
                'plan_id' => (int) $asset['plan_id'],
                'receivable_account_id' => $receivableAccountId,
                'revenue_account_id' => $revenueAccountId,
                'total_amount' => $total,
                'installment_count' => count($installments),
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
        $receivable = $this->repository->find($administratorId, $id);
        if ($receivable === null) {
            return null;
        }
        $receivable['installments'] = $this->repository->installments($administratorId, $id);
        return $receivable;
    }

    /** @param mixed $value */
    private function positiveId(mixed $value, string $message): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT);
        if (!is_int($id) || $id < 1) {
            throw new InvalidArgumentException($message);
        }
        return $id;
    }

    /** @param mixed $value */
    private function money(mixed $value): string
    {
        $value = trim((string) $value);
        if (!preg_match('/^(?:0|[1-9][0-9]{0,10})(?:\.[0-9]{1,2})?$/D', $value)) {
            throw new InvalidArgumentException('Informe valores monetários positivos com até duas casas decimais.');
        }
        $cents = $this->moneyCents($value);
        if ($cents < 1) {
            throw new InvalidArgumentException('O valor total e cada parcela devem ser maiores que zero.');
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        return $whole . '.' . str_pad($fraction, 2, '0');
    }

    private function moneyCents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    /** @param mixed $raw @param list<array<string,mixed>> $periodOptions
     *  @return list<array{accounting_period_id:int,due_date:string,amount:string}>
     */
    private function installments(mixed $raw, array $periodOptions, int $condominiumId): array
    {
        if (!is_array($raw) || !isset($raw['amount'], $raw['due_date'], $raw['period_id'])
            || !is_array($raw['amount']) || !is_array($raw['due_date']) || !is_array($raw['period_id'])) {
            throw new InvalidArgumentException('Informe pelo menos uma parcela com competência, vencimento e valor.');
        }
        $count = count($raw['amount']);
        if ($count < 1 || $count !== count($raw['due_date']) || $count !== count($raw['period_id'])) {
            throw new InvalidArgumentException('As parcelas precisam ter competência, vencimento e valor preenchidos.');
        }

        $result = [];
        foreach (array_keys($raw['amount']) as $index) {
            $date = trim((string) ($raw['due_date'][$index] ?? ''));
            $parsedDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if ($parsedDate === false || $parsedDate->format('Y-m-d') !== $date) {
                throw new InvalidArgumentException('Informe um vencimento válido para cada parcela.');
            }
            $periodId = $this->positiveId($raw['period_id'][$index] ?? null, 'Selecione uma competência aberta para cada parcela.');
            $period = $this->findOption($periodOptions, 'id', $periodId);
            if ($period === null || (int) $period['condominium_id'] !== $condominiumId) {
                throw new InvalidArgumentException('A competência deve estar aberta no mesmo condomínio do título.');
            }
            $result[] = [
                'accounting_period_id' => $periodId,
                'due_date' => $date,
                'amount' => $this->money($raw['amount'][$index] ?? null),
            ];
        }
        return $result;
    }

    /** @param list<array<string,mixed>> $options @return array<string,mixed>|null */
    private function findOption(array $options, string $key, int $id): ?array
    {
        foreach ($options as $option) {
            if ((int) ($option[$key] ?? 0) === $id) {
                return $option;
            }
        }
        return null;
    }
}
