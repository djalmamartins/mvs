<?php

declare(strict_types=1);

use Moves\Core\IdempotencyKey;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IdempotencyKeyTest extends TestCase
{
    public function testKeyIsTrimmedAndPreserved(): void
    {
        self::assertSame('payment:42.retry-1', IdempotencyKey::normalize(' payment:42.retry-1 '));
    }

    #[DataProvider('invalidKeys')]
    public function testInvalidKeysAreRejected(?string $key): void
    {
        $this->expectException(InvalidArgumentException::class);
        IdempotencyKey::normalize($key);
    }

    /** @return array<string, array{0: ?string}> */
    public static function invalidKeys(): array
    {
        return [
            'missing' => [null],
            'empty' => ['   '],
            'spaces' => ['payment key'],
            'unsafe' => ['payment/../../42'],
            'too long' => [str_repeat('a', 129)],
        ];
    }
}
