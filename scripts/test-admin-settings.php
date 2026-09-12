<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Moves\Boot\Connection as DatabaseConnection;
use Moves\Boot\Environment;
use Moves\Core\Config;
use Moves\Core\Settings;
use MovesCode\Model\Connection as ModelConnection;

/**
 * Moves | Admin Settings HTTP Test
 *
 * Valida autorização, CSRF e persistência da configuração administrativa.
 *
 * @author Djalma Martins
 */

Environment::load(dirname(__DIR__));
$base = rtrim((string) Config::get('APP_URL'), '/');
$host = parse_url($base, PHP_URL_HOST);

if (!is_string($host) || gethostbyname($host) !== '127.0.0.1') {
    throw new RuntimeException('Este teste só pode usar um servidor local.');
}

$pdo = DatabaseConnection::getInstance();
ModelConnection::configure($pdo);
$email = 'settings-test-' . bin2hex(random_bytes(8)) . '@example.invalid';
$password = bin2hex(random_bytes(16));
$original = Settings::get('app_name', 'Moves');
$client = curl_init();
$id = null;

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
    return [
        'status' => curl_getinfo($client, CURLINFO_RESPONSE_CODE),
        'body' => substr($raw, $size),
    ];
};

try {
    $insert = $pdo->prepare('INSERT INTO users (name, email, password, status, role) VALUES (?, ?, ?, ?, ?)');
    $insert->execute(['Admin settings test', $email, password_hash($password, PASSWORD_DEFAULT), 'active', 'admin']);
    $id = (int) $pdo->lastInsertId();

    $login = $request('/login');
    preg_match('/name="_token"\s+value="([^"]+)"/', $login['body'], $match);
    $token = $match[1] ?? '';
    $request('/login', ['email' => $email, 'password' => $password, '_token' => $token]);

    $page = $request('/admin/settings');
    if ($page['status'] !== 200 || !str_contains($page['body'], 'name="app_name"')) {
        throw new RuntimeException('FAIL: formulário administrativo indisponível.');
    }

    $invalid = $request('/admin/settings', ['app_name' => 'Moves HTTP', '_token' => 'invalid']);
    if ($invalid['status'] !== 302 || Settings::get('app_name') !== $original) {
        throw new RuntimeException('FAIL: CSRF inválido alterou Settings.');
    }

    $request('/admin/settings');
    $updated = $request('/admin/settings', ['app_name' => 'Moves HTTP', '_token' => $token]);
    if ($updated['status'] !== 302 || Settings::get('app_name') !== 'Moves HTTP') {
        throw new RuntimeException('FAIL: Settings não foi persistido.');
    }

    echo 'OK: Settings administrativo via HTTP.' . PHP_EOL;
} finally {
    Settings::set('app_name', $original);
    if ($id !== null) {
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    }
    curl_close($client);
}
