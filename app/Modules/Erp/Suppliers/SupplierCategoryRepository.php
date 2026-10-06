<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Suppliers;

use PDO;

final readonly class SupplierCategoryRepository
{
    private const DEFAULTS = [
        ['Elevadores','elevadores'],['Limpeza','limpeza'],['Segurança','seguranca'],['Engenharia','engenharia'],
        ['Elétrica','eletrica'],['Hidráulica','hidraulica'],['Advocacia','advocacia'],['Contabilidade','contabilidade'],
        ['Manutenção','manutencao'],['Seguros','seguros'],
    ];

    public function __construct(private PDO $pdo) {}

    public function ensureDefaults(int $administratorId): void
    {
        $insert=$this->pdo->prepare('INSERT IGNORE INTO erp_supplier_categories(administrator_id,name,slug) VALUES(?,?,?)');
        foreach(self::DEFAULTS as [$name,$slug]) $insert->execute([$administratorId,$name,$slug]);
    }

    /** @return list<array<string,mixed>> */
    public function all(int $administratorId): array
    {
        $statement=$this->pdo->prepare("SELECT id,name,slug FROM erp_supplier_categories WHERE administrator_id=:administrator_id AND status='active' ORDER BY name");
        $statement->execute(['administrator_id'=>$administratorId]);
        return array_values($statement->fetchAll(PDO::FETCH_ASSOC));
    }
}
