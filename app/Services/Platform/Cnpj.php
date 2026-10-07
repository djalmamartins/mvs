<?php

declare(strict_types=1);

namespace Moves\Services\Platform;

/** Normalização e dígitos verificadores de CNPJ numérico e alfanumérico. */
final class Cnpj
{
    public static function normalize(?string $value): ?string
    {
        $normalized = strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', trim((string) $value)));

        return $normalized === '' ? null : $normalized;
    }

    public static function isValid(string $value): bool
    {
        $cnpj = self::normalize($value);
        if ($cnpj === null || preg_match('/^[A-Z0-9]{12}[0-9]{2}$/', $cnpj) !== 1 || preg_match('/^([A-Z0-9])\1{13}$/', $cnpj) === 1) {
            return false;
        }

        $first = self::digit(substr($cnpj, 0, 12), [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
        $second = self::digit(substr($cnpj, 0, 12) . $first, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);

        return substr($cnpj, 12, 2) === $first . $second;
    }

    public static function format(?string $value): string
    {
        $cnpj = self::normalize($value);
        if ($cnpj === null || strlen($cnpj) !== 14) {
            return '';
        }

        return substr($cnpj, 0, 2) . '.' . substr($cnpj, 2, 3) . '.' . substr($cnpj, 5, 3) . '/' . substr($cnpj, 8, 4) . '-' . substr($cnpj, 12, 2);
    }

    /** @param list<int> $weights */
    private static function digit(string $base, array $weights): string
    {
        $sum = 0;
        foreach ($weights as $index => $weight) {
            $sum += (ord($base[$index]) - 48) * $weight;
        }
        $remainder = $sum % 11;

        return (string) ($remainder < 2 ? 0 : 11 - $remainder);
    }

}
