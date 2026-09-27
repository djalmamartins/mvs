<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

Moves\Boot\Environment::load(dirname(__DIR__, 2));

fwrite(STDOUT, "ready\n");
fflush(STDOUT);

try {
    $service = new Moves\Services\Talk\TalkService((int) $argv[1]);
    fwrite(STDOUT, ($service->claim((int) $argv[2], (int) $argv[3]) ? '1' : '0') . "\n");
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
