<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Moves\Boot\Environment;
use Moves\Core\Config;

Environment::load(dirname(__DIR__));

$errors = [];

if (Config::environment() !== 'production') {
    $errors[] = 'APP_ENV deve ser production.';
}

if (Config::debug()) {
    $errors[] = 'APP_DEBUG deve estar desativado em produção.';
}

$appUrl = (string) Config::get('APP_URL', '');
if ($appUrl === '' || !str_starts_with($appUrl, 'https://')) {
    $errors[] = 'APP_URL deve existir e usar HTTPS.';
}

foreach (['DB_HOST', 'DB_DATABASE', 'DB_USERNAME'] as $key) {
    if ((string) Config::get($key, '') === '') {
        $errors[] = $key . ' é obrigatório.';
    }
}

$storage = dirname(__DIR__) . '/storage';
if (!is_dir($storage)) {
    $errors[] = 'Diretório storage não existe.';
} elseif (!is_writable($storage)) {
    $errors[] = 'Diretório storage não é gravável.';
}

if ($errors !== []) {
    fwrite(STDERR, "Gate de produção falhou:" . PHP_EOL);
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . PHP_EOL);
    }
    exit(1);
}

echo 'Gate de produção: OK' . PHP_EOL;
