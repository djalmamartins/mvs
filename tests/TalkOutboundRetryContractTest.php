<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TalkOutboundRetryContractTest extends TestCase
{
    public function testRetryIsRestrictedToFailedOutboundMessageAndReusesTheRow(): void
    {
        $service=(string)file_get_contents(__DIR__.'/../app/Services/Talk/TalkOutboundService.php');
        self::assertStringContainsString("direction='outbound'", $service);
        self::assertStringContainsString("['delivery_status']??'')!=='failed'", $service);
        self::assertStringContainsString('Somente mensagens com falha podem ser reenviadas.', $service);
        self::assertStringContainsString('UPDATE talk_messages SET external_id=:external_id', $service);
        self::assertStringContainsString("['retry_of']=\$retryMessageId", $service);
        self::assertStringContainsString("'message.retried'", $service);
    }

    public function testRetryActionAndFailureFeedbackAreAvailableToOperator(): void
    {
        $controller=(string)file_get_contents(__DIR__.'/../app/Controllers/TalkController.php');
        $view=(string)file_get_contents(__DIR__.'/../resources/themes/app/pages/talk.php');
        self::assertStringContainsString("action === 'retry_message'", $controller);
        self::assertStringContainsString("name=\"action\" value=\"retry_message\"", $view);
        self::assertStringContainsString("deliveryStatus==='failed'", $view);
        self::assertStringContainsString('WhatsApp Cloud API:', $controller);
    }
}
