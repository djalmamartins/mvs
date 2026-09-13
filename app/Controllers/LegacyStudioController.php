<?php

declare(strict_types=1);

namespace Moves\Controllers;

use Moves\Core\Response;

/** Keeps bookmarked Studio GET URLs compatible with the canonical /studio area. */
final class LegacyStudioController
{
    public function redirect(): never
    {
        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/admin');
        $parts = parse_url($requestUri);
        $path = is_array($parts) && is_string($parts['path'] ?? null)
            ? $parts['path']
            : '/admin';
        $query = is_array($parts) && is_string($parts['query'] ?? null)
            ? '?' . $parts['query']
            : '';

        $target = preg_replace('#^/admin(?=/|$)#', '/studio', $path, 1) ?: '/studio';

        Response::to($target . $query, 308);
    }
}
