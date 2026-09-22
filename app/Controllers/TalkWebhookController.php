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
            }
            http_response_code(200);echo 'EVENT_RECEIVED';
        }catch(\Throwable){http_response_code(500);}
        exit;
    }
}
