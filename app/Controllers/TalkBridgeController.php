<?php
declare(strict_types=1);
namespace Moves\Controllers;
use Moves\Core\Controller;
use Moves\Services\Talk\TalkInboundService;
final class TalkBridgeController extends Controller{
 public function inbound():never{
  header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
  $expected=(string)($_ENV['TALK_BAILEYS_BRIDGE_TOKEN']??$_SERVER['TALK_BAILEYS_BRIDGE_TOKEN']??getenv('TALK_BAILEYS_BRIDGE_TOKEN')?:'');
  $auth=(string)($_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'');
  if($auth===''&&function_exists('getallheaders')){$headers=getallheaders();$auth=(string)($headers['Authorization']??$headers['authorization']??'');}
  $provided=preg_match('/^Bearer\s+(.+)$/i',$auth,$match)===1?trim((string)$match[1]):'';
  if($expected===''||!hash_equals($expected,$provided)){http_response_code(401);echo json_encode(['error'=>'unauthorized']);exit;}
  try{$raw=file_get_contents('php://input');$payload=json_decode((string)$raw,true,512,JSON_THROW_ON_ERROR);$ticket=(new TalkInboundService())->receiveWhatsApp(is_array($payload)?$payload:[]);echo json_encode(['ok'=>true,'ticket_id'=>$ticket],JSON_THROW_ON_ERROR);}
  catch(\Throwable $e){http_response_code(422);echo json_encode(['error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}
  exit;
 }
}
