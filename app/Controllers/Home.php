<?php

declare(strict_types=1);

namespace Moves\Controllers;

use MovesCode\Router\Router;

/**
 * Moves | Home Controller
 *
 * Gerencia a página inicial da aplicação.
 *
 * @author Djalma Martins
 * @package Moves\Controllers
 */
final class Home
{
    public function __construct(
        private Router $router
    ) {
    }

    public function index(): void
    {
        echo 'Moves';
    }
}
