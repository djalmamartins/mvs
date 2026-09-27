<?php

declare(strict_types=1);

use Moves\Core\ApiResponse;
use PHPUnit\Framework\TestCase;

final class ApiResponseTest extends TestCase
{
    public function testDataEnvelopeIsStable(): void
    {
        self::assertSame(['data' => ['id' => 1]], ApiResponse::data(['id' => 1]));
    }

    public function testPaginationCalculatesTotalPages(): void
    {
        self::assertSame([
            'data' => [['id' => 1], ['id' => 2]],
            'meta' => ['page' => 2, 'per_page' => 2, 'total' => 5, 'total_pages' => 3],
        ], ApiResponse::page([['id' => 1], ['id' => 2]], 2, 2, 5));
    }

    public function testEmptyPaginationHasZeroPages(): void
    {
        self::assertSame(0, ApiResponse::page([], 1, 20, 0)['meta']['total_pages']);
    }

    public function testInvalidPaginationIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ApiResponse::page([], 0, 20, 0);
    }

    public function testErrorEnvelopeIsStable(): void
    {
        self::assertSame([
            'error' => ['code' => 'forbidden', 'message' => 'Access denied.'],
        ], ApiResponse::error('forbidden', 'Access denied.'));
    }

    public function testEmptyErrorCodeIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ApiResponse::error('', 'Failure');
    }
}
