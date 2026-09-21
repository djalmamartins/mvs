<?php

declare(strict_types=1);

namespace Moves\Core;

/**
 * Normaliza parâmetros comuns de listagem sem acoplar o Core ao domínio ERP.
 */
final class ApiQuery
{
    /**
     * @param array<string, mixed> $query
     * @param list<string> $allowedSorts
     * @return array{page:int,per_page:int,sort:?string,direction:string,filters:array<string,string>}
     */
    public static function normalize(array $query, array $allowedSorts = []): array
    {
        $page = filter_var($query['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
        $perPage = filter_var($query['per_page'] ?? 20, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]) ?: 20;
        $sort = isset($query['sort']) ? trim((string) $query['sort']) : null;
        $direction = strtolower(trim((string) ($query['direction'] ?? 'asc')));

        if ($sort !== null && $sort !== '' && !in_array($sort, $allowedSorts, true)) {
            throw new \InvalidArgumentException('Unsupported sort field.');
        }

        if (!in_array($direction, ['asc', 'desc'], true)) {
            throw new \InvalidArgumentException('Unsupported sort direction.');
        }

        $filters = [];
        foreach (($query['filter'] ?? []) as $key => $value) {
            if (!is_string($key) || (!is_scalar($value) && $value !== null)) {
                continue;
            }
            $filters[$key] = trim((string) $value);
        }

        return [
            'page' => $page,
            'per_page' => $perPage,
            'sort' => $sort === '' ? null : $sort,
            'direction' => $direction,
            'filters' => $filters,
        ];
    }
}
