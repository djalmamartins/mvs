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
     * Valida credenciais sem conceder uma sessão autenticada.
     *
     * Este boundary permite executar políticas adicionais (como MFA)
     * antes de promover o usuário para uma sessão completa.
     */
    public static function verifyCredentials(
        string $email,
        string $password
    ): ?User {
        $user = (new User())
            ->find(
                'email = :email AND status = :status',
                [
                    'email' => $email,
                    'status' => 'active',
                ]
            )
            ->fetch();

        if (!$user instanceof User) {
            return null;
        }

        if (!password_verify($password, $user->password)) {
            return null;
        }

        return $user;
    }

    /**
     * Concede a sessão autenticada somente a um usuário ativo já validado.
     */
    public static function establishSession(User $user): bool
    {
        if ((string) ($user->status ?? '') !== 'active' || (int) ($user->id ?? 0) <= 0) {
            return false;
        }

        Session::set(self::SESSION_KEY, (int) $user->id);
        session_regenerate_id(true);

        return true;
    }

    /**
     * Tenta autenticar um usuário preservando o contrato legado.
     */
    public static function attempt(
        string $email,
        string $password
    ): bool {
        $user = self::verifyCredentials($email, $password);

        if (!$user instanceof User) {
            return false;
        }

        return self::establishSession($user);
    }

    /**
     * Retorna o usuário autenticado.
     */
    public static function user(): ?User
    {
        $userId = Session::get(self::SESSION_KEY);

        if (!is_int($userId)) {
            return null;
        }

        $user = (new User())->findById($userId);

        if (!$user instanceof User || (string) ($user->status ?? '') !== 'active') {
            Session::remove(self::SESSION_KEY);
            return null;
        }

        return $user;
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
        Session::destroy();
    }
}
