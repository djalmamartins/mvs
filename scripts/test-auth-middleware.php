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
    preg_match('/^X-Powered-By:\s*(.+)$/mi', $headers, $runtime);
    return [
        'status' => curl_getinfo($client, CURLINFO_RESPONSE_CODE),
        'location' => trim($location[1] ?? ''),
        'runtime' => trim($runtime[1] ?? ''),
        'body' => substr($raw, $size),
    ];
};

$pdo = Connection::getInstance();
$email = 'middleware-test-' . bin2hex(random_bytes(12)) . '@example.invalid';
$password = bin2hex(random_bytes(24));
$id = null;
try {
    $insert = $pdo->prepare('INSERT INTO users (name, email, password, status) VALUES (?, ?, ?, ?)');
    $insert->execute(['Middleware integration test', $email, password_hash($password, PASSWORD_DEFAULT), 'active']);
    $id = (int) $pdo->lastInsertId();

    $response = $request('/app');
    $check($response['status'] === 302 && $response['location'] === $base . '/login', 'visitante em /app redirecionado para /login');
    $check(str_starts_with($response['runtime'], 'PHP/8.2.'), 'servidor HTTP executa PHP 8.2');
    $response = $request('/login');
    $check($response['status'] === 200 && str_contains($response['body'], 'name="email"'), 'GET /login permanece público');
    preg_match('/name="_token"\s+value="([^"]+)"/', $response['body'], $match);
    $token = $match[1] ?? '';
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
    $check($response['status'] === 200 && str_contains($response['body'], 'Área autenticada do Moves'), 'usuário autenticado acessa /app');
    $response = $request('/login');
    $check($response['status'] === 302 && $response['location'] === $base . '/app', 'GET /login autenticado redireciona para /app');

    $response = $request('/logout', ['_token' => 'invalid-token']);
    $check($response['status'] === 302 && $response['location'] === $base . '/app', 'logout rejeita CSRF inválido');
    $check($request('/app')['status'] === 200, 'CSRF inválido não encerra sessão');
    $response = $request('/logout', ['_token' => $token]);
    $check($response['status'] === 302 && $response['location'] === $base . '/login', 'logout válido funciona');
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
    curl_close($client);
}
