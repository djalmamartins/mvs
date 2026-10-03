<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Moves\Boot\Connection;
use Moves\Boot\Environment;
use Moves\Core\Config;
use Moves\Modules\Erp\Security\MfaEnrollmentRepository;
use Moves\Modules\Erp\Security\MfaRuntimeConfig;
use Moves\Modules\Erp\Security\TotpVerifier;

Environment::load(dirname(__DIR__));
$base = rtrim((string) Config::get('APP_URL'), '/');
$host = (string) parse_url($base, PHP_URL_HOST);
$database = (string) Config::get('DB_DATABASE', '');
if (Config::environment() !== 'testing' || !preg_match('/^mvs_mfa_day_test_[0-9a-f]{8}$/', $database)
    || !in_array(gethostbyname($host), ['127.0.0.1', '::1'], true)) {
    throw new RuntimeException('E2E exige servidor local e banco descartável mvs_mfa_day_test_*.');
}

$pdo = Connection::getInstance();
$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    ++$checks;
    echo 'PASS: ' . $label . PHP_EOL;
};
$client = static function (): CurlHandle {
    $curl = curl_init();
    if (!$curl instanceof CurlHandle) throw new RuntimeException('cURL indisponível.');
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_COOKIEFILE => '', CURLOPT_PROXY => '', CURLOPT_TIMEOUT => 15]);
    return $curl;
};
$request = static function (CurlHandle $curl, string $path, ?array $data = null) use ($base): array {
    curl_setopt($curl, CURLOPT_URL, $base . $path);
    if ($data === null) curl_setopt($curl, CURLOPT_HTTPGET, true);
    else { curl_setopt($curl, CURLOPT_POST, true); curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data)); }
    $raw = curl_exec($curl);
    if (!is_string($raw)) throw new RuntimeException('Falha HTTP: ' . curl_error($curl));
    $size = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $headers = substr($raw, 0, $size);
    preg_match('/^Location:\s*(.+)$/mi', $headers, $match);
    return ['status' => (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE),
        'location' => trim($match[1] ?? ''), 'body' => substr($raw, $size), 'headers' => $headers];
};
$token = static function (string $html): string {
    preg_match('/name="_token"\s+value="([^"]+)"/', $html, $match);
    if (!isset($match[1])) throw new RuntimeException('CSRF ausente no formulário.');
    return $match[1];
};
$sessionCookie = static function (CurlHandle $curl): string {
    foreach (curl_getinfo($curl, CURLINFO_COOKIELIST) as $line) {
        $parts = explode("\t", $line);
        if (($parts[5] ?? '') === 'PHPSESSID') return 'PHPSESSID=' . ($parts[6] ?? '');
    }
    return '';
};
$totp = static function (int $timestamp): string {
    $counter = intdiv($timestamp, 30);
    $hash = hash_hmac('sha1', pack('N2', intdiv($counter, 0x100000000), $counter % 0x100000000), '12345678901234567890', true);
    $offset = ord($hash[19]) & 15;
    $value = ((ord($hash[$offset]) & 127) << 24) | ((ord($hash[$offset + 1]) & 255) << 16)
        | ((ord($hash[$offset + 2]) & 255) << 8) | (ord($hash[$offset + 3]) & 255);
    return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
};

$password = bin2hex(random_bytes(12));
$suffix = bin2hex(random_bytes(5));
$users = [];
foreach (['plain', 'mfa'] as $kind) {
    $email = "day-http-$kind-$suffix@example.test";
    $pdo->prepare("INSERT INTO users(name,email,password,status,role) VALUES(?,?,?,'active','user')")
        ->execute(["Day $kind", $email, password_hash($password, PASSWORD_DEFAULT)]);
    $users[$kind] = ['id' => (int) $pdo->lastInsertId(), 'email' => $email];
}
$tenants = [];
foreach (['a', 'b'] as $kind) {
    $pdo->prepare("INSERT INTO talk_tenants(name,slug,status) VALUES(?,?,'active')")
        ->execute(["Day tenant $kind", "day-http-$kind-$suffix"]);
    $tenants[$kind] = (int) $pdo->lastInsertId();
    // Meu Dia é da plataforma e não pode depender de Talk habilitado.
    $pdo->prepare("INSERT INTO platform_roles(tenant_id,slug,name,is_system) VALUES(?,'agent','Atendente',1)")->execute([$tenants[$kind]]);
    $tenants[$kind . '_role'] = (int) $pdo->lastInsertId();
}
foreach ($users as $user) {
    $pdo->prepare("INSERT INTO talk_tenant_users(tenant_id,user_id,role,role_id,status,is_default) VALUES(?,?,'agent',?,'active',1)")
        ->execute([$tenants['a'], $user['id'], $tenants['a_role']]);
}
$tasks = [];
foreach (['own' => [$tenants['a'], $users['plain']['id']],
    'other_tenant' => [$tenants['b'], $users['plain']['id']],
    'other_user' => [$tenants['a'], $users['mfa']['id']]] as $kind => [$tenantId, $userId]) {
    $pdo->prepare("INSERT INTO day_tasks(tenant_id,assigned_user_id,title,status,priority) VALUES(?, ?, ?, 'pending', 'normal')")
        ->execute([$tenantId, $userId, "E2E task $kind $suffix"]);
    $tasks[$kind] = (int) $pdo->lastInsertId();
}
(new MfaEnrollmentRepository($pdo, MfaRuntimeConfig::fromEnvironment()->cipher()))
    ->enrollTotp($users['mfa']['id'], 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ');

$plain = $client();
$check($request($plain, '/day')['location'] === $base . '/login', 'visitante retorna ao login');
$login = $request($plain, '/login');
$preLoginCookie = $sessionCookie($plain);
$check($login['status'] === 200, 'login público responde 200');
$check($request($plain, '/login', ['email' => $users['plain']['email'], 'password' => $password,
    '_token' => 'bad'])['location'] === $base . '/login', 'login rejeita CSRF inválido');
$login = $request($plain, '/login');
$result = $request($plain, '/login', ['email' => $users['plain']['email'], 'password' => $password,
    '_token' => $token($login['body'])]);
$check($result['location'] === $base . '/day', 'sem MFA: login vai a /day');
$check($preLoginCookie !== $sessionCookie($plain), 'login regenera sessão');
$day = $request($plain, '/day');
$check($day['status'] === 200 && str_contains($day['body'], 'E2E task own ' . $suffix)
    && !str_contains($day['body'], 'E2E task other_tenant ' . $suffix)
    && !str_contains($day['body'], 'E2E task other_user ' . $suffix), 'Meu Dia mostra só tarefa do usuário e tenant');
$check(str_contains($day['body'], 'Navegação do Meu Dia') && str_contains($day['body'], 'day.css')
    && str_contains($day['body'], 'action="/day/tasks/' . $tasks['own'] . '"'), 'layout, sidebar e ação de tarefa presentes');
$check($request($plain, '/themes/admin/css/day.css')['status'] === 200, 'asset Day CSS responde 200');
$check($request($plain, '/themes/admin/js/application-shell.js')['status'] === 200, 'asset Day JS responde 200');
$csrf = $token($day['body']);
$check($request($plain, '/day/tasks/' . $tasks['own'], ['status' => 'done', '_token' => 'bad'])['location'] === $base . '/day?error=csrf', 'tarefa rejeita CSRF');
$check($request($plain, '/day/tasks/' . $tasks['other_tenant'], ['status' => 'done', '_token' => $csrf])['location'] === $base . '/day', 'tarefa de outro tenant não expõe erro');
$check($request($plain, '/day/tasks/' . $tasks['other_user'], ['status' => 'done', '_token' => $csrf])['location'] === $base . '/day', 'tarefa de outro usuário não expõe erro');
$check($request($plain, '/day/tasks/' . $tasks['own'], ['status' => 'done', '_token' => $csrf])['location'] === $base . '/day', 'tarefa própria atualizada');
$states = $pdo->query('SELECT id,status FROM day_tasks WHERE id IN (' . implode(',', $tasks) . ')')->fetchAll(PDO::FETCH_KEY_PAIR);
$check($states[$tasks['own']] === 'done' && $states[$tasks['other_tenant']] === 'pending'
    && $states[$tasks['other_user']] === 'pending', 'mutação respeita tenant e responsável');
$authenticatedCookie = $sessionCookie($plain);
$check($request($plain, '/logout', ['_token' => $csrf])['location'] === $base . '/login', 'logout funciona');
$check($request($plain, '/day')['location'] === $base . '/login', 'sessão encerrada não acessa /day');
$stale = $client();
curl_setopt($stale, CURLOPT_COOKIE, $authenticatedCookie);
$check($request($stale, '/day')['location'] === $base . '/login', 'sessão autenticada anterior não pode ser reutilizada');

$mfa = $client();
$login = $request($mfa, '/login');
$result = $request($mfa, '/login', ['email' => $users['mfa']['email'], 'password' => $password,
    '_token' => $token($login['body'])]);
$check($result['location'] === $base . '/login/2fa', 'com MFA: login exige challenge');
$check($request($mfa, '/day')['location'] === $base . '/login', 'challenge pendente não autentica');
$challenge = $request($mfa, '/login/2fa');
$check($challenge['status'] === 200 && substr_count($challenge['body'], 'class="mfa-digit"') === 6
    && str_contains($challenge['body'], 'mfa-challenge.js'), 'challenge HTTP entrega seis campos e JS');
$check($request($mfa, '/themes/auth/js/mfa-challenge.js')['status'] === 200, 'asset MFA JS responde 200');
$csrf = $token($challenge['body']);
$check($request($mfa, '/login/2fa', ['code' => $totp(time()), '_token' => 'bad'])['location'] === $base . '/login/2fa', 'challenge rejeita CSRF');
$check($request($mfa, '/day')['location'] === $base . '/login', 'CSRF inválido não autentica');
$verifier = new TotpVerifier();
$wrong = '000000';
while ($verifier->verify('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', $wrong)) {
    $wrong = str_pad((string) (((int) $wrong + 1) % 1000000), 6, '0', STR_PAD_LEFT);
}
$check($request($mfa, '/login/2fa', ['code' => $wrong, '_token' => $csrf])['location'] === $base . '/login/2fa', 'código incorreto permanece no challenge');
$challenge = $request($mfa, '/login/2fa');
$check(str_contains($challenge['body'], 'Código de verificação inválido'), 'erro de código é exibido');
$expired = $totp(time() - 120);
for ($age = 150; $verifier->verify('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', $expired); $age += 30) {
    $expired = $totp(time() - $age);
}
$check($request($mfa, '/login/2fa', ['code' => $expired, '_token' => $csrf])['location'] === $base . '/login/2fa', 'TOTP expirado é rejeitado');
$valid = $totp(time());
$check($request($mfa, '/login/2fa', ['code' => $valid, '_token' => $csrf])['location'] === $base . '/day', 'TOTP válido libera /day');
$day = $request($mfa, '/day');
$check($day['status'] === 200 && str_contains($day['body'], 'E2E task other_user ' . $suffix)
    && !str_contains($day['body'], 'E2E task own ' . $suffix), 'Meu Dia pós MFA usa usuário correto');
$check($request($mfa, '/login/2fa')['location'] === $base . '/day', 'challenge não é reutilizável após autenticação');
$check($request($mfa, '/logout', ['_token' => $token($day['body'])])['location'] === $base . '/login', 'logout pós MFA funciona');
$check($request($mfa, '/day')['location'] === $base . '/login', 'logout pós MFA remove acesso');

echo "OK: $checks verificações HTTP MFA + Meu Dia.\n";
