<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Moves\Boot\Connection as DatabaseConnection;
use Moves\Boot\Environment;
use Moves\Models\Setting;
use MovesCode\Model\Connection as ModelConnection;

/**
 * Moves | Model Test
 *
 * Valida criação, consulta e atualização utilizando o MovesCode Model.
 *
 * @author Djalma Martins
 */

Environment::load(dirname(__DIR__));

$pdo = DatabaseConnection::getInstance();

ModelConnection::configure($pdo);

$setting = new Setting();

$existing = $setting
    ->find('name = :name', ['name' => 'app_name'])
    ->fetch();

if ($existing) {
    $existing->value = 'Moves';

    if (!$existing->save()) {
        echo $existing->message()->text() . PHP_EOL;

        if ($existing->fail()) {
            echo $existing->fail()->getMessage() . PHP_EOL;
        }

        exit(1);
    }

    echo 'Setting atualizada: ' . $existing->name . PHP_EOL;
    exit;
}

$setting->name = 'app_name';
$setting->value = 'Moves';

if (!$setting->save()) {
    echo $setting->message()->text() . PHP_EOL;

    if ($setting->fail()) {
        echo $setting->fail()->getMessage() . PHP_EOL;
    }

    exit(1);
}

echo 'Setting criada com ID: ' . $setting->id . PHP_EOL;