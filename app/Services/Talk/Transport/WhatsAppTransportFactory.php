<?php

declare(strict_types=1);

namespace Moves\Services\Talk\Transport;

/** Resolves the configured provider without leaking provider concerns into Talk services. */
final class WhatsAppTransportFactory
{
    public static function make(): WhatsAppTransport
    {
        $driver = strtolower(trim(self::env('TALK_WHATSAPP_DRIVER', 'null')));

        if ($driver === 'baileys') {
            $url = trim(self::env('TALK_BAILEYS_URL', 'http://127.0.0.1:3011'));
            $token = trim(self::env('TALK_BAILEYS_BRIDGE_TOKEN'));
            return new BaileysWhatsAppTransport($url !== '' ? $url : 'http://127.0.0.1:3011', $token);
        }

        if ($driver === 'meta_cloud') {
            $phoneNumberId = trim(self::env('TALK_WHATSAPP_PHONE_NUMBER_ID'));
            $accessToken = trim(self::env('TALK_WHATSAPP_ACCESS_TOKEN'));
            $graphVersion = trim(self::env('TALK_WHATSAPP_GRAPH_VERSION', 'v23.0'));

            if ($phoneNumberId !== '' && $accessToken !== '') {
                return new MetaCloudWhatsAppTransport($phoneNumberId, $accessToken, $graphVersion !== '' ? $graphVersion : 'v23.0');
            }
        }

        return new NullWhatsAppTransport();
    }

    private static function env(string $key, string $default = ''): string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        return $value === false ? $default : (string)$value;
    }
}
