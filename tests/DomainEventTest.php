<?php

declare(strict_types=1);

use Moves\Core\DomainEvent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DomainEventTest extends TestCase
{
    public function testEventSerializesStableEnvelope(): void
    {
        $event = new DomainEvent(
            'condominium.created',
            'condominium',
            '42',
            ['name' => 'Residencial Teste']
        );

        $data = $event->toArray();

        self::assertSame('condominium.created', $data['name']);
        self::assertSame('condominium', $data['aggregate_type']);
        self::assertSame('42', $data['aggregate_id']);
        self::assertSame(['name' => 'Residencial Teste'], $data['payload']);
        self::assertNotSame('', $data['occurred_at']);
    }

    #[DataProvider('invalidIdentity')]
    public function testEventRejectsIncompleteIdentity(string $name, string $type, string $id): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DomainEvent($name, $type, $id, []);
    }

    /** @return array<string, array{string, string, string}> */
    public static function invalidIdentity(): array
    {
        return [
            'name' => ['', 'condominium', '42'],
            'aggregate type' => ['condominium.created', '', '42'],
            'aggregate id' => ['condominium.created', 'condominium', ''],
        ];
    }
}
