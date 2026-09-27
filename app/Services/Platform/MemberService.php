<?php

declare(strict_types=1);

namespace Moves\Services\Platform;

use InvalidArgumentException;
use PDO;

final readonly class MemberService
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<array<string,mixed>> */
    public function all(int $tenantId): array
    {
        $statement = $this->pdo->prepare('SELECT u.id,u.name,u.email,u.status,r.slug role,r.name role_name,m.is_default FROM talk_tenant_users m JOIN users u ON u.id=m.user_id JOIN platform_roles r ON r.id=m.role_id WHERE m.tenant_id=? ORDER BY u.name,u.id');
        $statement->execute([$tenantId]);
        return array_values($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array{id:int,slug:string,name:string}> */
    public function roles(int $tenantId): array
    {
        $statement = $this->pdo->prepare('SELECT id,slug,name FROM platform_roles WHERE tenant_id=? ORDER BY id');
        $statement->execute([$tenantId]);
        return array_values($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function add(int $tenantId, string $email, int $roleId, int $actorId): void
    {
        $email = strtolower(trim($email));
        $role = $this->pdo->prepare('SELECT slug FROM platform_roles WHERE id=? AND tenant_id=?');
        $role->execute([$roleId, $tenantId]);
        $slug = $role->fetchColumn();
        if (!is_string($slug)) {
            throw new InvalidArgumentException('Papel inválido.');
        }
        $user = $this->pdo->prepare("SELECT id FROM users WHERE email=? AND status='active'");
        $user->execute([$email]);
        $userId = (int) $user->fetchColumn();
        if ($userId < 1) {
            throw new InvalidArgumentException('O usuário precisa criar a conta antes de ser vinculado.');
        }
        $statement = $this->pdo->prepare('INSERT INTO talk_tenant_users(tenant_id,user_id,role,role_id,status,is_default) VALUES(?,?,?,? ,\'active\',0) ON DUPLICATE KEY UPDATE role=VALUES(role),role_id=VALUES(role_id),status=\'active\'');
        $statement->execute([$tenantId, $userId, $slug, $roleId]);
        (new PlatformAudit($this->pdo))->record($tenantId, $actorId, 'member.saved', 'user', $userId, ['role' => $slug]);
    }
}
