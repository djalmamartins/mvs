<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TalkHandoffContractTest extends TestCase
{
    public function testHumanClaimRespectsConfiguredJackSummary(): void
    {
        $source=(string)file_get_contents(__DIR__.'/../app/Services/Talk/TalkService.php');
        self::assertStringContainsString("jack.transfer_summary", $source);
        self::assertStringContainsString("action,summary,payload) VALUES(:ticket_id,NULL,'jack.handoff'", $source);
        self::assertStringContainsString("preserved_context", $source);
    }
}
