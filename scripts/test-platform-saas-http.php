<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Moves\Boot\Connection;
use Moves\Boot\Environment;
use Moves\Core\Config;
use Moves\Services\Platform\CompanyService;
use MovesCode\Model\Connection as ModelConnection;

Environment::load(dirname(__DIR__));
$base = rtrim((string) Config::get('APP_URL'), '/');
$host = parse_url($base, PHP_URL_HOST);
if (!is_string($host) || gethostbyname($host) !== '127.0.0.1') {
    throw new RuntimeException('Este teste só pode usar um servidor local.');
}

$pdo = Connection::getInstance();
ModelConnection::configure($pdo);
$suffix = bin2hex(random_bytes(8));
$email = 'platform-http-' . $suffix . '@example.invalid';
$password = bin2hex(random_bytes(16)) . '!Aa1';
$userId = 0;
$tenantId = 0;
$client = curl_init();
if ($client === false) { throw new RuntimeException('cURL indisponível.'); }
curl_setopt_array($client, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_COOKIEFILE=>'',CURLOPT_PROXY=>'',CURLOPT_TIMEOUT=>15]);
$request = static function (string $path, ?array $data = null) use ($client, $base): array {
    curl_setopt($client, CURLOPT_URL, $base . $path);
    if ($data === null) { curl_setopt($client, CURLOPT_HTTPGET, true); }
    else { curl_setopt($client, CURLOPT_POST, true); curl_setopt($client, CURLOPT_POSTFIELDS, http_build_query($data)); }
    $raw = curl_exec($client);
    if (!is_string($raw)) { throw new RuntimeException('Falha HTTP local.'); }
    $headerSize = curl_getinfo($client, CURLINFO_HEADER_SIZE);
    return ['status'=>(int)curl_getinfo($client,CURLINFO_RESPONSE_CODE),'body'=>substr($raw,$headerSize)];
};

try {
    $insert = $pdo->prepare("INSERT INTO users(name,email,password,status,role) VALUES('Platform HTTP test',?,?, 'active','user')");
    $insert->execute([$email, password_hash($password, PASSWORD_DEFAULT)]);
    $userId = (int) $pdo->lastInsertId();
    $tenantId = (new CompanyService($pdo))->create(['name'=>'HTTP Test '.$suffix,'legal_name'=>'HTTP Test '.$suffix], $userId, ['talk']);

    $login = $request('/login');
    preg_match('/name="_token"\s+value="([^"]+)"/', $login['body'], $match);
    $authenticated = $request('/login', ['email'=>$email,'password'=>$password,'_token'=>$match[1]??'']);
    if ($login['status'] !== 200 || $authenticated['status'] !== 302) { throw new RuntimeException('FAIL: autenticação da fundação SaaS.'); }
    foreach (['/talk'=>200, '/settings'=>200, '/erp'=>403] as $path=>$expected) {
        $response = $request($path);
        if ($response['status'] !== $expected) { throw new RuntimeException("FAIL: {$path} retornou {$response['status']}; esperado {$expected}."); }
    }
    echo "OK: login, tenant ativo, Talk habilitado, configurações e bloqueio de ERP validados por HTTP.\n";
} finally {
    if ($tenantId > 0) {
        $pdo->prepare('DELETE FROM platform_audit_events WHERE tenant_id=? OR actor_user_id=?')->execute([$tenantId,$userId]);
        $pdo->prepare('DELETE FROM talk_tenant_users WHERE tenant_id=?')->execute([$tenantId]);
        $pdo->prepare('DELETE FROM erp_administrators WHERE tenant_id=?')->execute([$tenantId]);
        $pdo->prepare('DELETE FROM talk_tenants WHERE id=?')->execute([$tenantId]);
    }
    if ($userId > 0) { $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$userId]); }
}
