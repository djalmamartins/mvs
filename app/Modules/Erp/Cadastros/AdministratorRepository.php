<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Cadastros;

use PDO;
use Moves\Services\Platform\CompanyService;

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
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $name = trim($legalName);
            $taxId = trim($taxId);
            $tenant = $this->pdo->prepare('INSERT INTO talk_tenants(name,slug,status) VALUES(:name,:slug,\'active\')');
            $tenant->execute(['name' => $name, 'slug' => 'erp-' . substr(hash('sha256', $taxId), 0, 32)]);
            $tenantId = (int) $this->pdo->lastInsertId();
            (new CompanyService($this->pdo))->ensureRoles($tenantId);

            $stmt = $this->pdo->prepare('INSERT INTO erp_administrators (tenant_id,legal_name,trade_name,tax_id) VALUES (:tenant_id,:legal_name,:trade_name,:tax_id)');
            $stmt->execute([
                'tenant_id' => $tenantId,
                'legal_name' => $name,
                'trade_name' => $tradeName === null ? null : trim($tradeName),
                'tax_id' => $taxId,
            ]);
            $administratorId = (int) $this->pdo->lastInsertId();
            $product = $this->pdo->prepare('INSERT INTO platform_tenant_products(tenant_id,product,enabled) VALUES(:tenant_id,\'erp\',1)');
            $product->execute(['tenant_id' => $tenantId]);
            if ($ownsTransaction) {
                $this->pdo->commit();
            }
            return $administratorId;
        } catch (\Throwable $exception) {
            if ($ownsTransaction) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }
}
