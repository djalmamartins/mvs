<?php

declare(strict_types=1);

namespace Moves\Boot;

use Closure;
use MovesCode\Router\Router;

/**
 * Moves | Modules
 *
 * Mantém o registro mínimo das aplicações opcionais do Moves.
 *
 * @author Djalma Martins
 * @package Moves\Boot
 */
final class Modules
{
    /** @var array<string, Closure(Router): void> */
    private static array $routes = [];

    /** @var array<string, array<int, string>> */
    private static array $permissions = [];

    /**
     * Registra uma aplicação sem criar um sistema complexo de plugins.
     *
     * @param array<string, array<int, string>> $permissions
     */
    public static function register(
        string $name,
        ?Closure $routes = null,
        array $permissions = []
    ): void {
        if ($routes !== null) {
            self::$routes[$name] = $routes;
        }

        foreach ($permissions as $role => $items) {
            self::$permissions[$role] = array_values(array_unique(array_merge(
                self::$permissions[$role] ?? [],
                $items
            )));
        }
    }

    public static function boot(Router $router): void
    {
        foreach (self::$routes as $routes) {
            $routes($router);
        }
    }

    /** @return array<int, string> */
    public static function permissions(string $role): array
    {
        return self::$permissions[$role] ?? [];
    }
}
