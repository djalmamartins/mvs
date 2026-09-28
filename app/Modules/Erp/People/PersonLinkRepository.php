<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\People;

use PDO;

final class PersonLinkRepository
{
    private const ROLES = ['owner', 'tenant', 'resident', 'manager', 'council', 'proxy'];

    public function __construct(private readonly PDO $pdo) {}

    public function create(int $personId, int $condominiumId, ?int $unitId, string $role, string $startsAt, ?string $endsAt = null): int
    {
        $role = strtolower(trim($role));
        if (!in_array($role, self::ROLES, true)) {
            throw new \InvalidArgumentException('Unsupported person link role.');
        }
        if ($endsAt !== null && $endsAt < $startsAt) {
            throw new \InvalidArgumentException('Link end date cannot precede start date.');
        }
        if ($unitId !== null && !$this->unitBelongsToCondominium($unitId, $condominiumId)) {
            throw new \InvalidArgumentException('Unit does not belong to condominium.');
        }

        $stmt = $this->pdo->prepare('INSERT INTO erp_person_links (person_id, condominium_id, unit_id, role, starts_at, ends_at) VALUES (:person_id, :condominium_id, :unit_id, :role, :starts_at, :ends_at)');
        $stmt->execute([
            'person_id' => $personId,
            'condominium_id' => $condominiumId,
            'unit_id' => $unitId,
            'role' => $role,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function timeline(int $personId, int $condominiumId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM erp_person_links WHERE person_id = :person_id AND condominium_id = :condominium_id ORDER BY starts_at DESC, id DESC');
        $stmt->execute(['person_id' => $personId, 'condominium_id' => $condominiumId]);
        return array_values($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function unitBelongsToCondominium(int $unitId, int $condominiumId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM erp_units WHERE id = :id AND condominium_id = :condominium_id');
        $stmt->execute(['id' => $unitId, 'condominium_id' => $condominiumId]);
        return $stmt->fetchColumn() !== false;
    }
}