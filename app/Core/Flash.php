<?php

declare(strict_types=1);

namespace Moves\Core;

/**
 * Moves | Flash
 *
 * Gerencia mensagens temporárias armazenadas na sessão.
 *
 * As mensagens flash permanecem disponíveis até serem lidas
 * e são removidas automaticamente após o consumo.
 *
 * @author Djalma Martins
 * @package Moves\Core
 */
final class Flash
{
    private const SESSION_KEY = '_flash';

    /**
     * Armazena uma mensagem flash.
     */
    public static function set(
        string $type,
        string $message
    ): void {
        $messages = Session::get(
            self::SESSION_KEY,
            []
        );

        $messages[] = [
            'type' => $type,
            'message' => $message,
        ];

        Session::set(
            self::SESSION_KEY,
            $messages
        );
    }

    /**
     * Retorna todas as mensagens flash e as remove da sessão.
     */
    public static function all(): array
    {
        $messages = Session::get(
            self::SESSION_KEY,
            []
        );

        Session::remove(
            self::SESSION_KEY
        );

        return $messages;
    }

    /**
     * Verifica se existem mensagens flash.
     */
    public static function has(): bool
    {
        return Session::has(
            self::SESSION_KEY
        );
    }
}
