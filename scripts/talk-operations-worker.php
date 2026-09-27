<?php

declare(strict_types=1);

use Moves\Boot\Environment;
use Moves\Services\Talk\TalkOperationsWorker;

require dirname(__DIR__) . '/vendor/autoload.php';

Environment::load(dirname(__DIR__));

$once = in_array('--once', $argv, true);
$interval = max(1000, min(60000, (int) ($_ENV['TALK_OPERATIONS_INTERVAL_MS'] ?? 5000)));
$worker = new TalkOperationsWorker();
$running = true;

if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGTERM, static function () use (&$running): void { $running = false; });
    pcntl_signal(SIGINT, static function () use (&$running): void { $running = false; });
}

do {
    try {
        $result = $worker->processDue();
        if ($result['assigned'] > 0 || $result['jack'] > 0) {
            echo json_encode($result, JSON_THROW_ON_ERROR) . PHP_EOL;
        }
    } catch (Throwable $exception) {
        fwrite(STDERR, 'Operações Talk indisponíveis: ' . mb_substr($exception->getMessage(), 0, 300) . PHP_EOL);
        if ($once) {
            exit(1);
        }
    }

    if (!$once && $running) {
        usleep($interval * 1000);
    }
} while (!$once && $running);
