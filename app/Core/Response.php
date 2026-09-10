<?php

declare(strict_types=1);

namespace Moves\Core;

/**
 * Moves | Response
 *
 * Gerencia respostas HTTP simples da aplicação.
 *
 * @author Djalma Martins
 * @package Moves\Core
 */
final class Response
{
    /**
     * Redireciona a requisição para uma URL.
     */
    public static function redirect(
        string $url,
        int $status = 302
    ): never {
        header(
            'Location: ' . $url,
            true,
            $status
        );

        exit;
    }

    /**
     * Redireciona para um caminho relativo à aplicação.
     */
    public static function to(
        string $path = '/',
        int $status = 302
    ): never {
        $baseUrl = rtrim(
            (string) Config::get('APP_URL', ''),
            '/'
        );

        $path = '/' . ltrim($path, '/');

        self::redirect(
            $baseUrl . $path,
            $status
        );
    }

    /**
     * Redireciona para a página inicial da aplicação.
     */
    public static function home(
        int $status = 302
    ): never {
        self::to(
            '/',
            $status
        );
    }
}