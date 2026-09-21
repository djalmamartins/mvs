<?php

declare(strict_types=1);

namespace Moves\Core;

/**
 * Stable payload contracts shared by versioned APIs.
 */
final class ApiResponse
{
    /** @return array{data: mixed} */
    public static function data(mixed $data): array
    {
        return ['data' => $data];
    }

    /**
     * @param array<int, mixed> $items
     * @return array{data: array<int, mixed>, meta: array{page: int, per_page: int, total: int, total_pages: int}}
     */
    public static function page(array $items, int $page, int $perPage, int $total): array
    {
        if ($page < 1 || $perPage < 1 || $total < 0) {
            throw new \InvalidArgumentException('Invalid pagination values.');
        }

        return [
            'data' => array_values($items),
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $total === 0 ? 0 : (int) ceil($total / $perPage),
            ],
        ];
    }

    /** @return array{error: array{code: string, message: string}} */
    public static function error(string $code, string $message): array
    {
        $code = trim($code);
        $message = trim($message);

        if ($code === '' || $message === '') {
            throw new \InvalidArgumentException('Error code and message are required.');
        }

        return ['error' => ['code' => $code, 'message' => $message]];
    }
}
