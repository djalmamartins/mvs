<?php

declare(strict_types=1);

namespace Moves\Services\Auth;

use DateTimeImmutable;
use Moves\Boot\Connection;
use PDO;
use RuntimeException;
use Throwable;

final class UserInvitationService
{
    public const DEFAULT_TTL_HOURS = 48;

    /** @return array{token:string,expires_at:string} */
    public function create(int $tenantId, int $userId, int $roleId, ?int $invitedBy = null): array
    {
        if ($tenantId < 1 || $userId < 1 || $roleId < 1) {
            throw new RuntimeException('Convite inválido.');
        }

        $pdo = Connection::getInstance();
        $this->assertMembershipContext($pdo, $tenantId, $userId, $roleId);

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = (new DateTimeImmutable())
            ->modify('+' . self::DEFAULT_TTL_HOURS . ' hours')
            ->format('Y-m-d H:i:s');

        $pdo->beginTransaction();

        try {
            $pdo->prepare(
                'UPDATE platform_user_invitations SET revoked_at=NOW() '
                . 'WHERE tenant_id=? AND user_id=? AND accepted_at IS NULL AND revoked_at IS NULL'
            )->execute([$tenantId, $userId]);

            $statement = $pdo->prepare(
                'INSERT INTO platform_user_invitations '
                . '(tenant_id,user_id,role_id,token_hash,expires_at,invited_by) VALUES(?,?,?,?,?,?)'
            );
            $statement->execute([$tenantId, $userId, $roleId, $tokenHash, $expiresAt, $invitedBy]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        return ['token' => $token, 'expires_at' => $expiresAt];
    }

    /** @return array<string,mixed>|null */
    public function valid(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        $statement = Connection::getInstance()->prepare(
            'SELECT i.id,i.tenant_id,i.user_id,i.role_id,i.expires_at,u.name,u.email,t.name tenant_name '
            . 'FROM platform_user_invitations i '
            . 'JOIN users u ON u.id=i.user_id '
            . 'JOIN talk_tenants t ON t.id=i.tenant_id '
            . 'WHERE i.token_hash=? AND i.accepted_at IS NULL AND i.revoked_at IS NULL '
            . 'AND i.expires_at>NOW() LIMIT 1'
        );
        $statement->execute([hash('sha256', $token)]);
        $invitation = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($invitation) ? $invitation : null;
    }

    public function accept(string $token, string $passwordHash): bool
    {
        $invitation = $this->valid($token);
        if ($invitation === null || $passwordHash === '') {
            return false;
        }

        $pdo = Connection::getInstance();
        $pdo->beginTransaction();

        try {
            $lock = $pdo->prepare(
                'SELECT id,tenant_id,user_id,role_id FROM platform_user_invitations '
                . 'WHERE id=? AND accepted_at IS NULL AND revoked_at IS NULL AND expires_at>NOW() FOR UPDATE'
            );
            $lock->execute([(int) $invitation['id']]);
            $current = $lock->fetch(PDO::FETCH_ASSOC);
            if (!is_array($current)) {
                $pdo->rollBack();
                return false;
            }

            $pdo->prepare('UPDATE users SET password=?,status=? WHERE id=?')
                ->execute([$passwordHash, 'active', (int) $current['user_id']]);
            $pdo->prepare(
                'UPDATE talk_tenant_users SET role_id=?,status=?,updated_at=NOW() WHERE tenant_id=? AND user_id=?'
            )->execute([(int) $current['role_id'], 'active', (int) $current['tenant_id'], (int) $current['user_id']]);
            $pdo->prepare('UPDATE platform_user_invitations SET accepted_at=NOW() WHERE id=? AND accepted_at IS NULL')
                ->execute([(int) $current['id']]);
            $pdo->commit();

            return true;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function assertMembershipContext(PDO $pdo, int $tenantId, int $userId, int $roleId): void
    {
        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM talk_tenant_users m '
            . 'JOIN platform_roles r ON r.id=? AND r.tenant_id=m.tenant_id '
            . 'WHERE m.tenant_id=? AND m.user_id=?'
        );
        $statement->execute([$roleId, $tenantId, $userId]);

        if ((int) $statement->fetchColumn() !== 1) {
            throw new RuntimeException('Usuário, papel e tenant não pertencem ao mesmo vínculo.');
        }
    }
}
