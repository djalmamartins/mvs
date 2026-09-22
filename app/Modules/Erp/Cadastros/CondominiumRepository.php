<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Cadastros;

use PDO;

final class CondominiumRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, administrator_id, legal_name, trade_name, tax_id, status, timezone, created_at, updated_at FROM erp_condominiums WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /** @return list<array<string, mixed>> */
    public function listByAdministrator(int $administratorId): array
    {
        $stmt = $this->pdo->prepare("SELECT id, administrator_id, legal_name, trade_name, tax_id, status, timezone, created_at, updated_at FROM erp_condominiums WHERE administrator_id = :administrator_id AND status = 'active' ORDER BY legal_name, id");
        $stmt->execute(['administrator_id' => $administratorId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_values($rows);
    }

    public function create(int $administratorId, string $legalName, ?string $tradeName, string $taxId, string $timezone = 'America/Sao_Paulo'): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO erp_condominiums (administrator_id, legal_name, trade_name, tax_id, timezone) VALUES (:administrator_id, :legal_name, :trade_name, :tax_id, :timezone)');
        $stmt->execute([
            'administrator_id' => $administratorId,
            'legal_name' => trim($legalName),
            'trade_name' => $tradeName === null ? null : trim($tradeName),
            'tax_id' => trim($taxId),
            'timezone' => trim($timezone),
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
