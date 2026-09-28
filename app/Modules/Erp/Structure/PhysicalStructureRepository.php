<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Structure;

use PDO;

final class PhysicalStructureRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /** @return list<array<string, mixed>> */
    public function listBlocks(int $condominiumId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM erp_blocks WHERE condominium_id = :condominium_id AND status = 'active' ORDER BY name, id");
        $stmt->execute(['condominium_id' => $condominiumId]);
        return array_values($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string, mixed>> */
    public function listUnits(int $condominiumId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM erp_units WHERE condominium_id = :condominium_id AND status = 'active' ORDER BY code, id");
        $stmt->execute(['condominium_id' => $condominiumId]);
        return array_values($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function createBlock(int $condominiumId, string $code, string $name): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO erp_blocks (condominium_id, code, name) VALUES (:condominium_id, :code, :name)');
        $stmt->execute(['condominium_id'=>$condominiumId, 'code'=>trim($code), 'name'=>trim($name)]);
        return (int) $this->pdo->lastInsertId();
    }

    public function createUnit(int $condominiumId, ?int $blockId, string $code, float $idealFraction = 0.0): int
    {
        if ($idealFraction < 0 || $idealFraction > 1) {
            throw new \InvalidArgumentException('Ideal fraction must be between 0 and 1.');
        }
        if ($blockId !== null && !$this->blockBelongsToCondominium($blockId, $condominiumId)) {
            throw new \InvalidArgumentException('Block does not belong to condominium.');
        }
        $stmt = $this->pdo->prepare('INSERT INTO erp_units (condominium_id, block_id, code, ideal_fraction) VALUES (:condominium_id, :block_id, :code, :ideal_fraction)');
        $stmt->execute(['condominium_id'=>$condominiumId, 'block_id'=>$blockId, 'code'=>trim($code), 'ideal_fraction'=>$idealFraction]);
        return (int) $this->pdo->lastInsertId();
    }

    private function blockBelongsToCondominium(int $blockId, int $condominiumId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM erp_blocks WHERE id = :id AND condominium_id = :condominium_id');
        $stmt->execute(['id'=>$blockId, 'condominium_id'=>$condominiumId]);
        return $stmt->fetchColumn() !== false;
    }
}