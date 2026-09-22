<?php

declare(strict_types=1);

namespace Moves\Services\Talk\Transport;

/**
 * Boundary between Moves Talk and a concrete WhatsApp provider.
 *
 * Keeping provider concerns behind this contract prevents controllers and
 * ticket services from depending on Baileys, Meta Cloud API or any future
 * gateway directly.
 */
interface WhatsAppTransport
{
    /** @return array{message_id:string,status:string} */
    public function sendText(string $to, string $text): array;

    /** @return array{message_id:string,status:string} */
    public function sendMedia(string $to, string $absolutePath, string $mimeType, ?string $caption = null): array;

    /** @return array{status:string,connected:bool,detail:?string} */
    public function status(): array;
}
