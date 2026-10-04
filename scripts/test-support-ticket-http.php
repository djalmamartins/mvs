<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Moves\Boot\Connection;
use Moves\Boot\Environment;
use Moves\Core\Config;

Environment::load(dirname(__DIR__));
$database = (string) Config::get('DB_DATABASE', '');
$base = rtrim((string) Config::get('APP_URL', ''), '/');
$host = (string) parse_url($base, PHP_URL_HOST);
if (Config::environment() !== 'testing'
    || !preg_match('/^mvs_support_test_[0-9a-f]{8}$/', $database)
    || !in_array(gethostbyname($host), ['127.0.0.1', '::1'], true)) {
    throw new RuntimeException('E2E Support exige servidor local e banco descartável mvs_support_test_*.');
}

$pdo = Connection::getInstance();
$baseClient = static function (): CurlHandle {
    $client = curl_init();
    if (!$client instanceof CurlHandle) throw new RuntimeException('cURL indisponível.');
    curl_setopt_array($client, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_COOKIEFILE => '', CURLOPT_PROXY => '', CURLOPT_TIMEOUT => 15]);
    return $client;
};
$request = static function (CurlHandle $client, string $path, ?array $data = null) use ($base): array {
    curl_setopt($client, CURLOPT_URL, $base . $path);
    if ($data === null) curl_setopt($client, CURLOPT_HTTPGET, true);
    else { curl_setopt($client, CURLOPT_POST, true); curl_setopt($client, CURLOPT_POSTFIELDS, http_build_query($data)); }
    $raw = curl_exec($client);
    if (!is_string($raw)) throw new RuntimeException('Falha HTTP: ' . curl_error($client));
    $size = (int) curl_getinfo($client, CURLINFO_HEADER_SIZE);
    $headers = substr($raw, 0, $size);
    preg_match('/^Location:\s*(.+)$/mi', $headers, $match);
    return ['status' => (int) curl_getinfo($client, CURLINFO_RESPONSE_CODE),
        'location' => trim($match[1] ?? ''), 'body' => substr($raw, $size)];
};
$token = static function (string $html): string {
    preg_match('/name="_token"\s+value="([^"]+)"/', $html, $match);
    if (!isset($match[1])) throw new RuntimeException('Token CSRF ausente.');
    return $match[1];
};
$checks = 0;
$check = static function (bool $condition, string $label) use (&$checks): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    ++$checks;
    echo 'PASS: ' . $label . PHP_EOL;
};

$suffix = bin2hex(random_bytes(5));
$password = bin2hex(random_bytes(12));
$users = [];
$tenants = [];
foreach (['A', 'B'] as $key) {
    $email = 'support-http-' . strtolower($key) . '-' . $suffix . '@example.test';
    $pdo->prepare("INSERT INTO users(name,email,password,status) VALUES(?,?,?,'active')")
        ->execute(['Support HTTP ' . $key, $email, password_hash($password, PASSWORD_DEFAULT)]);
    $users[$key] = ['id' => (int) $pdo->lastInsertId(), 'email' => $email];
    $pdo->prepare("INSERT INTO talk_tenants(name,slug,status) VALUES(?,?,'active')")
        ->execute(['Support HTTP tenant ' . $key, 'support-http-' . strtolower($key) . '-' . $suffix]);
    $tenants[$key] = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO talk_tenant_users(tenant_id,user_id,role,status,is_default) VALUES(?,?,'agent','active',1)")
        ->execute([$tenants[$key], $users[$key]['id']]);
}

$login = static function (CurlHandle $client, array $user) use ($request, $token, $password, $base, $check): void {
    $form = $request($client, '/login');
    $check($form['status'] === 200, 'formulário de login responde 200');
    $result = $request($client, '/login', ['email' => $user['email'], 'password' => $password, '_token' => $token($form['body'])]);
    $check($result['status'] === 302 && $result['location'] === $base . '/app', 'login autentica usuário de teste');
};

$a = $baseClient();
$check($request($a, '/support/tickets/create')['location'] === $base . '/login', 'visitante não acessa criação');
$login($a, $users['A']);
$create = $request($a, '/support/tickets/create');
$check($create['status'] === 200 && str_contains($create['body'], 'Novo chamado'), 'formulário de chamado disponível');
$csrf = $token($create['body']);
$subject = 'Portão E2E ' . $suffix;
$body = ['subject' => $subject, 'description' => 'Fechadura quebrada', 'priority' => 'high'];
$check($request($a, '/support/tickets', $body + ['_token' => 'invalid'])['location'] === $base . '/support/tickets/create', 'criação rejeita CSRF');
$check((int) $pdo->query('SELECT COUNT(*) FROM support_tickets WHERE tenant_id=' . $tenants['A'])->fetchColumn() === 0, 'CSRF inválido não persiste');
$saved = $request($a, '/support/tickets', $body + ['_token' => $csrf]);
$match = [];
$check($saved['status'] === 302 && preg_match('#^' . preg_quote($base, '#') . '/support/tickets/(\d+)$#', $saved['location'], $match) === 1, 'criação redireciona para detalhe');
$id = (int) $match[1];
$statement = $pdo->prepare('SELECT tenant_id,protocol,subject FROM support_tickets WHERE id=?');
$statement->execute([$id]);
$ticket = $statement->fetch(PDO::FETCH_ASSOC);
$check(is_array($ticket) && (int) $ticket['tenant_id'] === $tenants['A']
    && $ticket['subject'] === $subject && preg_match('/^SUP-\d{8}-\d{6}$/', (string) $ticket['protocol']) === 1,
    'chamado persistido com tenant e protocolo');
$check((int) $pdo->query('SELECT COUNT(*) FROM support_ticket_events WHERE ticket_id=' . $id . " AND event_type='created'")->fetchColumn() === 1,
    'evento de auditoria persistido');
$detail = $request($a, '/support/tickets/' . $id);
$check($detail['status'] === 200 && str_contains($detail['body'], $subject)
    && str_contains($detail['body'], (string) $ticket['protocol']), 'criador visualiza o detalhe');

$b = $baseClient();
$login($b, $users['B']);
$denied = $request($b, '/support/tickets/' . $id);
$check($denied['status'] === 302 && $denied['location'] === $base . '/support/my-tickets'
    && !str_contains($denied['body'], $subject), 'tenant B não visualiza chamado A');

echo "OK: $checks verificações HTTP Support.\n";
