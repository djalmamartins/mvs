<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TalkMultiWhatsAppContractTest extends TestCase
{
    public function testInboundAndOutboundAreBoundToExactChannelAndTenant(): void
    {
        $in=(string)file_get_contents(__DIR__.'/../app/Services/Talk/TalkInboundService.php');
        $out=(string)file_get_contents(__DIR__.'/../app/Services/Talk/TalkOutboundService.php');
        self::assertStringContainsString("channel_id=:channel_id", $in);
        self::assertStringContainsString("tenant_id=:tenant_id", $in);
        self::assertStringContainsString("queue_id", $in);
        self::assertStringContainsString("cv.channel_id", $out);
        self::assertStringContainsString("AND tenant_id=:tenant_id", $out);
        self::assertStringContainsString("channelConfig['status']!=='active'", $out);
    }
}
