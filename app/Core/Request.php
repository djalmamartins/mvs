<?php

declare(strict_types=1);

namespace Moves\Core;

/**
 * Moves | Request
 *
 * Gerencia os dados recebidos pela requisição HTTP.
 *
 * Centraliza o acesso aos dados de GET, POST e informações
 * básicas da requisição atual.
 *
 * @author Djalma Martins
 * @package Moves\Core
 */
final class Request
{
    /**
     * Retorna o método HTTP atual.
     */
    public static function method(): string
    {
        return strtoupper(
            $_SERVER['REQUEST_METHOD'] ?? 'GET'
        );
    }

    /**
     * Verifica se a requisição utiliza um método específico.
     */
    public static function isMethod(string $method): bool
    {
        return self::method() === strtoupper($method);
    }

    /**
     * Retorna um valor enviado via GET.
     */
    public static function get(
        string $key,
        mixed $default = null
    ): mixed {
        return $_GET[$key] ?? $default;
    }

    /**
     * Retorna um valor enviado via POST.
     */
    public static function post(
        string $key,
        mixed $default = null
    ): mixed {
        return $_POST[$key] ?? $default;
    }

    /**
     * Retorna um valor enviado via POST ou GET.
     */
    public static function input(
        string $key,
        mixed $default = null
    ): mixed {
        if (array_key_exists($key, $_POST)) {
            return $_POST[$key];
        }

        return $_GET[$key] ?? $default;
    }

    /**
     * Verifica se um campo foi enviado.
     */
    public static function has(string $key): bool
    {
        return array_key_exists($key, $_POST)
            || array_key_exists($key, $_GET);
    }

    /**
     * Retorna todos os dados recebidos.
     */
    public static function all(): array
    {
        return array_merge(
            $_GET,
            $_POST
        );
    }
}
