<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Security;

use Moves\Boot\Connection;
use Moves\Core\Auth;
use Moves\Core\HttpException;
use Moves\Services\Platform\TenantContext;
use PDO;

/** Resolves the authenticated user's currently selected, ERP-authorized tenant. */
final class ErpTenantContext
{
    /** @return array{pdo:PDO,tenant_id:int,administrator_id:int,user_id:int} */
    public static function current(): array
    {
        $user=Auth::user();if($user===null)throw new HttpException(401,'Autenticação necessária.');
        $pdo=Connection::getInstance();$userId=(int)$user->id;$tenantId=(new TenantContext($pdo))->currentId($userId);
        if(!(new AdministratorTenantAccess($pdo))->hasAdministrator($userId,$tenantId))throw new HttpException(403,'ERP indisponível para este usuário.');
        $statement=$pdo->prepare("SELECT id FROM erp_administrators WHERE tenant_id=:tenant_id AND status='active' ORDER BY id LIMIT 1");$statement->execute(['tenant_id'=>$tenantId]);$administratorId=(int)$statement->fetchColumn();
        if($administratorId<1)throw new HttpException(403,'Administradora ativa não encontrada.');
        return ['pdo'=>$pdo,'tenant_id'=>$tenantId,'administrator_id'=>$administratorId,'user_id'=>$userId];
    }
}
