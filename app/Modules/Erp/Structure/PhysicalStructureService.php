<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Structure;

use InvalidArgumentException;
use Moves\Services\Platform\PlatformAudit;
use PDO;
use Throwable;

final readonly class PhysicalStructureService
{
    public function __construct(
        private PDO $pdo,
        private PhysicalStructureRepository $repository,
        private PlatformAudit $audit,
    ) {
    }

    /** @param array{condominium_id:mixed,block:mixed,code:mixed,complement?:mixed} $data */
    public function createUnit(int $tenantId, int $administratorId, int $actorId, array $data): int
    {
        $condominiumId = filter_var($data['condominium_id'], FILTER_VALIDATE_INT);
        $code = trim(strip_tags((string) $data['code']));
        $blockName = trim(strip_tags((string) $data['block']));
        $complement = trim(strip_tags((string) ($data['complement'] ?? ''))) ?: null;
        if (!is_int($condominiumId) || $condominiumId < 1 || !$this->repository->condominiumExists($administratorId, $condominiumId)) {
            throw new InvalidArgumentException('Selecione um condomínio da administradora atual.');
        }
        if ($code === '' || mb_strlen($code) > 40) {
            throw new InvalidArgumentException('Informe um identificador de unidade com até 40 caracteres.');
        }
        if (mb_strlen($blockName) > 120 || ($complement !== null && mb_strlen($complement) > 120)) {
            throw new InvalidArgumentException('Bloco e complemento aceitam até 120 caracteres.');
        }
        $this->pdo->beginTransaction();
        try {
            $blockId = $blockName === '' ? null : $this->repository->findOrCreateBlock($condominiumId, $blockName);
            $unitId = $this->repository->createUnit($condominiumId, $blockId, $code, $complement);
            $this->audit->record($tenantId, $actorId, 'erp.unit.created', 'unit', $unitId, ['condominium_id'=>$condominiumId,'block_id'=>$blockId]);
            $this->pdo->commit();
            return $unitId;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @param array{code?:mixed,complement?:mixed} $data */
    public function updateUnit(int $tenantId, int $administratorId, int $actorId, int $unitId, array $data): bool
    {
        $rawCode = $data['code'] ?? null;
        $rawComplement = $data['complement'] ?? '';
        if (!is_string($rawCode) || !is_string($rawComplement)) {
            throw new InvalidArgumentException('Identificador e complemento devem ser texto.');
        }
        $code = trim(strip_tags($rawCode));
        $complement = trim(strip_tags($rawComplement)) ?: null;
        if ($code === '' || mb_strlen($code) > 40) {
            throw new InvalidArgumentException('Informe um identificador de unidade com até 40 caracteres.');
        }
        if ($complement !== null && mb_strlen($complement) > 120) {
            throw new InvalidArgumentException('O complemento aceita até 120 caracteres.');
        }

        $this->pdo->beginTransaction();
        try {
            $unit = $this->repository->findUnit($administratorId, $unitId, true);
            if ($unit === null) {
                $this->pdo->commit();
                return false;
            }
            if ($this->repository->unitCodeExists($administratorId, (int) $unit['condominium_id'], $code, $unitId)) {
                throw new InvalidArgumentException('Já existe uma unidade com este identificador neste condomínio.');
            }

            $before = ['code' => (string) $unit['code'], 'complement' => $unit['complement']];
            $after = ['code' => $code, 'complement' => $complement];
            if ($before === $after) {
                $this->pdo->commit();
                return true;
            }
            if (!$this->repository->updateUnit($administratorId, $unitId, $code, $complement)) {
                throw new \RuntimeException('A unidade deixou de estar disponível para esta administradora.');
            }
            $this->audit->record($tenantId, $actorId, 'erp.unit.updated', 'unit', $unitId, [
                'condominium_id' => (int) $unit['condominium_id'],
                'block_id' => $unit['block_id'] === null ? null : (int) $unit['block_id'],
                'changes' => ['before' => $before, 'after' => $after],
            ]);
            $this->pdo->commit();
            return true;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @return list<array<string,mixed>> */
    public function units(int $administratorId, array $filters = []): array
    {
        return $this->repository->listUnits($administratorId, $filters);
    }

    /** @return array<string,mixed>|null */
    public function unit(int $administratorId, int $unitId): ?array
    {
        return $this->repository->findUnit($administratorId, $unitId);
    }
}
