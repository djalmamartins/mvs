<?php
declare(strict_types=1);
namespace Moves\Services\Talk\Transport;
use RuntimeException;
final class BaileysWhatsAppTransport implements WhatsAppTransport{
 public function __construct(private readonly string $baseUrl,private readonly string $token=''){}
 public function sendText(string $channelKey,string $to,string $text,?string $idempotencyKey=null):array{return $this->request('POST',$this->channelPath($channelKey).'/send/text',['to'=>$to,'text'=>$text,'idempotency_key'=>$idempotencyKey]);}
 public function sendMedia(string $channelKey,string $to,string $absolutePath,string $mimeType,?string $caption=null):array{throw new RuntimeException('Envio de mídia pelo bridge local ainda não está habilitado.');}
 public function status(string $channelKey):array{try{$r=$this->request('GET',$this->channelPath($channelKey).'/status');return $r+['driver'=>'baileys'];}catch(\Throwable $e){return ['status'=>'disconnected','connected'=>false,'detail'=>'Bridge local indisponível: '.$e->getMessage(),'qr'=>null,'profile'=>null,'driver'=>'baileys'];}}
 public function connect(string $channelKey,string $externalId):array{return $this->request('POST',$this->channelPath($channelKey).'/connect',['channel_external_id'=>$externalId]);}
 public function logout(string $channelKey):array{return $this->request('POST',$this->channelPath($channelKey).'/logout');}
 private function channelPath(string $channelKey):string{if(!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/D',$channelKey))throw new RuntimeException('Identificador de sessão inválido.');return '/channels/'.rawurlencode($channelKey);}
 private function request(string $method,string $path,?array $payload=null):array{if(!function_exists('curl_init'))throw new RuntimeException('Extensão PHP cURL não está habilitada.');$ch=curl_init(rtrim($this->baseUrl,'/').$path);if($ch===false)throw new RuntimeException('Falha ao iniciar bridge WhatsApp.');$headers=['Accept: application/json'];if($this->token!=='')$headers[]='Authorization: Bearer '.$this->token;$opt=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>8,CURLOPT_HTTPHEADER=>$headers];if($method==='POST'){$headers[]='Content-Type: application/json';$opt[CURLOPT_POST]=true;$opt[CURLOPT_POSTFIELDS]=json_encode($payload??[],JSON_THROW_ON_ERROR);$opt[CURLOPT_HTTPHEADER]=$headers;}curl_setopt_array($ch,$opt);$raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$err=curl_error($ch);if($raw===false||$status<200||$status>=300)throw new RuntimeException($err!==''?$err:'HTTP '.$status);$data=json_decode((string)$raw,true);if(!is_array($data))throw new RuntimeException('Resposta inválida do bridge WhatsApp.');if(isset($data['error']))throw new RuntimeException((string)$data['error']);return $data;}
}
