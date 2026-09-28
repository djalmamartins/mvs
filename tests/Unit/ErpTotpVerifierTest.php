<?php

declare(strict_types=1);

use Moves\Modules\Erp\Security\TotpVerifier;
use PHPUnit\Framework\TestCase;

final class ErpTotpVerifierTest extends TestCase
{
    private const RFC_SECRET_BASE32 = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    public function testMatchesRfc6238Sha1Vectors(): void
    {
        $verifier = new TotpVerifier(period: 30, digits: 8, window: 0);

        foreach ([
            59 => '94287082',
            1111111109 => '07081804',
            1111111111 => '14050471',
            1234567890 => '89005924',
            2000000000 => '69279037',
            20000000000 => '65353130',
        ] as $timestamp => $expected) {
            self::assertTrue($verifier->verify(self::RFC_SECRET_BASE32, $expected, $timestamp));
        }
    }

    public function testAcceptsOnlyConfiguredClockWindow(): void
    {
        $strict = new TotpVerifier(period: 30, digits: 8, window: 0);
        $tolerant = new TotpVerifier(period: 30, digits: 8, window: 1);

        self::assertFalse($strict->verify(self::RFC_SECRET_BASE32, '94287082', 89));
        self::assertTrue($tolerant->verify(self::RFC_SECRET_BASE32, '94287082', 89));
    }

    public function testRejectsMalformedCodeAndSecret(): void
    {
        $verifier = new TotpVerifier(window: 0);

        self::assertFalse($verifier->verify(self::RFC_SECRET_BASE32, '12345', 59));
        self::assertFalse($verifier->verify(self::RFC_SECRET_BASE32, '12345a', 59));
        self::assertFalse($verifier->verify('INVALID!SECRET', '123456', 59));
        self::assertFalse($verifier->verify('', '123456', 59));
    }

    public function testRejectsInvalidConfiguration(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TotpVerifier(period: 0);
    }
}
