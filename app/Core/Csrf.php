<?php

declare(strict_types=1);

namespace Moves\Core;

/**
 * Moves | CSRF Protection
 *
 * Gerencia tokens CSRF utilizados na proteção
 * de formulários e requisições sensíveis.
 *
 * @author Djalma Martins
 * @package Moves\Core
 */
final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    /**
     * Retorna o token CSRF atual ou gera um novo.
     */
    public static function token(): string
    {
        $token = Session::get(
            self::SESSION_KEY
        );

        if (
            !is_string($token)
            || $token === ''
        ) {
            $token = bin2hex(
                random_bytes(32)
            );

            Session::set(
                self::SESSION_KEY,
                $token
            );
        }

        return $token;
    }

    /**
     * Valida um token CSRF recebido.
     */
    public static function validate(
        ?string $token
    ): bool {
        if (
            $token === null
            || $token === ''
        ) {
            return false;
        }

        $storedToken = Session::get(
            self::SESSION_KEY
        );

        if (
            !is_string($storedToken)
            || $storedToken === ''
        ) {
            return false;
        }

        return hash_equals(
            $storedToken,
            $token
        );
    }

    /**
     * Gera o campo HTML oculto com o token CSRF.
     */
    public static function field(): string
    {
        $token = htmlspecialchars(
            self::token(),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        return sprintf(
            '<input type="hidden" name="_token" value="%s">',
            $token
        );
    }

    /**
     * Renova o token CSRF atual.
     */
    public static function regenerate(): string
    {
        Session::remove(
            self::SESSION_KEY
        );

        return self::token();
    }
}
