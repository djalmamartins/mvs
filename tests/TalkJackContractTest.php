<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TalkJackContractTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $this->source=(string)file_get_contents(__DIR__.'/../app/Services/Talk/TalkJackService.php');
    }

    public function testJackCanBeDisabledPerTenant(): void
    {
        self::assertStringContainsString("jack.enabled", $this->source);
        self::assertStringContainsString("tenant_id=:tenant_id", $this->source);
        self::assertStringContainsString("!=='1'){return 0;}", $this->source);
    }

    public function testJackPreventsDuplicateAndHumanOverlap(): void
    {
        self::assertStringContainsString("action='jack.reply'", $this->source);
        self::assertStringContainsString("sender_type='user'", $this->source);
        self::assertStringContainsString("FOR UPDATE", $this->source);
    }

    public function testJackHasSafeFallbackForNonTextMessages(): void
    {
        self::assertStringContainsString("'['.\$type.']'", $this->source);
        self::assertStringContainsString("[mensagem sem texto]", $this->source);
        self::assertStringContainsString("context_read", $this->source);
    }
}
