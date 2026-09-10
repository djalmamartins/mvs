<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Moves\Boot\Environment;
use Moves\Core\Application;

Environment::load(dirname(__DIR__));

$app = new Application();
$app->run();