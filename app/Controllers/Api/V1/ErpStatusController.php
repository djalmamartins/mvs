<?php

declare(strict_types=1);

namespace Moves\Controllers\Api\V1;

use Moves\Core\Controller;
use Moves\Core\Response;

/**
 * Minimal authenticated ERP API contract.
 *
 * This endpoint deliberately exposes no database or infrastructure details.
 */
final class ErpStatusController extends Controller
{
    public function index(): never
    {
        Response::json([
            'data' => [
                'service' => 'erp',
                'api_version' => 'v1',
                'status' => 'ok',
            ],
        ]);
    }
}
