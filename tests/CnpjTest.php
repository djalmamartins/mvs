<?php

declare(strict_types=1);

namespace Moves\Tests;

use Moves\Services\Platform\Cnpj;
use PHPUnit\Framework\TestCase;

final class CnpjTest extends TestCase
{
    public function testValidatesNumericAndAlphanumericCnpjUsingTheSameVerifierRule(): void
    {
        self::assertTrue(Cnpj::isValid('11.222.333/0001-81'));
        self::assertTrue(Cnpj::isValid('12.ABC.345/01DE-35'));
        self::assertFalse(Cnpj::isValid('12.ABC.345/01DE-34'));
        self::assertFalse(Cnpj::isValid('00000000000000'));
        self::assertFalse(Cnpj::isValid('SEM CNPJ'));
        self::assertSame('12ABC34501DE35', Cnpj::normalize('12.ABC.345/01DE-35'));
        self::assertSame('12.ABC.345/01DE-35', Cnpj::format('12abc34501de35'));
        self::assertNull(Cnpj::normalize(' .-/ '));
    }
}
