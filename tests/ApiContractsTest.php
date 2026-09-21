<?php

declare(strict_types=1);

use Moves\Core\ApiQuery;
use Moves\Core\IdempotencyKey;
use PHPUnit\Framework\TestCase;

final class ApiContractsTest extends TestCase
{
    public function testQueryDefaultsAndLimitsAreStable(): void
    {
        self::assertSame([
            'page' => 1,
            'per_page' => 20,
            'sort' => null,
            'direction' => 'asc',
            'filters' => [],
        ], ApiQuery::normalize([]));

        self::assertSame(20, ApiQuery::normalize(['per_page' => 1000])['per_page']);
    }

    public function testAllowedSortAndScalarFiltersAreNormalized(): void
    {
        $query = ApiQuery::normalize([
            'page' => '2',
            'per_page' => '50',
            'sort' => 'name',
            'direction' => 'DESC',
            'filter' => ['status' => ' active '],
        ], ['name']);

        self::assertSame(2, $query['page']);
        self::assertSame(50, $query['per_page']);
        self::assertSame('name', $query['sort']);
        self::assertSame('desc', $query['direction']);
        self::assertSame(['status' => 'active'], $query['filters']);
    }

    public function testUnsupportedSortIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ApiQuery::normalize(['sort' => 'password'], ['name']);
    }

    public function testIdempotencyFingerprintIsStableAndScoped(): void
    {
        $first = IdempotencyKey::fingerprint('req-123', 'erp:payments');
        self::assertSame($first, IdempotencyKey::fingerprint('req-123', 'erp:payments'));
        self::assertNotSame($first, IdempotencyKey::fingerprint('req-123', 'erp:invoices'));
    }

    public function testInvalidIdempotencyKeyIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        IdempotencyKey::normalize('invalid key with spaces');
    }
}
