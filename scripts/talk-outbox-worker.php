<?php

declare(strict_types=1);

use Moves\Boot\Environment;
use Moves\Services\Talk\TalkOutboxWorker;

require dirname(__DIR__) . '/vendor/autoload.php';

Environment::load(dirname(__DIR__));

$once = in_array('--once', $argv, true);
$idleMilliseconds = max(250, min(60000, (int)($_ENV['TALK_OUTBOX_IDLE_MILLISECONDS'] ?? 2000)));
$lockTimeout = max(1, min(3600, (int)($_ENV['TALK_OUTBOX_LOCK_TIMEOUT'] ?? 120)));
$baseBackoff = max(1, min(3600, (int)($_ENV['TALK_OUTBOX_BACKOFF_SECONDS'] ?? 15)));
$worker = new TalkOutboxWorker(lockTimeoutSeconds: $lockTimeout, baseBackoffSeconds: $baseBackoff);
$running = true;

if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGTERM, static function () use (&$running): void { $running = false; });
    pcntl_signal(SIGINT, static function () use (&$running): void { $running = false; });
}

do {
    try {
        $result = $worker->processNext();
        if ($result !== null) {
            echo json_encode($result, JSON_THROW_ON_ERROR) . PHP_EOL;
        }
    } catch (Throwable $exception) {
        fwrite(STDERR, 'Worker indisponível: ' . mb_substr($exception->getMessage(), 0, 300) . PHP_EOL);
        $result = null;
    }

    if (!$once && $running && $result === null) {
        usleep($idleMilliseconds * 1000);
    }
} while (!$once && $running);
