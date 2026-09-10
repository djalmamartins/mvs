<?php

declare(strict_types=1);

namespace Moves\Core;

use MovesCode\Router\Router;
use MovesCode\View\Engine;

/**
 * Moves | Controller
 *
 * Fornece a estrutura base para os controladores da aplicação.
 *
 * @author Djalma Martins
 * @package Moves\Core
 */
abstract class Controller
{
    protected Engine $view;

    public function __construct(
        protected Router $router
    ) {
        $this->view = new Engine(
            Theme::path()
        );

        $this->view->share(
            'theme',
            Theme::active()
        );

        $this->view->registerFunction(
            'asset',
            static fn (string $path): string => Theme::asset($path)
        );
    }
}