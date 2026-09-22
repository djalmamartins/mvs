<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TalkWhatsAppWebhookContractTest extends TestCase
{
    public function testWebhookRequiresVerificationAndSignedPayload(): void
    {
        $source=(string)file_get_contents(__DIR__.'/../app/Controllers/TalkWebhookController.php');
        self::assertStringContainsString('TALK_WHATSAPP_VERIFY_TOKEN',$source);
        self::assertStringContainsString('TALK_WHATSAPP_APP_SECRET',$source);
        self::assertStringContainsString('HTTP_X_HUB_SIGNATURE_256',$source);
        self::assertStringContainsString("hash_hmac('sha256'",$source);
        self::assertStringContainsString("phone_number_id",$source);
        self::assertStringContainsString("external_account_id=:external_id",$source);
        self::assertStringContainsString("LIMIT 2",$source);
        self::assertStringContainsString("\$value['statuses']",$source);
        self::assertStringContainsString("['sent','delivered','read','failed']",$source);
        self::assertStringContainsString("delivery_updated_at",$source);
    }

    public function testChannelActivationRequiresSuccessfulProviderStatus(): void
    {
        $service=(string)file_get_contents(__DIR__.'/../app/Services/Talk/TalkService.php');
        $controller=(string)file_get_contents(__DIR__.'/../app/Controllers/TalkController.php');
        $view=(string)file_get_contents(__DIR__.'/../resources/themes/app/pages/talk.php');

        self::assertStringContainsString('WhatsAppTransportFactory::make($channel)->status()', $service);
        self::assertStringContainsString("\$connected?'active':'error'", $service);
        self::assertStringContainsString("status='connecting'", $service);
        self::assertStringContainsString("Request::post('external_account_id','')", $controller);
        self::assertStringContainsString("action === 'channel_test'", $controller);
        self::assertStringContainsString('name="external_account_id"', $view);
        self::assertStringContainsString('name="action" value="channel_test"', $view);
        self::assertStringNotContainsString('name="access_token"', $view);
        self::assertStringNotContainsString('name="app_secret"', $view);
    }
}
