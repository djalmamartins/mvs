<?php

declare(strict_types=1);

namespace Moves\Services\Platform;

use Moves\Boot\Connection;
use Moves\Core\HttpException;
use Moves\Core\Session;
use PDO;

final class TenantContext
{
    public const SESSION_KEY = 'talk_tenant_id';

    public function __construct(private readonly ?PDO $connection = null)
    {
    }

    public function currentId(int $userId): int
    {
        $requested = Session::get(self::SESSION_KEY);
        $tenantId = $this->resolve($userId, is_int($requested) ? $requested : null);
        Session::set(self::SESSION_KEY, $tenantId);
        return $tenantId;
    }

    public function switch(int $userId, int $tenantId): void
    {
        $resolved = $this->resolve($userId, $tenantId);
        Session::set(self::SESSION_KEY, $resolved);
        (new PlatformAudit($this->pdo()))->record($resolved, $userId, 'tenant.switched', 'tenant', $resolved);
    }

    /** @return list<array{id:int,name:string,status:string,role:string,is_default:int}> */
    public function available(int $userId): array
    {
        $statement = $this->pdo()->prepare(
            "SELECT t.id,t.name,t.status,r.slug role,m.is_default
             FROM talk_tenant_users m
             JOIN talk_tenants t ON t.id=m.tenant_id AND t.status='active'
             JOIN users u ON u.id=m.user_id AND u.status='active'
             JOIN platform_roles r ON r.id=m.role_id AND r.tenant_id=t.id
             WHERE m.user_id=:user_id AND m.status='active'
             ORDER BY m.is_default DESC,t.name,t.id"
        );
        $statement->execute(['user_id' => $userId]);
        $tenants = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $tenants[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'status' => (string) $row['status'],
                'role' => (string) $row['role'],
                'is_default' => (int) $row['is_default'],
            ];
        }
        return $tenants;
    }

    private function resolve(int $userId, ?int $requested): int
    {
        $sql = "SELECT m.tenant_id FROM talk_tenant_users m
                JOIN talk_tenants t ON t.id=m.tenant_id AND t.status='active'
                JOIN users u ON u.id=m.user_id AND u.status='active'
                WHERE m.user_id=:user_id AND m.status='active'";
        $params = ['user_id' => $userId];
        if ($requested !== null) {
            $sql .= ' AND m.tenant_id=:tenant_id';
            $params['tenant_id'] = $requested;
        }
        $sql .= ' ORDER BY m.is_default DESC,m.tenant_id LIMIT 1';
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($params);
        $tenantId = (int) ($statement->fetchColumn() ?: 0);
        if ($tenantId < 1) {
            throw new HttpException(403, 'Usuário sem acesso à administradora selecionada.');
        }
        return $tenantId;
    }

    private function pdo(): PDO
    {
        return $this->connection ?? Connection::getInstance();
    }
}
