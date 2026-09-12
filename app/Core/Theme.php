<?php

declare(strict_types=1);

namespace Moves\Core;

/**
 * Moves | Theme
 *
 * Resolve o contexto visual da aplicação pela URL atual.
 *
 * @author Djalma Martins
 * @package Moves\Core
 */
final class Theme
{
    public static function active(): string
    {
        $path = parse_url(
            $_SERVER['REQUEST_URI'] ?? '/',
            PHP_URL_PATH
        );

        $path = is_string($path) ? $path : '/';

        if ($path === '/admin' || str_starts_with($path, '/admin/')) {
            return 'admin';
        }

        if ($path === '/app' || str_starts_with($path, '/app/')) {
            return 'app';
        }

        return 'site';
    }

    public static function path(): string
    {
        return dirname(__DIR__, 2)
            . '/resources/themes/'
            . self::active();
    }

    public static function asset(string $path): string
    {
        $path = ltrim($path, '/');
        $url = '/themes/'
            . self::active()
            . '/'
            . $path;
        $file = dirname(__DIR__, 2)
            . '/public/themes/'
            . self::active()
            . '/'
            . $path;

        return is_file($file)
            ? $url . '?v=' . filemtime($file)
            : $url;
    }
}
