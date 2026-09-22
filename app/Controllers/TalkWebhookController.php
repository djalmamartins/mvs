<?php
declare(strict_types=1);

namespace Moves\Controllers;

use Moves\Boot\Connection;
use Moves\Services\Talk\TalkInboundService;
use Moves\Services\Talk\TalkDeliveryStatus;
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
            $entries=$payload['entry']??[];
            if(!is_array($entries))$entries=[];
            foreach($entries as $entry){
                if(!is_array($entry))continue;
                $changes=$entry['changes']??[];
                if(!is_array($changes))continue;
                foreach($changes as $change){
                    if(!is_array($change))continue;
                    $value=$change['value']??[];
                    if(!is_array($value))continue;
                    $metadata=$value['metadata']??[];
                    if(!is_array($metadata))$metadata=[];
                    $phoneId=trim((string)($metadata['phone_number_id']??''));
                    if($phoneId==='')continue;

                    $s=$pdo->prepare("SELECT id FROM talk_channels WHERE type='whatsapp' AND external_account_id=:external_id AND status='active' ORDER BY id LIMIT 2");
                    $s->execute(['external_id'=>$phoneId]);
                    $channels=$s->fetchAll(PDO::FETCH_COLUMN);
                    if(count($channels)!==1)continue;
                    $channelId=(int)$channels[0];

                    $messages=$value['messages']??[];
                    if(!is_array($messages))$messages=[];
                    foreach($messages as $message){
                        if(!is_array($message))continue;
                        $type=(string)($message['type']??'text');
                        $media=in_array($type,['image','document','audio','video'],true)&&is_array($message[$type]??null)?$message[$type]:[];
                        $text=$message['text']??[];
                        if(!is_array($text))$text=[];
                        $body=$type==='text'?(string)($text['body']??''):(string)($media['caption']??'');
                        $inbound->receiveWhatsApp($channelId,[
                            'id'=>(string)($message['id']??''),
                            'from'=>(string)($message['from']??''),
                            'type'=>$type,
                            'body'=>$body,
                            'media_id'=>(string)($media['id']??''),
                            'mime_type'=>(string)($media['mime_type']??''),
                            'filename'=>(string)($media['filename']??''),
                        ]);
                    }

                    $statuses=$value['statuses']??[];
                    if(!is_array($statuses))$statuses=[];
                    foreach($statuses as $status){
                        if(!is_array($status))continue;
                        $externalId=trim((string)($status['id']??''));$delivery=trim((string)($status['status']??''));if($externalId===''||!in_array($delivery,['sent','delivered','read','failed'],true))continue;
                        $m=$pdo->prepare("SELECT id,metadata FROM talk_messages WHERE external_id=:external_id AND direction='outbound' LIMIT 1");$m->execute(['external_id'=>$externalId]);$row=$m->fetch(PDO::FETCH_ASSOC);if(!$row)continue;
                        $messageMetadata=json_decode((string)($row['metadata']??''),true);if(!is_array($messageMetadata))$messageMetadata=[];
                        if((int)($messageMetadata['channel_id']??0)!==$channelId)continue;$current=(string)($messageMetadata['delivery_status']??'');if(!TalkDeliveryStatus::accepts($current,$delivery))continue;
                        $messageMetadata['delivery_status']=$delivery;$messageMetadata['delivery_updated_at']=date('Y-m-d H:i:s');if($delivery==='failed'&&isset($status['errors']))$messageMetadata['delivery_errors']=$status['errors'];elseif($delivery!=='failed')unset($messageMetadata['delivery_errors']);
                        $u=$pdo->prepare("UPDATE talk_messages SET metadata=:metadata WHERE id=:id");$u->execute(['metadata'=>json_encode($messageMetadata,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),'id'=>(int)$row['id']]);
                    }
                }
            }
            http_response_code(200);echo 'EVENT_RECEIVED';
        }catch(\Throwable){http_response_code(500);}
        exit;
    }
}
