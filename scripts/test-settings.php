<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Moves\Boot\Connection as DatabaseConnection;
use Moves\Boot\Environment;
use Moves\Core\Settings;
use MovesCode\Model\Connection as ModelConnection;

/**
 * Moves | Settings Test
 *
 * Valida a leitura das configurações persistidas da aplicação.
 *
 * @author Djalma Martins
 */

Environment::load(dirname(__DIR__));

$pdo = DatabaseConnection::getInstance();

ModelConnection::configure($pdo);

echo Settings::get(
        'app_name',
        'Moves'
    ) . PHP_EOL;

echo Settings::get(
        'setting_that_does_not_exist',
        'Default Value'
    ) . PHP_EOL;
