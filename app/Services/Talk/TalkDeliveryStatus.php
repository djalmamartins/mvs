<?php
declare(strict_types=1);

namespace Moves\Services\Talk;

final class TalkDeliveryStatus
{
    private const RANK = ['sent'=>1,'delivered'=>2,'read'=>3];

    public static function accepts(string $current,string $incoming): bool
    {
        if(!isset(self::RANK[$incoming])&&$incoming!=='failed')return false;
        if($incoming==='failed')return !in_array($current,['delivered','read'],true);
        if($current==='failed')return true;
        return self::RANK[$incoming] >= (self::RANK[$current]??0);
    }
}
