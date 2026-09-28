<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Parties;

use PDO;

final class PartyRepository
{
    private const KINDS = ['supplier', 'employee', 'contractor'];

    public function __construct(private readonly PDO $pdo) {}

    public function create(int $condominiumId, string $kind, string $legalName, ?string $tradeName = null, ?string $taxId = null, ?string $email = null, ?string $phone = null): int
    {
        $kind = strtolower(trim($kind));
        if (!in_array($kind, self::KINDS, true)) {
            throw new \InvalidArgumentException('Unsupported party kind.');
        }
        if (trim($legalName) === '') {
            throw new \InvalidArgumentException('Party legal name is required.');
        }

        $stmt = $this->pdo->prepare('INSERT INTO erp_parties (condominium_id, kind, legal_name, trade_name, tax_id, email, phone) VALUES (:condominium_id, :kind, :legal_name, :trade_name, :tax_id, :email, :phone)');
        $stmt->execute([
            'condominium_id' => $condominiumId,
            'kind' => $kind,
            'legal_name' => trim($legalName),
            'trade_name' => $tradeName === null ? null : trim($tradeName),
            'tax_id' => $taxId === null ? null : preg_replace('/[^0-9A-Za-z]/', '', $taxId),
            'email' => $email === null ? null : trim($email),
            'phone' => $phone === null ? null : trim($phone),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function listByCondominium(int $condominiumId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, condominium_id, kind, legal_name, trade_name, tax_id, email, phone, status, created_at, updated_at FROM erp_parties WHERE condominium_id = :condominium_id ORDER BY legal_name, id');
        $stmt->execute(['condominium_id' => $condominiumId]);
        return array_values($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}