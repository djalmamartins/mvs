<?php

declare(strict_types=1);

namespace Moves\Services\Auth;

final class PasswordPolicy
{
    public const MIN_LENGTH = 10;
    public const MAX_LENGTH = 128;

    /** @return array<int,string> */
    public static function errors(string $password): array
    {
        $errors = [];
        $length = mb_strlen($password);

        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            $errors[] = 'Use uma senha entre 10 e 128 caracteres.';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Inclua ao menos uma letra minúscula.';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Inclua ao menos uma letra maiúscula.';
        }
        if (!preg_match('/\d/', $password)) {
            $errors[] = 'Inclua ao menos um número.';
        }
        if (!preg_match('/[^A-Za-z0-9\s]/', $password)) {
            $errors[] = 'Inclua ao menos um símbolo.';
        }

        return $errors;
    }

    public static function accepts(string $password): bool
    {
        return self::errors($password) === [];
    }
}
