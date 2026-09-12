<?php

declare(strict_types=1);

namespace Moves\Core;

use RuntimeException;

/**
 * Moves | HTTP Exception
 *
 * Representa erros HTTP controlados pela aplicação,
 * preservando o código de resposta correspondente.
 *
 * @author Djalma Martins
 * @package Moves\Core
 */
final class HttpException extends RuntimeException
{
    public function __construct(
        private int $statusCode,
        string $message = ''
    ) {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}
