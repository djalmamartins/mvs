<?php
declare(strict_types=1);
namespace Moves\Services\Talk;
use Moves\Boot\Connection;
use Moves\Core\Session;
use PDO;

final class TalkTenantContext
{
    private const SESSION_KEY='talk_tenant_id';

    public function current(int $userId): ?array
    {
        $allowed=$this->allowed($userId);
        if($allowed===[])return null;
        $selected=(int)Session::get(self::SESSION_KEY,0);
        foreach($allowed as $tenant)if((int)$tenant['id']===$selected)return $tenant;
        Session::set(self::SESSION_KEY,(int)$allowed[0]['id']);
        return $allowed[0];
    }

    public function id(int $userId): int
    {
        $tenant=$this->current($userId);
        if($tenant===null)throw new \RuntimeException('Usuário sem administradora ativa no Talk.');
        return (int)$tenant['id'];
    }

    public function select(int $userId,int $tenantId): bool
    {
        foreach($this->allowed($userId) as $tenant){
            if((int)$tenant['id']===$tenantId){Session::set(self::SESSION_KEY,$tenantId);return true;}
        }
        return false;
    }

    public function allowed(int $userId): array
    {
        $s=Connection::getInstance()->prepare("SELECT t.id,t.name,t.slug,t.logo_url,t.brand_color,tu.role FROM talk_tenant_users tu INNER JOIN talk_tenants t ON t.id=tu.tenant_id WHERE tu.user_id=:user_id AND tu.status='active' AND t.status='active' ORDER BY t.name");
        $s->execute(['user_id'=>$userId]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }
}
