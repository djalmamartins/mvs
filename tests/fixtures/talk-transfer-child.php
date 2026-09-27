<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

Moves\Boot\Environment::load(dirname(__DIR__, 2));
fwrite(STDOUT, "ready\n");
fflush(STDOUT);

try {
    (new Moves\Services\Talk\TalkService((int)$argv[1]))->transfer((int)$argv[2], (int)$argv[3], (int)$argv[4], null, 'concorrência');
    fwrite(STDOUT, "transferred\n");
} catch (RuntimeException $exception) {
    fwrite(STDOUT, "rejected: {$exception->getMessage()}\n");
}
