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
     * Redireciona a requisição para uma nova URL.
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
     * Redireciona para uma URL relativa à aplicação.
     */
    public static function to(
        string $path,
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
}
