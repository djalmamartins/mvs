<?php
declare(strict_types=1);

namespace Moves\Services\Talk;

use Moves\Boot\Connection;
use PDO;
use RuntimeException;

final class TalkProtocolService
{
    public function next(int $tenantId,?int $condominiumId=null): string
    {
        if($tenantId<=0)throw new RuntimeException('Administradora inválida para protocolo.');
        $pdo=Connection::getInstance();$year=(int)date('Y');$ownsTransaction=!$pdo->inTransaction();if($ownsTransaction)$pdo->beginTransaction();
        try{
            $tenant=$pdo->prepare("SELECT protocol_prefix,protocol_digits FROM talk_tenants WHERE id=:id AND status='active' LIMIT 1");$tenant->execute(['id'=>$tenantId]);$config=$tenant->fetch(PDO::FETCH_ASSOC);if(!$config)throw new RuntimeException('Administradora indisponível para protocolo.');
            $configuredPrefix=strtoupper(preg_replace('/[^A-Z0-9]/i','',(string)$config['protocol_prefix'])??'');if($configuredPrefix==='')$configuredPrefix='TALK';$configuredPrefix=substr($configuredPrefix,0,12);$digits=max(1,min(12,(int)$config['protocol_digits']));
            $pdo->prepare("INSERT INTO talk_protocol_sequences(tenant_id,year,last_number) VALUES(:tenant_id,:year,0) ON DUPLICATE KEY UPDATE last_number=last_number")->execute(['tenant_id'=>$tenantId,'year'=>$year]);
            $lock=$pdo->prepare("SELECT last_number FROM talk_protocol_sequences WHERE tenant_id=:tenant_id AND year=:year FOR UPDATE");$lock->execute(['tenant_id'=>$tenantId,'year'=>$year]);$sequence=(int)$lock->fetchColumn()+1;
            $pdo->prepare("UPDATE talk_protocol_sequences SET last_number=:number WHERE tenant_id=:tenant_id AND year=:year")->execute(['number'=>$sequence,'tenant_id'=>$tenantId,'year'=>$year]);
            $number=str_pad((string)$sequence,$digits,'0',STR_PAD_LEFT);$identified=$condominiumId!==null&&$condominiumId>0;$prefix=$identified?$configuredPrefix:'TK';$scope=$identified?(string)$condominiumId:'00';$protocol=$prefix.'-'.$year.'-'.$number.'-'.$scope;if($ownsTransaction)$pdo->commit();return $protocol;
        }catch(\Throwable $e){if($ownsTransaction&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
}
