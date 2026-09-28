<?php

declare(strict_types=1);

namespace Moves\Services\Master;

use Moves\Boot\Connection;
use PDO;
use RuntimeException;

final class TenantBranding
{
    /** @return array{tenant_id:int,name:string,logo_path:string,primary_color:string,secondary_color:string}|null */
    public static function current(): ?array
    {
        try{$tenantId=TenantProductAccess::currentTenantId();}catch(RuntimeException){return null;}
        $statement=Connection::getInstance()->prepare('SELECT tenant_id,trade_name,legal_name,logo_path,primary_color,secondary_color FROM mst_administrators WHERE tenant_id=:tenant LIMIT 1');
        $statement->execute(['tenant'=>$tenantId]);
        $row=$statement->fetch(PDO::FETCH_ASSOC);
        if(!is_array($row)){return null;}
        return [
            'tenant_id'=>$tenantId,
            'name'=>(string)($row['trade_name']?:$row['legal_name']),
            'logo_path'=>(string)($row['logo_path']??''),
            'primary_color'=>(string)($row['primary_color']?:'#6E00B3'),
            'secondary_color'=>(string)($row['secondary_color']??''),
        ];
    }
}
