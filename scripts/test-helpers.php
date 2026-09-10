<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Moves\Boot\Connection as DatabaseConnection;
use Moves\Boot\Environment;
use MovesCode\Model\Connection as ModelConnection;

/**
 * Moves | Helpers Test
 *
 * Valida os helpers globais da aplicação.
 *
 * @author Djalma Martins
 */

Environment::load(dirname(__DIR__));

$pdo = DatabaseConnection::getInstance();

ModelConnection::configure($pdo);

echo config('APP_NAME', 'Moves') . PHP_EOL;
echo setting('app_name', 'Moves') . PHP_EOL;
echo setting('unknown_setting', 'Default Value') . PHP_EOL;