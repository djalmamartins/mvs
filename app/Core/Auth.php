<?php

declare(strict_types=1);

namespace Moves\Core;

use Moves\Models\User;

/**
 * Moves | Authentication
 *
 * Gerencia a autenticação e a sessão
 * dos usuários da aplicação.
 *
 * @author Djalma Martins
 * @package Moves\Core
 */
final class Auth
{
    private const SESSION_KEY = 'auth_user';

    /**
     * Tenta autenticar um usuário.
     */
    public static function attempt(
        string $email,
        string $password
    ): bool {
        $user = (new User())
            ->find(
                'email = :email AND status = :status',
                [
                    'email' => $email,
                    'status' => 'active',
                ]
            )
            ->fetch();

        if (!$user) {
            return false;
        }

        if (
            !password_verify(
                $password,
                $user->password
            )
        ) {
            return false;
        }

        Session::set(
            self::SESSION_KEY,
            (int) $user->id
        );

        session_regenerate_id(true);

        return true;
    }

    /**
     * Retorna o usuário autenticado.
     */
    public static function user(): ?User
    {
        $userId = Session::get(
            self::SESSION_KEY
        );

        if (!is_int($userId)) {
            return null;
        }

        $user = (new User())
            ->findById($userId);

        return $user instanceof User
            ? $user
            : null;
    }

    /**
     * Verifica se existe um usuário autenticado.
     */
    public static function check(): bool
    {
        return self::user() !== null;
    }

    /**
     * Encerra a autenticação atual.
     */
    public static function logout(): void
    {
        Session::remove(
            self::SESSION_KEY
        );

        session_regenerate_id(true);
    }
}
