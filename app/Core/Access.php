<?php

declare(strict_types=1);

namespace Moves\Core;

use Moves\Boot\Modules;
use Moves\Boot\Connection;
use Moves\Services\Platform\TenantAuthorization;
use Moves\Services\Platform\TenantContext;

/**
 * Moves | Access
 *
 * Centraliza as regras de autorização da aplicação.
 *
 * @author Djalma Martins
 * @package Moves\Core
 */
final class Access
{
    /**
     * Define as permissões disponíveis para cada papel.
     *
     * @var array<string, array<int, string>>
     */
    private const PERMISSIONS = [
        'admin' => [
            'users.manage',
            'settings.manage',
            'diagnostics.view',
            'studio.dashboard',
            'studio.search',
            'content.manage',
            'media.manage',
            'proposals.manage',
            'notifications.manage',
            'reports.view',
            'logs.manage',
        ],

        'user' => [
            'profile.view',
        ],
    ];

    /**
     * Verifica se o usuário autenticado possui
     * uma determinada permissão.
     */
    public static function can(string $permission): bool
    {
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        try {
            $pdo = Connection::getInstance();
            $tenantId = (new TenantContext($pdo))->currentId((int) $user->id);
            if ((new TenantAuthorization($pdo))->can((int) $user->id, $tenantId, $permission)) {
                return true;
            }
        } catch (\Throwable) {
            // Legacy/global permissions remain available during staged upgrades.
        }

        $role = (string) ($user->role ?? 'user');

        $permissions = array_merge(
            self::PERMISSIONS[$role] ?? [],
            Modules::permissions($role)
        );

        if ($role === 'admin') {
            $permissions = array_merge(
                self::PERMISSIONS['user'],
                Modules::permissions('user'),
                $permissions
            );
        }

        return in_array(
            $permission,
            $permissions,
            true
        );
    }

    /**
     * Verifica se o usuário autenticado
     * possui um determinado papel.
     */
    public static function is(string $role): bool
    {
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        return (string) ($user->role ?? 'user') === $role;
    }
}
