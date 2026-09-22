<?php
declare(strict_types=1);

namespace Moves\Controllers;

use Moves\Boot\Connection;
use Moves\Services\Talk\TalkInboundService;
use PDO;

final class TalkWebhookController
{
    public function verify(): never
    {
        $mode=(string)($_GET['hub_mode']??$_GET['hub.mode']??'');$token=(string)($_GET['hub_verify_token']??$_GET['hub.verify_token']??'');$challenge=(string)($_GET['hub_challenge']??$_GET['hub.challenge']??'');
        $expected=(string)($_ENV['TALK_WHATSAPP_VERIFY_TOKEN']??$_SERVER['TALK_WHATSAPP_VERIFY_TOKEN']??getenv('TALK_WHATSAPP_VERIFY_TOKEN')?:'');
        if($mode==='subscribe'&&$expected!==''&&hash_equals($expected,$token)){http_response_code(200);header('Content-Type: text/plain; charset=utf-8');echo $challenge;exit;}
        http_response_code(403);exit;
    }

    public function receive(): never
    {
        $raw=(string)file_get_contents('php://input');$secret=(string)($_ENV['TALK_WHATSAPP_APP_SECRET']??$_SERVER['TALK_WHATSAPP_APP_SECRET']??getenv('TALK_WHATSAPP_APP_SECRET')?:'');
        $signature=(string)($_SERVER['HTTP_X_HUB_SIGNATURE_256']??'');
        if($secret===''||!str_starts_with($signature,'sha256=')||!hash_equals('sha256='.hash_hmac('sha256',$raw,$secret),$signature)){http_response_code(401);exit;}
        $payload=json_decode($raw,true);if(!is_array($payload)){http_response_code(400);exit;}
        $inbound=new TalkInboundService();$pdo=Connection::getInstance();
        try{
            foreach(($payload['entry']??[]) as $entry)foreach(($entry['changes']??[]) as $change){$value=$change['value']??[];$phoneId=trim((string)($value['metadata']['phone_number_id']??''));if($phoneId==='')continue;
                $s=$pdo->prepare("SELECT id FROM talk_channels WHERE type='whatsapp' AND external_account_id=:external_id AND status='active' ORDER BY id LIMIT 2");$s->execute(['external_id'=>$phoneId]);$channels=$s->fetchAll(PDO::FETCH_COLUMN);if(count($channels)!==1)continue;$channelId=(int)$channels[0];
                foreach(($value['messages']??[]) as $message){$type=(string)($message['type']??'text');$body=$type==='text'?(string)($message['text']['body']??''):'';$inbound->receiveWhatsApp($channelId,['id'=>(string)($message['id']??''),'from'=>(string)($message['from']??''),'type'=>$type,'body'=>$body]);}
                foreach(($value['statuses']??[]) as $status){$externalId=trim((string)($status['id']??''));$delivery=trim((string)($status['status']??''));if($externalId===''||!in_array($delivery,['sent','delivered','read','failed'],true))continue;$m=$pdo->prepare("SELECT id,metadata FROM talk_messages WHERE external_id=:external_id AND direction='outbound' LIMIT 1");$m->execute(['external_id'=>$externalId]);$row=$m->fetch(PDO::FETCH_ASSOC);if(!$row)continue;$metadata=json_decode((string)($row['metadata']??''),true);if(!is_array($metadata))$metadata=[];if((int)($metadata['channel_id']??0)!==$channelId)continue;$current=(string)($metadata['delivery_status']??'');$rank=['sent'=>1,'delivered'=>2,'read'=>3];$accept=$delivery==='failed'?(!in_array($current,['delivered','read'],true)):($current==='failed'||$rank[$delivery]>=($rank[$current]??0));if(!$accept)continue;$metadata['delivery_status']=$delivery;$metadata['delivery_updated_at']=date('Y-m-d H:i:s');if($delivery==='failed'&&isset($status['errors']))$metadata['delivery_errors']=$status['errors'];elseif($delivery!=='failed')unset($metadata['delivery_errors']);$u=$pdo->prepare("UPDATE talk_messages SET metadata=:metadata WHERE id=:id");$u->execute(['metadata'=>json_encode($metadata,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),'id'=>(int)$row['id']]);}
            }
            http_response_code(200);echo 'EVENT_RECEIVED';
        }catch(\Throwable){http_response_code(500);}
        exit;
    }
}
