<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Cadastros;

use PDO;

final class AdministratorRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, legal_name, trade_name, tax_id, status, created_at, updated_at FROM erp_administrators WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /** @return list<array<string, mixed>> */
    public function listActive(): array
    {
        $stmt = $this->pdo->query("SELECT id, legal_name, trade_name, tax_id, status, created_at, updated_at FROM erp_administrators WHERE status = 'active' ORDER BY legal_name, id");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_values($rows);
    }

    public function create(string $legalName, ?string $tradeName, string $taxId): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO erp_administrators (legal_name, trade_name, tax_id) VALUES (:legal_name, :trade_name, :tax_id)');
        $stmt->execute([
            'legal_name' => trim($legalName),
            'trade_name' => $tradeName === null ? null : trim($tradeName),
            'tax_id' => trim($taxId),
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
