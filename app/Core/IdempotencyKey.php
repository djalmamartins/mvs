<?php

declare(strict_types=1);

namespace Moves\Core;

final class IdempotencyKey
{
    public static function normalize(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '' || strlen($value) > 128) {
            throw new \InvalidArgumentException('A valid idempotency key is required.');
        }

        if (preg_match('/^[A-Za-z0-9._:-]+$/', $value) !== 1) {
            throw new \InvalidArgumentException('Invalid idempotency key format.');
        }

        return $value;
    }

    public static function fingerprint(string $key, string $scope): string
    {
        $key = self::normalize($key);
        $scope = trim($scope);

        if ($scope === '') {
            throw new \InvalidArgumentException('Idempotency scope is required.');
        }

        return hash('sha256', $scope . "\0" . $key);
    }
}
