<?php

declare(strict_types=1);

namespace Moves\Services\Talk\Transport;

use RuntimeException;

/**
 * Safe default used while no WhatsApp provider is configured.
 * It never pretends that a message was delivered.
 */
final class NullWhatsAppTransport implements WhatsAppTransport
{
    public function sendText(string $to, string $text): array
    {
        $this->assertRecipient($to);
        throw new RuntimeException('WhatsApp não está conectado. Configure um transporte antes de enviar mensagens.');
    }

    public function sendMedia(string $to, string $absolutePath, string $mimeType, ?string $caption = null): array
    {
        $this->assertRecipient($to);
        throw new RuntimeException('WhatsApp não está conectado. Configure um transporte antes de enviar mídia.');
    }

    public function status(): array
    {
        return [
            'status' => 'disconnected',
            'connected' => false,
            'detail' => 'Nenhum transporte WhatsApp configurado.',
        ];
    }

    private function assertRecipient(string $to): void
    {
        if (trim($to) === '') {
            throw new RuntimeException('Destinatário do WhatsApp é obrigatório.');
        }
    }
}
