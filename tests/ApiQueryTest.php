<?php

declare(strict_types=1);

use Moves\Core\ApiQuery;
use PHPUnit\Framework\TestCase;

final class ApiQueryTest extends TestCase
{
    public function testPaginationUsesDefaults(): void
    {
        self::assertSame(['page' => 1, 'per_page' => 20], ApiQuery::pagination([]));
    }

    public function testPaginationCapsPageSize(): void
    {
        self::assertSame(['page' => 2, 'per_page' => 100], ApiQuery::pagination([
            'page' => '2',
            'per_page' => '500',
        ]));
    }

    public function testInvalidPaginationIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ApiQuery::pagination(['page' => '0']);
    }

    public function testFiltersOnlyReturnAllowlistedKeys(): void
    {
        self::assertSame([
            'status' => 'active',
        ], ApiQuery::filters([
            'status' => ' active ',
            'admin' => '1',
        ], ['status']));
    }

    public function testNestedFilterIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ApiQuery::filters(['status' => ['active']], ['status']);
    }
}
