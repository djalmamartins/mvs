<?php

declare(strict_types=1);

namespace Moves\Services\Master;

use Moves\Boot\Connection;
use Moves\Services\Talk\TalkTenantContext;
use PDO;
use RuntimeException;

final class TenantProductAccess
{
    public static function currentTenantId(): int
    {
        return (new TalkTenantContext())->currentTenantId();
    }

    public static function enabled(string $productKey, ?int $tenantId = null): bool
    {
        if ($productKey === 'master') {
            return true;
        }
        try {
            $tenantId ??= self::currentTenantId();
        } catch (RuntimeException) {
            return false;
        }
        $statement=Connection::getInstance()->prepare("SELECT status FROM mst_tenant_products WHERE tenant_id=:tenant AND product_key=:product LIMIT 1");
        $statement->execute(['tenant'=>$tenantId,'product'=>$productKey]);
        return $statement->fetchColumn()==='active';
    }

    /** @return array<string,bool> */
    public static function currentEntitlements(): array
    {
        $result=['day'=>false,'talk'=>false,'support'=>false,'erp'=>false,'cms'=>false];
        try{$tenantId=self::currentTenantId();}catch(RuntimeException){return $result;}
        $statement=Connection::getInstance()->prepare("SELECT product_key,status FROM mst_tenant_products WHERE tenant_id=:tenant");
        $statement->execute(['tenant'=>$tenantId]);
        foreach($statement->fetchAll(PDO::FETCH_ASSOC) as $row){$key=(string)$row['product_key'];if(array_key_exists($key,$result)){$result[$key]=(string)$row['status']==='active';}}
        return $result;
    }
}
