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
        self::assertStringContainsString("(int)(\$metadata['channel_id']??0)!==\$channelId",$source);
        self::assertStringContainsString("TalkDeliveryStatus::accepts(\$current,\$delivery)",$source);
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

    public function testDeliveryChangesParticipateInLiveSync(): void
    {
        $service=(string)file_get_contents(__DIR__.'/../app/Services/Talk/TalkService.php');
        $javascript=(string)file_get_contents(__DIR__.'/../public/themes/app/js/app.js');

        self::assertStringContainsString('MAX(m.updated_at)', $service);
        self::assertStringContainsString('delivery_status', $service);
        self::assertStringContainsString('item.dataset.deliveryStatus', $javascript);
        self::assertStringContainsString('item.delivery !== nextMessages[index].delivery', $javascript);
        self::assertStringContainsString("cache: 'no-store'", $javascript);
    }

    public function testInboundMediaIsParsedDownloadedAndPersisted(): void
    {
        $webhook=(string)file_get_contents(__DIR__.'/../app/Controllers/TalkWebhookController.php');
        $inbound=(string)file_get_contents(__DIR__.'/../app/Services/Talk/TalkInboundService.php');
        $transport=(string)file_get_contents(__DIR__.'/../app/Services/Talk/Transport/MetaCloudWhatsAppTransport.php');

        self::assertStringContainsString("['image','document','audio','video']", $webhook);
        self::assertStringContainsString("'media_id'=>(string)(\$media['id']??'')", $webhook);
        self::assertStringContainsString("'mime_type'=>(string)(\$media['mime_type']??'')", $webhook);
        self::assertStringContainsString("'filename'=>(string)(\$media['filename']??'')", $webhook);
        self::assertStringContainsString('downloadMedia($mediaId)', $inbound);
        self::assertStringContainsString('INSERT INTO talk_attachments', $inbound);
        self::assertStringContainsString('UPDATE talk_messages SET media_url=:path', $inbound);
        self::assertStringContainsString('public function downloadMedia(string $mediaId)', $transport);
        self::assertStringContainsString("strlen(\$bytes)>10_485_760", $transport);
    }
}
