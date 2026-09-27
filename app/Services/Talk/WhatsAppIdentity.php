<?php

declare(strict_types=1);

namespace Moves\Services\Talk;

final class WhatsAppIdentity
{
    public static function jid(mixed $value): string
    {
        $jid = trim((string) $value);
        if (preg_match('/^\d+(?::\d+)?@(s\.whatsapp\.net|lid)$/D', $jid) !== 1) return '';
        return (string) preg_replace('/:\d+(?=@)/', '', $jid);
    }

    public static function phone(mixed $value): string
    {
        $raw = trim((string) $value);
        if (str_ends_with($raw, '@lid')) return '';
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if (preg_match('/^55\d{2}[6-9]\d{7}$/D', $digits) === 1) {
            $digits = substr($digits, 0, 4).'9'.substr($digits, 4);
        }
        return preg_match('/^\d{8,15}$/D', $digits) === 1 ? $digits : '';
    }

    /** @param array<string,mixed> $payload @return array{jid:string,lid:string,phone_jid:string,phone_number:string,external_address:string} */
    public static function inbound(array $payload): array
    {
        $sender = self::jid($payload['from_jid'] ?? '');
        $phoneJid = self::jid($payload['phone_jid'] ?? '');
        if ($phoneJid !== '' && !str_ends_with($phoneJid, '@s.whatsapp.net')) $phoneJid = '';
        if ($phoneJid === '' && str_ends_with($sender, '@s.whatsapp.net')) $phoneJid = $sender;
        $lid = self::jid($payload['from_lid'] ?? '');
        if ($lid === '' && str_ends_with($sender, '@lid')) $lid = $sender;
        $phone = self::phone($payload['phone_number'] ?? $payload['from'] ?? '');
        if ($phone === '' && $phoneJid !== '') $phone = self::phone(strstr($phoneJid, '@', true));
        $external = $phoneJid !== '' ? $phoneJid : ($lid !== '' ? $lid : $sender);
        return ['jid'=>$sender,'lid'=>$lid,'phone_jid'=>$phoneJid,'phone_number'=>$phone,'external_address'=>$external];
    }
}
