<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\People;

use PDO;

final class PersonRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM erp_people WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function create(string $fullName, ?string $documentType = null, ?string $documentNumber = null, ?string $email = null, ?string $phone = null): int
    {
        $documentType = $documentType === null ? null : strtolower(trim($documentType));
        $documentNumber = $documentNumber === null ? null : preg_replace('/[^0-9A-Za-z]/', '', $documentNumber);
        if ($documentNumber === '') {
            $documentNumber = null;
        }

        if ($documentType !== null && $documentNumber !== null) {
            $existing = $this->findByDocument($documentType, $documentNumber);
            if ($existing !== null) {
                return (int) $existing['id'];
            }
        }

        $stmt = $this->pdo->prepare('INSERT INTO erp_people (full_name, document_type, document_number, email, phone) VALUES (:full_name, :document_type, :document_number, :email, :phone)');
        $stmt->execute([
            'full_name' => trim($fullName),
            'document_type' => $documentType,
            'document_number' => $documentNumber,
            'email' => $email === null ? null : trim($email),
            'phone' => $phone === null ? null : trim($phone),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    private function findByDocument(string $type, string $number): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM erp_people WHERE document_type = :type AND document_number = :number LIMIT 1');
        $stmt->execute(['type' => $type, 'number' => $number]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }
}