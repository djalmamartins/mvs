<?php

declare(strict_types=1);

namespace Moves\Core;

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

        $role = (string) ($user->role ?? 'user');

        $permissions = self::PERMISSIONS[$role] ?? [];

        if ($role === 'admin') {
            $permissions = array_merge(
                self::PERMISSIONS['user'] ?? [],
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
