<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Moves\Boot\Connection;
use Moves\Boot\Environment;
use Moves\Core\Config;

/**
 * Moves | Authentication HTTP Test
 *
 * Verifica autenticação, middleware e CSRF no servidor local com usuário descartável.
 *
 * @author Djalma Martins
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

Environment::load(dirname(__DIR__));
$base = rtrim((string) Config::get('APP_URL'), '/');
$host = parse_url($base, PHP_URL_HOST);
if (!is_string($host) || !in_array(gethostbyname($host), ['127.0.0.1', '::1'], true)) {
    throw new RuntimeException('Este teste só pode usar um servidor local.');
}

$client = curl_init();
if ($client === false) {
    throw new RuntimeException('Não foi possível iniciar o cliente HTTP.');
}
curl_setopt_array($client, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_COOKIEFILE => '',
    CURLOPT_PROXY => '',
    CURLOPT_TIMEOUT => 15,
]);

$checks = 0;
$check = static function (bool $condition, string $label) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $label);
    }
    ++$checks;
    echo 'PASS: ' . $label . PHP_EOL;
};

$request = static function (string $path, ?array $data = null) use ($client, $base): array {
    curl_setopt($client, CURLOPT_URL, $base . $path);
    if ($data === null) {
        curl_setopt($client, CURLOPT_HTTPGET, true);
    } else {
        curl_setopt($client, CURLOPT_POST, true);
        curl_setopt($client, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    $raw = curl_exec($client);
    if (!is_string($raw)) {
        throw new RuntimeException('Falha na requisição HTTP de teste.');
    }
    $size = curl_getinfo($client, CURLINFO_HEADER_SIZE);
    $headers = substr($raw, 0, $size);
    preg_match('/^Location:\s*(.+)$/mi', $headers, $location);
    preg_match('/^Server:\s*(.+)$/mi', $headers, $runtime);
    return [
        'status' => curl_getinfo($client, CURLINFO_RESPONSE_CODE),
        'location' => trim($location[1] ?? ''),
        'runtime' => trim($runtime[1] ?? ''),
        'headers' => $headers,
        'body' => substr($raw, $size),
    ];
};

$pdo = Connection::getInstance();
$email = 'middleware-test-' . bin2hex(random_bytes(12)) . '@example.invalid';
$password = bin2hex(random_bytes(24));
$name = 'Middleware integration test';
$id = null;
try {
    $insert = $pdo->prepare('INSERT INTO users (name, email, password, status) VALUES (?, ?, ?, ?)');
    $insert->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), 'active']);
    $id = (int) $pdo->lastInsertId();

    $response = $request('/app');
    $check($response['status'] === 302 && $response['location'] === $base . '/login', 'visitante em /app redirecionado para /login');
    $check($request('/app/status')['status'] === 302, 'visitante não acessa estado em tempo real');
    $sessionHeaders = $response['headers'];
    $response = $request('/studio');
    $check($response['status'] === 302 && $response['location'] === $base . '/login', 'visitante em /studio redirecionado para /login');
    foreach (['/studio/versions', '/studio/logs'] as $protectedPath) {
        $response = $request($protectedPath);
        $check($response['status'] === 302 && $response['location'] === $base . '/login', 'visitante não acessa ' . $protectedPath);
    }
    $check(str_contains($response['runtime'], 'PHP/8.2.'), 'servidor HTTP executa PHP 8.2');
    $response = $request('/login');
    $check($response['status'] === 200 && str_contains($response['body'], 'name="email"'), 'GET /login permanece público');
    $check(stripos($response['headers'], 'X-Powered-By:') === false, 'versão do PHP não é exposta');
    $check(stripos($sessionHeaders, 'HttpOnly') !== false, 'cookie de sessão é HttpOnly');
    $check(stripos($sessionHeaders, 'SameSite=Lax') !== false, 'cookie de sessão usa SameSite Lax');
    $check(stripos($sessionHeaders, '; secure') === false, 'cookie HTTP local não exige Secure');
    foreach (['/.env', '/.git/config', '/storage/logs/moves.log'] as $privatePath) {
        $privateResponse = $request($privatePath);
        $check(
            $privateResponse['status'] === 404,
            'arquivo interno não é servido: ' . $privatePath
        );
    }
    foreach (
        [
            'Content-Security-Policy:',
            'X-Content-Type-Options: nosniff',
            'X-Frame-Options: DENY',
            'Referrer-Policy: strict-origin-when-cross-origin',
            'Permissions-Policy:',
        ] as $expectedHeader
    ) {
        $check(
            stripos($response['headers'], $expectedHeader) !== false,
            'header de segurança presente: ' . $expectedHeader
        );
    }
    preg_match('/name="_token"\s+value="([^"]+)"/', $response['body'], $match);
    $token = $match[1] ?? '';
    $guestToken = $token;
    $check($token !== '', 'formulário fornece token CSRF');

    foreach ([null, 'invalid-token'] as $invalidToken) {
        $data = ['email' => $email, 'password' => $password];
        if ($invalidToken !== null) $data['_token'] = $invalidToken;
        $response = $request('/login', $data);
        $check($response['status'] === 302 && $response['location'] === $base . '/login', 'POST /login rejeita CSRF ausente ou inválido');
        $check($request('/app')['status'] === 302, 'CSRF inválido não autentica');
    }

    $response = $request('/login', ['email' => $email, 'password' => 'incorrect-password', '_token' => $token]);
    $check($response['status'] === 302 && $response['location'] === $base . '/login', 'login inválido rejeitado');
    $check($request('/app')['status'] === 302, 'login inválido não autentica');

    $before = curl_getinfo($client, CURLINFO_COOKIELIST);
    $response = $request('/login', ['email' => $email, 'password' => $password, '_token' => $token]);
    $check($response['status'] === 302 && $response['location'] === $base . '/app', 'login válido redireciona para /app');
    $check($before !== curl_getinfo($client, CURLINFO_COOKIELIST), 'login regenera identificador da sessão');
    $response = $request('/app');
    $check($response['status'] === 200 && str_contains($response['body'], 'Área do Cliente'), 'usuário autenticado acessa /app');
    $check(stripos($response['headers'], 'Cache-Control: private, no-store') !== false, 'área autenticada não permite cache público');
    $profile = $request('/app/profile');
    $check($profile['status'] === 200 && str_contains($profile['body'], $email), 'perfil pertence ao usuário autenticado');
    $status = $request('/app/status');
    $statusPayload = json_decode($status['body'], true);
    $check($status['status'] === 200 && is_array($statusPayload), 'estado em tempo real exige autenticação e retorna JSON');
    $check(stripos($status['headers'], 'Content-Type: application/json') !== false, 'estado em tempo real declara JSON');
    $check(($statusPayload['user']['name'] ?? null) === $name, 'estado em tempo real pertence ao usuário autenticado');
    preg_match('/name="_token"\s+value="([^"]+)"/', $response['body'], $match);
    $token = $match[1] ?? '';
    $check($token !== '', 'sessão autenticada fornece novo token CSRF');
    $check($token !== $guestToken, 'token CSRF é rotacionado após login');
    $response = $request('/login');
    $check($response['status'] === 302 && $response['location'] === $base . '/app', 'GET /login autenticado redireciona para /app');

    $response = $request('/studio/users');
    $check($response['status'] === 302 && $response['location'] === $base . '/app', 'usuário sem permissão é redirecionado para /app');
    $response = $request('/studio');
    $check($response['status'] === 302 && $response['location'] === $base . '/app', 'usuário comum não acessa /studio');
    $response = $request('/app');
    $check(str_contains($response['body'], 'Você não tem permissão'), 'redirect de permissão apresenta Flash');

    $pdo->prepare("UPDATE users SET status='inactive' WHERE id=?")->execute([$id]);
    $response = $request('/app');
    $check($response['status'] === 302 && $response['location'] === $base . '/login', 'sessão de conta desativada é invalidada imediatamente');
    $pdo->prepare("UPDATE users SET status='active' WHERE id=?")->execute([$id]);
    $login = $request('/login');
    preg_match('/name="_token"\s+value="([^"]+)"/', $login['body'], $match);
    $request('/login', ['email' => $email, 'password' => $password, '_token' => $match[1] ?? '']);
    $response = $request('/app');
    preg_match('/name="_token"\s+value="([^"]+)"/', $response['body'], $match);
    $token = $match[1] ?? '';
    $check($response['status'] === 200 && $token !== '', 'conta reativada inicia nova sessão protegida');

    $response = $request('/logout', ['_token' => 'invalid-token']);
    $check($response['status'] === 302 && $response['location'] === $base . '/app', 'logout rejeita CSRF inválido');
    $check($request('/app')['status'] === 200, 'CSRF inválido não encerra sessão');
    $beforeLogout = curl_getinfo($client, CURLINFO_COOKIELIST);
    $response = $request('/logout', ['_token' => $token]);
    $check($response['status'] === 302 && $response['location'] === $base . '/login', 'logout válido funciona');
    $check($beforeLogout !== curl_getinfo($client, CURLINFO_COOKIELIST), 'logout invalida identificador da sessão anterior');
    $check($request('/app')['status'] === 302, 'logout remove autenticação');
    $check($request('/login')['status'] === 200, 'login continua disponível após logout');
    echo 'OK: ' . $checks . ' verificações HTTP.' . PHP_EOL;
} finally {
    if ($id !== null) {
        $delete = $pdo->prepare('DELETE FROM users WHERE id = ? AND email = ?');
        $delete->execute([$id, $email]);
        if ($delete->rowCount() !== 1) {
            throw new RuntimeException('Verifique a remoção do usuário descartável de teste.');
        }
        echo 'Usuário descartável removido.' . PHP_EOL;
    }
}
