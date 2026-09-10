<?php

declare(strict_types=1);

namespace Moves\Core;

/**
 * Moves | Session
 *
 * Gerencia os dados de sessão da aplicação.
 *
 * @author Djalma Martins
 * @package Moves\Core
 */
final class Session
{
    /**
     * Inicia a sessão caso ainda não esteja ativa.
     */
    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    /**
     * Retorna um valor da sessão.
     */
    public static function get(
        string $key,
        mixed $default = null
    ): mixed {
        self::start();

        return $_SESSION[$key] ?? $default;
    }

    /**
     * Define um valor na sessão.
     */
    public static function set(
        string $key,
        mixed $value
    ): void {
        self::start();

        $_SESSION[$key] = $value;
    }

    /**
     * Verifica se uma chave existe na sessão.
     */
    public static function has(string $key): bool
    {
        self::start();

        return array_key_exists($key, $_SESSION);
    }

    /**
     * Remove uma chave da sessão.
     */
    public static function remove(string $key): void
    {
        self::start();

        unset($_SESSION[$key]);
    }

    /**
     * Remove todos os dados da sessão atual.
     */
    public static function clear(): void
    {
        self::start();

        $_SESSION = [];
    }

    /**
     * Encerra completamente a sessão atual.
     */
    public static function destroy(): void
    {
        self::start();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $parameters = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $parameters['path'],
                $parameters['domain'],
                $parameters['secure'],
                $parameters['httponly']
            );
        }

        session_destroy();
    }
}
