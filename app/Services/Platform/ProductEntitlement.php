<?php

declare(strict_types=1);

namespace Moves\Services\Platform;

use PDO;

final readonly class ProductEntitlement
{
    public const PRODUCTS = ['talk', 'erp', 'support', 'cms', 'studio'];

    public function __construct(private PDO $pdo)
    {
    }

    public function enabled(int $tenantId, string $product): bool
    {
        if (!in_array($product, self::PRODUCTS, true)) {
            return false;
        }
        $statement = $this->pdo->prepare(
            "SELECT COUNT(*) FROM platform_tenant_products p
             JOIN talk_tenants t ON t.id=p.tenant_id AND t.status='active'
             WHERE p.tenant_id=:tenant_id AND p.product=:product AND p.enabled=1"
        );
        $statement->execute(['tenant_id' => $tenantId, 'product' => $product]);
        return (int) $statement->fetchColumn() === 1;
    }

    public function set(int $tenantId, string $product, bool $enabled, int $actorId): void
    {
        if (!in_array($product, self::PRODUCTS, true)) {
            throw new \InvalidArgumentException('Produto inválido.');
        }
        $statement = $this->pdo->prepare(
            'INSERT INTO platform_tenant_products(tenant_id,product,enabled) VALUES(:tenant_id,:product,:enabled)
             ON DUPLICATE KEY UPDATE enabled=VALUES(enabled),updated_at=CURRENT_TIMESTAMP'
        );
        $statement->execute(['tenant_id' => $tenantId, 'product' => $product, 'enabled' => $enabled ? 1 : 0]);
        (new PlatformAudit($this->pdo))->record($tenantId, $actorId, $enabled ? 'product.enabled' : 'product.disabled', 'product', null, ['product' => $product]);
    }

    /** @return array<string,bool> */
    public function all(int $tenantId): array
    {
        $result = array_fill_keys(self::PRODUCTS, false);
        $statement = $this->pdo->prepare('SELECT product,enabled FROM platform_tenant_products WHERE tenant_id=:tenant_id');
        $statement->execute(['tenant_id' => $tenantId]);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $product = (string) ($row['product'] ?? '');
            if (isset($result[$product])) {
                $result[$product] = (bool) ($row['enabled'] ?? false);
            }
        }
        return $result;
    }
}
