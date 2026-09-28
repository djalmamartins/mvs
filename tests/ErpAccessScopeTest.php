<?php

declare(strict_types=1);

use Moves\Modules\Erp\Security\AccessScope;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ErpAccessScopeTest extends TestCase
{
    public function testBuildsAdministratorAndCondominiumScopes(): void
    {
        $administrator = AccessScope::administrator(10);
        $condominium = AccessScope::condominium(20);

        self::assertSame(AccessScope::ADMINISTRATOR, $administrator->type);
        self::assertSame(10, $administrator->id);
        self::assertSame(AccessScope::CONDOMINIUM, $condominium->type);
        self::assertSame(20, $condominium->id);
        self::assertTrue($administrator->equals(AccessScope::administrator(10)));
        self::assertFalse($administrator->equals($condominium));
    }

    #[DataProvider('invalidScopes')]
    public function testRejectsInvalidScopes(string $type, int $id): void
    {
        $this->expectException(InvalidArgumentException::class);
        new AccessScope($type, $id);
    }

    /** @return array<string, array{string, int}> */
    public static function invalidScopes(): array
    {
        return [
            'unknown type' => ['global', 1],
            'zero id' => [AccessScope::ADMINISTRATOR, 0],
            'negative id' => [AccessScope::CONDOMINIUM, -1],
        ];
    }
}
