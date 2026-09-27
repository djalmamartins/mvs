<?php

declare(strict_types=1);

use Moves\Services\Talk\WhatsAppIdentity;
use PHPUnit\Framework\TestCase;

final class WhatsAppIdentityTest extends TestCase
{
    public function testBrazilianMobilePhoneIsRestoredFromPnWithoutUsingDeviceId(): void
    {
        self::assertSame('5531996920154', WhatsAppIdentity::phone('+55 31 99692-0154'));
        self::assertSame('5531996920154', WhatsAppIdentity::phone('553196920154'));
        self::assertSame('553196920154@s.whatsapp.net', WhatsAppIdentity::jid('553196920154:3@s.whatsapp.net'));
    }

    public function testLidRemainsAnIdentifierAndNeverBecomesAPhone(): void
    {
        $identity = WhatsAppIdentity::inbound([
            'from_jid'=>'150350380150964:3@lid',
            'from_lid'=>'150350380150964:3@lid',
        ]);
        self::assertSame('150350380150964@lid', $identity['lid']);
        self::assertSame('150350380150964@lid', $identity['external_address']);
        self::assertSame('', $identity['phone_number']);
        self::assertSame('', WhatsAppIdentity::phone('150350380150964@lid'));
    }

    public function testPnAndLidAreStoredAsSeparateIdentities(): void
    {
        $identity = WhatsAppIdentity::inbound([
            'from_jid'=>'553196920154:7@s.whatsapp.net',
            'phone_jid'=>'553196920154:7@s.whatsapp.net',
            'from_lid'=>'150350380150964:3@lid',
            'phone_number'=>'5531996920154',
        ]);
        self::assertSame('553196920154@s.whatsapp.net', $identity['phone_jid']);
        self::assertSame('150350380150964@lid', $identity['lid']);
        self::assertSame('5531996920154', $identity['phone_number']);
    }
}
