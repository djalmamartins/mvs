<?php

declare(strict_types=1);

namespace Moves\Services\Talk;

use Moves\Boot\Connection;
use Moves\Core\Auth;
use Moves\Core\Session;
use PDO;
use RuntimeException;

final class TalkTenantContext
{
    public const SESSION_KEY = 'talk_tenant_id';

    public function currentTenantId(): int
    {
        $user = Auth::user();
        if ($user === null) {
            throw new RuntimeException('Contexto de empresa indisponível.');
        }

        $requested = Session::get(self::SESSION_KEY);
        return $this->forUser((int) $user->id, is_int($requested) ? $requested : null);
    }

    public function forUser(int $userId, ?int $requestedTenantId = null): int
    {
        $pdo = Connection::getInstance();
        $sql = "SELECT tu.tenant_id FROM talk_tenant_users tu INNER JOIN talk_tenants t ON t.id=tu.tenant_id WHERE tu.user_id=:user_id AND tu.status='active' AND t.status='active'";
        $params = ['user_id' => $userId];
        if ($requestedTenantId !== null) {
            $sql .= ' AND tu.tenant_id=:tenant_id';
            $params['tenant_id'] = $requestedTenantId;
        }
        $sql .= ' ORDER BY tu.is_default DESC,tu.tenant_id LIMIT 1';
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        $tenantId = (int) ($statement->fetchColumn() ?: 0);
        if ($tenantId < 1) {
            throw new RuntimeException('Usuário sem acesso à empresa selecionada.');
        }
        return $tenantId;
    }

    /** @return array{id:int,tenant_id:int,type:string,external_id:string,driver:string,status:string} */
    public function inboundChannel(string $externalId, string $type = 'whatsapp'): array
    {
        $statement = Connection::getInstance()->prepare("SELECT id,tenant_id,type,external_id,driver,status FROM talk_channels WHERE external_id=:external_id AND type=:type AND status='active' LIMIT 1");
        $statement->execute(['external_id' => $externalId, 'type' => $type]);
        $channel = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($channel)) {
            throw new RuntimeException('Canal de entrada inativo ou não cadastrado.');
        }
        return [
            'id' => (int) $channel['id'],
            'tenant_id' => (int) $channel['tenant_id'],
            'type' => (string) $channel['type'],
            'external_id' => (string) $channel['external_id'],
            'driver' => (string) $channel['driver'],
            'status' => (string) $channel['status'],
        ];
    }
}
