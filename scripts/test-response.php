<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Moves\Boot\Environment;
use Moves\Core\Config;

/**
 * Moves | Response Test
 *
 * Valida a construção da URL base utilizada nos redirecionamentos.
 *
 * @author Djalma Martins
 */

Environment::load(dirname(__DIR__));

echo rtrim(
        (string) Config::get('APP_URL'),
        '/'
    ) . '/test' . PHP_EOL;
