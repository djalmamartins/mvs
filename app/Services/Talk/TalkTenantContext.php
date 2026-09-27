<?php

declare(strict_types=1);

namespace Moves\Services\Talk;

use Moves\Boot\Connection;
use Moves\Core\Auth;
use Moves\Core\HttpException;
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
        $sql = "SELECT tu.tenant_id FROM talk_tenant_users tu INNER JOIN talk_tenants t ON t.id=tu.tenant_id INNER JOIN platform_tenant_products product ON product.tenant_id=tu.tenant_id AND product.product='talk' AND product.enabled=1 INNER JOIN users u ON u.id=tu.user_id WHERE tu.user_id=:user_id AND tu.status='active' AND t.status='active' AND u.status='active'";
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
            throw new HttpException(403, 'Usuário sem acesso à empresa selecionada.');
        }
        return $tenantId;
    }

    /** @return array{id:int,tenant_id:int,type:string,external_id:string,driver:string,status:string} */
    public function inboundChannel(string $externalId, string $type = 'whatsapp'): array
    {
        $statement = Connection::getInstance()->prepare("SELECT ch.id,ch.tenant_id,ch.type,ch.external_id,ch.driver,ch.status FROM talk_channels ch INNER JOIN talk_tenants tenant ON tenant.id=ch.tenant_id AND tenant.status='active' INNER JOIN platform_tenant_products product ON product.tenant_id=ch.tenant_id AND product.product='talk' AND product.enabled=1 WHERE ch.external_id=:external_id AND ch.type=:type AND ch.status='active' LIMIT 1");
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
