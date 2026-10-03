<?php

declare(strict_types=1);

if (PHP_SAPI === 'cli-server') {
    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $requestPath = is_string($requestPath) ? rawurldecode($requestPath) : '/';
    $publicRoot = realpath(__DIR__);
    $requestedFile = realpath(__DIR__ . '/' . ltrim($requestPath, '/'));

    if (
        is_string($publicRoot)
        && is_string($requestedFile)
        && str_starts_with($requestedFile, $publicRoot . DIRECTORY_SEPARATOR)
        && is_file($requestedFile)
    ) {
        return false;
    }
}

require dirname(__DIR__) . '/vendor/autoload.php';

use Moves\Boot\Environment;
use Moves\Core\Application;

Environment::load(dirname(__DIR__));

$app = new Application();
$app->run();
