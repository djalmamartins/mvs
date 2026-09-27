<?php

declare(strict_types=1);

namespace Moves\Core;

use InvalidArgumentException;

/**
 * Normalizes client supplied idempotency keys for mutating API operations.
 * Persistence/replay belongs to the application service that owns the write.
 */
final class IdempotencyKey
{
    private const MAX_LENGTH = 128;

    public static function normalize(?string $value): string
    {
        $key = trim((string) $value);

        if ($key === '' || strlen($key) > self::MAX_LENGTH) {
            throw new InvalidArgumentException('Invalid idempotency key.');
        }

        if (preg_match('/^[A-Za-z0-9._:-]+$/', $key) !== 1) {
            throw new InvalidArgumentException('Invalid idempotency key.');
        }

        return $key;
    }
}
