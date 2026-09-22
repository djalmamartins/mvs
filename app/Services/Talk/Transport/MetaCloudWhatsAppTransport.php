<?php

declare(strict_types=1);

namespace Moves\Services\Talk\Transport;

use RuntimeException;

/**
 * WhatsApp Cloud API transport.
 * Credentials are injected by configuration; no provider secret is persisted by Talk.
 */
final class MetaCloudWhatsAppTransport implements WhatsAppTransport
{
    public function __construct(
        private readonly string $phoneNumberId,
        private readonly string $accessToken,
        private readonly string $graphVersion = 'v23.0',
    ) {
        if (trim($this->phoneNumberId) === '' || trim($this->accessToken) === '') {
            throw new RuntimeException('Credenciais do WhatsApp Cloud API não configuradas.');
        }
    }

    public function sendText(string $to, string $text): array
    {
        $to = $this->normalizeRecipient($to);
        $text = trim($text);
        if ($text === '') {
            throw new RuntimeException('Mensagem do WhatsApp vazia.');
        }

        $response = $this->request('POST', $this->phoneNumberId.'/messages', [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => ['preview_url' => false, 'body' => $text],
        ]);

        return $this->deliveryResult($response);
    }

    public function sendMedia(string $to, string $absolutePath, string $mimeType, ?string $caption = null): array
    {
        $to = $this->normalizeRecipient($to);
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            throw new RuntimeException('Arquivo de mídia indisponível para envio.');
        }

        $type = $this->mediaType($mimeType);
        $mediaId = $this->uploadMedia($absolutePath, $mimeType);
        $media = ['id' => $mediaId];
        $caption = trim((string)$caption);
        if ($caption !== '' && in_array($type, ['image', 'video', 'document'], true)) {
            $media['caption'] = mb_substr($caption, 0, 1024);
        }
        if ($type === 'document') {
            $media['filename'] = basename($absolutePath);
        }

        $response = $this->request('POST', $this->phoneNumberId.'/messages', [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => $type,
            $type => $media,
        ]);

        return $this->deliveryResult($response);
    }

    /** @return array{bytes:string,mime_type:string} */
    public function downloadMedia(string $mediaId): array
    {
        $mediaId=trim($mediaId);if($mediaId==='')throw new RuntimeException('Identificador de mídia do WhatsApp ausente.');
        $metadata=$this->request('GET',rawurlencode($mediaId));$url=trim((string)($metadata['url']??''));$mime=trim((string)($metadata['mime_type']??'application/octet-stream'));
        if($url===''||!str_starts_with($url,'https://'))throw new RuntimeException('WhatsApp não retornou uma URL de mídia válida.');
        $ch=curl_init($url);if($ch===false)throw new RuntimeException('Não foi possível iniciar o download da mídia.');
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$this->accessToken]]);
        $bytes=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$error=curl_error($ch);curl_close($ch);
        if(!is_string($bytes)||$status<200||$status>=300)throw new RuntimeException($this->errorMessage('', $status, $error));
        if($bytes===''||strlen($bytes)>10_485_760)throw new RuntimeException('Mídia recebida vazia ou maior que 10 MB.');
        return ['bytes'=>$bytes,'mime_type'=>$mime];
    }

    public function status(): array
    {
        try {
            $response = $this->request('GET', $this->phoneNumberId.'?fields=id,display_phone_number,verified_name');
            return [
                'status' => 'connected',
                'connected' => isset($response['id']),
                'detail' => isset($response['display_phone_number']) ? (string)$response['display_phone_number'] : null,
            ];
        } catch (\Throwable $e) {
            return ['status' => 'disconnected', 'connected' => false, 'detail' => $e->getMessage()];
        }
    }

    private function uploadMedia(string $path, string $mimeType): string
    {
        $url = $this->endpoint($this->phoneNumberId.'/media');
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Não foi possível iniciar o envio de mídia.');
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer '.$this->accessToken],
            CURLOPT_POSTFIELDS => [
                'messaging_product' => 'whatsapp',
                'type' => $mimeType,
                'file' => new \CURLFile($path, $mimeType, basename($path)),
            ],
        ]);
        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($raw === false || $status < 200 || $status >= 300) {
            throw new RuntimeException($this->errorMessage((string)$raw, $status, $error));
        }
        $data = json_decode((string)$raw, true);
        $id = is_array($data) ? trim((string)($data['id'] ?? '')) : '';
        if ($id === '') {
            throw new RuntimeException('WhatsApp não retornou o identificador da mídia.');
        }
        return $id;
    }

    /** @return array<string,mixed> */
    private function request(string $method, string $path, ?array $payload = null): array
    {
        $ch = curl_init($this->endpoint($path));
        if ($ch === false) {
            throw new RuntimeException('Não foi possível iniciar a conexão com o WhatsApp.');
        }
        $headers = ['Authorization: Bearer '.$this->accessToken, 'Accept: application/json'];
        $options = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_HTTPHEADER => $headers];
        if ($method === 'POST') {
            $body = json_encode($payload ?? [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $headers[] = 'Content-Type: application/json';
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $body;
            $options[CURLOPT_HTTPHEADER] = $headers;
        }
        curl_setopt_array($ch, $options);
        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($raw === false || $status < 200 || $status >= 300) {
            throw new RuntimeException($this->errorMessage((string)$raw, $status, $error));
        }
        $data = json_decode((string)$raw, true);
        if (!is_array($data)) {
            throw new RuntimeException('Resposta inválida do WhatsApp Cloud API.');
        }
        return $data;
    }

    /** @param array<string,mixed> $response @return array{message_id:string,status:string} */
    private function deliveryResult(array $response): array
    {
        $id = trim((string)($response['messages'][0]['id'] ?? ''));
        if ($id === '') {
            throw new RuntimeException('WhatsApp não confirmou o envio da mensagem.');
        }
        return ['message_id' => $id, 'status' => 'sent'];
    }

    private function normalizeRecipient(string $recipient): string
    {
        $recipient = preg_replace('/\D+/', '', $recipient) ?? '';
        if ($recipient === '') {
            throw new RuntimeException('Número de WhatsApp inválido.');
        }
        return $recipient;
    }

    private function mediaType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) return 'image';
        if (str_starts_with($mimeType, 'audio/')) return 'audio';
        if (str_starts_with($mimeType, 'video/')) return 'video';
        return 'document';
    }

    private function endpoint(string $path): string
    {
        return 'https://graph.facebook.com/'.rawurlencode($this->graphVersion).'/'.ltrim($path, '/');
    }

    private function errorMessage(string $raw, int $status, string $curlError): string
    {
        $data = json_decode($raw, true);
        $provider = is_array($data) ? trim((string)($data['error']['message'] ?? '')) : '';
        if ($provider !== '') return 'WhatsApp Cloud API: '.$provider;
        if ($curlError !== '') return 'Falha de conexão com WhatsApp: '.$curlError;
        return 'WhatsApp Cloud API retornou HTTP '.$status.'.';
    }
}
