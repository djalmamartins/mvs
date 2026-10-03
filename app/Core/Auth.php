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
    private const LAST_ACTIVITY_KEY = 'auth_last_activity';
    private const AUTHENTICATED_AT_KEY = 'auth_authenticated_at';
    private const DEFAULT_IDLE_TIMEOUT = 1800;
    private const DEFAULT_ABSOLUTE_TIMEOUT = 43200;

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
        $now = time();
        Session::set(self::LAST_ACTIVITY_KEY, $now);
        Session::set(self::AUTHENTICATED_AT_KEY, $now);
        Session::regenerate();

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
        if (!self::sessionIsFresh()) {
            self::logout();
            return false;
        }

        $user = self::user();
        if (!$user instanceof User) {
            return false;
        }

        Session::set(self::LAST_ACTIVITY_KEY, time());

        return true;
    }

    private static function sessionIsFresh(): bool
    {
        $userId = Session::get(self::SESSION_KEY);
        if (!is_int($userId)) {
            return true;
        }

        $lastActivity = Session::get(self::LAST_ACTIVITY_KEY);
        $authenticatedAt = Session::get(self::AUTHENTICATED_AT_KEY);
        if (!is_int($lastActivity) || !is_int($authenticatedAt)) {
            return false;
        }

        $idleConfigured = (int) Config::get('SESSION_IDLE_TIMEOUT', self::DEFAULT_IDLE_TIMEOUT);
        $idleTimeout = $idleConfigured > 0 ? $idleConfigured : self::DEFAULT_IDLE_TIMEOUT;
        $absoluteConfigured = (int) Config::get('SESSION_ABSOLUTE_TIMEOUT', self::DEFAULT_ABSOLUTE_TIMEOUT);
        $absoluteTimeout = $absoluteConfigured > 0 ? $absoluteConfigured : self::DEFAULT_ABSOLUTE_TIMEOUT;
        $now = time();

        return ($now - $lastActivity) <= $idleTimeout
            && ($now - $authenticatedAt) <= $absoluteTimeout;
    }

    /**
     * Encerra a autenticação atual.
     */
    public static function logout(): void
    {
        Session::destroy();
    }
}
