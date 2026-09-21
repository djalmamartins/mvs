<?php

declare(strict_types=1);

namespace Moves\Core;

/**
 * Normalizes query parameters shared by versioned APIs.
 */
final class ApiQuery
{
    /** @return array{page: int, per_page: int} */
    public static function pagination(array $query, int $maxPerPage = 100): array
    {
        $page = filter_var($query['page'] ?? 1, FILTER_VALIDATE_INT);
        $perPage = filter_var($query['per_page'] ?? 20, FILTER_VALIDATE_INT);

        if ($page === false || $page < 1 || $perPage === false || $perPage < 1) {
            throw new \InvalidArgumentException('Invalid pagination query.');
        }

        return [
            'page' => $page,
            'per_page' => min($perPage, $maxPerPage),
        ];
    }

    /**
     * @param array<string, mixed> $query
     * @param array<int, string> $allowed
     * @return array<string, scalar|null>
     */
    public static function filters(array $query, array $allowed): array
    {
        $filters = [];

        foreach ($allowed as $key) {
            if (!array_key_exists($key, $query)) {
                continue;
            }

            $value = $query[$key];
            if (!is_scalar($value) && $value !== null) {
                throw new \InvalidArgumentException('Invalid filter value.');
            }

            $filters[$key] = is_string($value) ? trim($value) : $value;
        }

        return $filters;
    }
}
