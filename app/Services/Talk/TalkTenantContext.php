<?php

declare(strict_types=1);

namespace Moves\Services\Talk;

use Moves\Boot\Connection;
use Moves\Core\Auth;
use Moves\Services\Platform\ProductEntitlement;
use Moves\Services\Platform\TenantContext;
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

        $pdo = Connection::getInstance();
        $tenantId = (new TenantContext($pdo))->currentId((int) $user->id);
        if (!(new ProductEntitlement($pdo))->enabled($tenantId, 'talk')) {
            throw new \Moves\Core\HttpException(403, 'Talk não habilitado para esta administradora.');
        }
        return $tenantId;
    }

    public function forUser(int $userId, ?int $requestedTenantId = null): int
    {
        $pdo = Connection::getInstance();
        $context = new TenantContext($pdo);
        $tenantId = $requestedTenantId === null ? $context->currentId($userId) : $this->resolveRequested($context, $userId, $requestedTenantId);
        if (!(new ProductEntitlement($pdo))->enabled($tenantId, 'talk')) {
            throw new \Moves\Core\HttpException(403, 'Talk não habilitado para esta administradora.');
        }
        return $tenantId;
    }

    private function resolveRequested(TenantContext $context, int $userId, int $tenantId): int
    {
        foreach ($context->available($userId) as $tenant) {
            if ($tenant['id'] === $tenantId) {
                return $tenantId;
            }
        }
        throw new \Moves\Core\HttpException(403, 'Usuário sem acesso à empresa selecionada.');
    }

    /** @return array{id:int,tenant_id:int,type:string,external_id:string,driver:string,status:string} */
    public function inboundChannel(string $externalId, string $type = 'whatsapp', string $sessionKey = ''): array
    {
        $sessionSql = $sessionKey !== '' ? ' AND ch.session_key=:session_key' : '';
        $statement = Connection::getInstance()->prepare("SELECT ch.id,ch.tenant_id,ch.type,ch.external_id,ch.driver,ch.status FROM talk_channels ch INNER JOIN talk_tenants tenant ON tenant.id=ch.tenant_id AND tenant.status='active' INNER JOIN platform_tenant_products product ON product.tenant_id=ch.tenant_id AND product.product='talk' AND product.enabled=1 WHERE ch.external_id=:external_id AND ch.type=:type AND ch.status='active'{$sessionSql} LIMIT 1");
        $parameters = ['external_id' => $externalId, 'type' => $type];
        if ($sessionKey !== '') $parameters['session_key'] = $sessionKey;
        $statement->execute($parameters);
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
