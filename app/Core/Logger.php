<?php

declare(strict_types=1);

namespace Moves\Core;

use Throwable;

/**
 * Moves | Logger
 *
 * Registra eventos e erros da aplicação
 * em arquivos locais de log.
 *
 * @author Djalma Martins
 * @package Moves\Core
 */
final class Logger
{
    private const LOG_DIRECTORY = 'storage/logs';

    public static function info(
        string $message,
        array $context = []
    ): void {
        self::write('info', $message, $context);
    }

    public static function warning(
        string $message,
        array $context = []
    ): void {
        self::write('warning', $message, $context);
    }

    public static function error(
        string $message,
        array $context = []
    ): void {
        self::write('error', $message, $context);
    }

    public static function exception(
        Throwable $exception
    ): void {
        self::error(
            $exception->getMessage(),
            [
                'exception' => $exception::class,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]
        );
    }

    private static function write(
        string $level,
        string $message,
        array $context = []
    ): void {
        $directory = dirname(__DIR__, 2)
            . '/'
            . self::LOG_DIRECTORY;

        if (!is_dir($directory)) {
            mkdir(
                $directory,
                0755,
                true
            );
        }

        $record = [
            'timestamp' => date(DATE_ATOM),
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];

        $encoded = json_encode(
            $record,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
        );

        if ($encoded === false) {
            error_log(
                '[Moves] Não foi possível converter o registro de log para JSON.'
            );

            return;
        }

        $logFile = $directory . '/moves.log';

        $written = @file_put_contents(
            $logFile,
            $encoded . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );

        if ($written === false) {
            error_log(
                '[Moves] Não foi possível gravar o log em: '
                . $logFile
            );
        }
    }
}
