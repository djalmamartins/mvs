<?php

declare(strict_types=1);

namespace Moves\Core;

use InvalidArgumentException;

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
     * Envia headers defensivos compatíveis com os temas atuais.
     */
    public static function securityHeaders(): void
    {
        header_remove('X-Powered-By');
        header('Content-Type: text/html; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), geolocation=(), microphone=()');
        header(
            "Content-Security-Policy: default-src 'self'; "
            . "base-uri 'self'; form-action 'self'; frame-ancestors 'none'; "
            . "object-src 'none'; img-src 'self' data:; "
            . "style-src 'self'; script-src 'self'"
        );

        $scheme = parse_url(
            (string) Config::get('APP_URL', ''),
            PHP_URL_SCHEME
        );

        if (Config::isProduction() && $scheme === 'https') {
            header('Strict-Transport-Security: max-age=31536000');
        }
    }

    /**
     * Redireciona a requisição para uma URL.
     */
    public static function redirect(
        string $url,
        int $status = 302
    ): never {
        if (!self::isSafeRedirect($url)) {
            throw new InvalidArgumentException('Destino de redirecionamento inválido.');
        }

        if (!in_array($status, [301, 302, 303, 307, 308], true)) {
            throw new InvalidArgumentException('Status de redirecionamento inválido.');
        }

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

    private static function isSafeRedirect(string $url): bool
    {
        if ($url === '' || str_contains($url, "\r") || str_contains($url, "\n")) {
            return false;
        }

        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return true;
        }

        $target = parse_url($url);
        $application = parse_url((string) Config::get('APP_URL', ''));

        if ($target === false || $application === false) {
            return false;
        }

        foreach (['scheme', 'host', 'port'] as $part) {
            if (($target[$part] ?? null) !== ($application[$part] ?? null)) {
                return false;
            }
        }

        return isset($target['scheme'], $target['host'])
            && !isset($target['user'], $target['pass']);
    }
}
