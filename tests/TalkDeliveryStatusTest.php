<?php
declare(strict_types=1);

use Moves\Services\Talk\TalkDeliveryStatus;
use PHPUnit\Framework\TestCase;

final class TalkDeliveryStatusTest extends TestCase
{
    public function testSuccessfulDeliveryNeverRegresses(): void
    {
        self::assertTrue(TalkDeliveryStatus::accepts('sent','delivered'));
        self::assertTrue(TalkDeliveryStatus::accepts('sent','read'));
        self::assertTrue(TalkDeliveryStatus::accepts('delivered','read'));
        self::assertFalse(TalkDeliveryStatus::accepts('read','delivered'));
        self::assertFalse(TalkDeliveryStatus::accepts('read','sent'));
        self::assertFalse(TalkDeliveryStatus::accepts('delivered','sent'));
    }

    public function testFailureDoesNotOverrideConfirmedDeliveryAndCanRecover(): void
    {
        self::assertTrue(TalkDeliveryStatus::accepts('sent','failed'));
        self::assertFalse(TalkDeliveryStatus::accepts('delivered','failed'));
        self::assertFalse(TalkDeliveryStatus::accepts('read','failed'));
        self::assertTrue(TalkDeliveryStatus::accepts('failed','sent'));
        self::assertTrue(TalkDeliveryStatus::accepts('failed','delivered'));
        self::assertTrue(TalkDeliveryStatus::accepts('failed','read'));
    }

    public function testUnknownProviderStatusIsRejected(): void
    {
        self::assertFalse(TalkDeliveryStatus::accepts('sent','unknown'));
    }
}
