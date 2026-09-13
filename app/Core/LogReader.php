<?php

declare(strict_types=1);

namespace Moves\Core;

/** Reads the application JSONL log without exposing its storage details. */
final class LogReader
{
    private const MAX_LINES = 2000;

    private const SENSITIVE_KEY_PATTERN =
        '/password|passwd|secret|token|csrf|cookie|session|authorization|api[_-]?key|trace|file|path/i';

    public function __construct(
        private readonly ?string $logFile = null
    ) {
    }

    /**
     * @return array{entries: list<array{timestamp: string, level: string, message: string, context: array<mixed>}>, total: int, page: int, pages: int, perPage: int}
     */
    public function read(
        string $search = '',
        string $level = '',
        int $page = 1,
        int $perPage = 20
    ): array {
        $search = mb_substr(trim(strip_tags($search)), 0, 120);
        $level = in_array($level, ['info', 'warning', 'error'], true)
            ? $level
            : '';
        $page = max(1, $page);
        $perPage = in_array($perPage, [10, 20, 50], true) ? $perPage : 20;
        $records = [];
        $file = $this->logFile
            ?? dirname(__DIR__, 2) . '/storage/logs/moves.log';

        if (is_file($file) && is_readable($file)) {
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            if (is_array($lines)) {
                foreach (array_slice($lines, -self::MAX_LINES) as $line) {
                    $record = json_decode($line, true);

                    if (!is_array($record)) {
                        continue;
                    }

                    $recordLevel = (string) ($record['level'] ?? 'info');
                    $message = mb_substr((string) ($record['message'] ?? ''), 0, 1000);
                    $context = is_array($record['context'] ?? null)
                        ? $this->sanitize($record['context'])
                        : [];

                    if ($level !== '' && $recordLevel !== $level) {
                        continue;
                    }

                    $haystack = $message . ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
                    if ($search !== '' && mb_stripos($haystack, $search) === false) {
                        continue;
                    }

                    $records[] = [
                        'timestamp' => (string) ($record['timestamp'] ?? ''),
                        'level' => in_array($recordLevel, ['info', 'warning', 'error'], true)
                            ? $recordLevel
                            : 'info',
                        'message' => $message,
                        'context' => $context,
                    ];
                }
            }
        }

        $records = array_reverse($records);
        $total = count($records);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);

        return [
            'entries' => array_slice($records, ($page - 1) * $perPage, $perPage),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'perPage' => $perPage,
        ];
    }

    /** @param array<mixed> $context @return array<mixed> */
    private function sanitize(array $context): array
    {
        $safe = [];

        foreach ($context as $key => $value) {
            if (preg_match(self::SENSITIVE_KEY_PATTERN, (string) $key) === 1) {
                $safe[$key] = '[REDACTED]';
                continue;
            }

            if (is_array($value)) {
                $safe[$key] = $this->sanitize($value);
                continue;
            }

            $safe[$key] = is_scalar($value) || $value === null
                ? $value
                : '[UNAVAILABLE]';
        }

        return $safe;
    }
}
