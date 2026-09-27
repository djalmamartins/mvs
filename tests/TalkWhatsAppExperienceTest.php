<?php

declare(strict_types=1);

use Moves\Services\Talk\Transport\BaileysWhatsAppTransport;
use PHPUnit\Framework\TestCase;

final class TalkWhatsAppExperienceTest extends TestCase
{
    public function testConnectionPageKeepsQrInsideExplicitLifecycleModal(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__).'/resources/themes/admin/pages/partials/talk-channels.php');
        $script = (string) file_get_contents(dirname(__DIR__).'/public/themes/admin/js/talk.js');
        self::assertStringContainsString('data-connect-dialog', $view);
        self::assertStringContainsString('data-connect-qr', $view);
        self::assertStringNotContainsString('data-channel-qr', $view);
        foreach (['starting', 'qr', 'connected', 'reconnecting', 'expired'] as $state) self::assertStringContainsString("state === '{$state}'", $script);
        self::assertStringContainsString("error: 'Erro'", $script);
        self::assertStringContainsString("request(id, 'disconnect')", $script);
        self::assertStringContainsString("request(id, 'remove')", $script);
    }

    public function testUnavailableBridgeNeverReportsConnected(): void
    {
        $status = (new BaileysWhatsAppTransport('http://127.0.0.1:9', 'test'))->status('test-channel');
        self::assertFalse($status['connected']);
        self::assertSame('disconnected', $status['status']);
        self::assertStringContainsString('Bridge local indisponível', (string)$status['detail']);
    }
}
