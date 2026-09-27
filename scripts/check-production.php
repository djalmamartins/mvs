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
if ($appUrl === '' || filter_var($appUrl, FILTER_VALIDATE_URL) === false || !str_starts_with(strtolower($appUrl), 'https://')) {
    $errors[] = 'APP_URL deve ser uma URL HTTPS válida.';
}

foreach (['DB_HOST', 'DB_DATABASE', 'DB_USERNAME'] as $key) {
    if (trim((string) Config::get($key, '')) === '') {
        $errors[] = $key . ' é obrigatório.';
    }
}

foreach (['SESSION_SECURE', 'SESSION_HTTP_ONLY'] as $key) {
    $value = strtolower(trim((string) Config::get($key, '')));
    if (!in_array($value, ['1', 'true', 'yes', 'on'], true)) {
        $errors[] = $key . ' deve estar habilitado em produção.';
    }
}

$sameSite = strtolower(trim((string) Config::get('SESSION_SAME_SITE', '')));
if (!in_array($sameSite, ['lax', 'strict'], true)) {
    $errors[] = 'SESSION_SAME_SITE deve ser Lax ou Strict em produção.';
}

$storage = dirname(__DIR__) . '/storage';
if (!is_dir($storage)) {
    $errors[] = 'Diretório storage não existe.';
} elseif (!is_writable($storage)) {
    $errors[] = 'Diretório storage não é gravável.';
}

$envFile = dirname(__DIR__) . '/.env';
if (is_file($envFile)) {
    $permissions = fileperms($envFile);
    if ($permissions !== false && ($permissions & 0x0004) !== 0) {
        $errors[] = 'Arquivo .env não pode ser legível por outros usuários.';
    }
}

if ($errors !== []) {
    fwrite(STDERR, "Gate de produção falhou:" . PHP_EOL);
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . PHP_EOL);
    }
    exit(1);
}

echo 'Gate de produção: OK' . PHP_EOL;
