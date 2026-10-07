<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Moves\Boot\Connection;
use Moves\Boot\Environment;
use Moves\Core\Config;
use Moves\Services\Platform\CompanyService;

Environment::load(dirname(__DIR__));
$base = rtrim((string) Config::get('APP_URL', ''), '/');
$database = (string) Config::get('DB_DATABASE', '');
$host = (string) parse_url($base, PHP_URL_HOST);
if (Config::environment() !== 'testing'
    || !preg_match('/^moves_codex_studio_users_sec286_[a-zA-Z0-9_]+$/', $database)
    || !in_array(gethostbyname($host), ['127.0.0.1', '::1'], true)) {
    throw new RuntimeException('Studio users E2E requer APP_ENV=testing, APP_URL local e DB_DATABASE moves_codex_studio_users_sec286_*.');
}

$pdo = Connection::getInstance();
$suffix = bin2hex(random_bytes(5));
$password = 'Moves-Studio-Sec286-Test-2026!';
$newUser = static function (string $name, string $label, string $globalRole = 'user') use ($pdo, $suffix, $password): array {
    $email = 'studio-' . $label . '-' . $suffix . '@example.test';
    $pdo->prepare('INSERT INTO users(name,email,password,status,role) VALUES(?,?,?,\'active\',?)')
        ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $globalRole]);
    return ['id' => (int) $pdo->lastInsertId(), 'name' => $name, 'email' => $email];
};
$membership = static function (int $tenantId, int $userId, string $roleSlug) use ($pdo): void {
    $role = $pdo->prepare('SELECT id FROM platform_roles WHERE tenant_id=? AND slug=?');
    $role->execute([$tenantId, $roleSlug]);
    $roleId = (int) $role->fetchColumn();
    if ($roleId < 1) {
        throw new RuntimeException('Tenant role fixture missing.');
    }
    $pdo->prepare('INSERT INTO talk_tenant_users(tenant_id,user_id,role,role_id,status,is_default) VALUES(?,?,?, ?,\'active\',?)')
        ->execute([$tenantId, $userId, $roleSlug === 'owner' ? 'admin' : $roleSlug, $roleId, $roleSlug === 'owner' ? 1 : 0]);
};
$company = new CompanyService($pdo);
$ownerA = $newUser('Studio Owner A ' . $suffix, 'owner-a', 'admin');
$tenantA = $company->create(['name' => 'Studio A ' . $suffix, 'legal_name' => 'Studio A ' . $suffix], $ownerA['id'], ['studio']);
$memberA = $newUser('Studio Member A ' . $suffix, 'member-a');
$membership($tenantA, $memberA['id'], 'agent');
$ownerB = $newUser('Studio Owner B ' . $suffix, 'owner-b', 'admin');
$tenantB = $company->create(['name' => 'Studio B ' . $suffix, 'legal_name' => 'Studio B ' . $suffix], $ownerB['id'], ['studio']);

$checks = 0;
$check = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) {
        throw new RuntimeException('FAIL: ' . $message);
    }
    ++$checks;
    echo 'PASS: ' . $message . PHP_EOL;
};
$client = static function () use ($base): Closure {
    $handle = curl_init();
    if (!$handle instanceof CurlHandle) {
        throw new RuntimeException('cURL indisponível.');
    }
    $cookie = tempnam(sys_get_temp_dir(), 'moves-studio-users-');
    if (!is_string($cookie)) {
        throw new RuntimeException('Cookie jar temporário indisponível.');
    }
    curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_COOKIEFILE => $cookie, CURLOPT_COOKIEJAR => $cookie, CURLOPT_PROXY => '', CURLOPT_TIMEOUT => 15]);
    return static function (string $path, ?array $post = null) use ($handle, $base): array {
        curl_setopt($handle, CURLOPT_URL, $base . $path);
        curl_setopt($handle, CURLOPT_POST, $post !== null);
        if ($post !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($post));
        }
        $raw = curl_exec($handle);
        if (!is_string($raw)) {
            throw new RuntimeException('HTTP falhou: ' . curl_error($handle));
        }
        $size = (int) curl_getinfo($handle, CURLINFO_HEADER_SIZE);
        preg_match('/^Location:\s*(.+)$/mi', substr($raw, 0, $size), $location);
        return ['status' => (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE), 'location' => trim($location[1] ?? ''), 'body' => substr($raw, $size)];
    };
};
$csrf = static function (string $body): string {
    if (!preg_match('/name="_token"\s+value="([^"]+)"/', $body, $match)) {
        throw new RuntimeException('Token CSRF ausente.');
    }
    return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5);
};
$login = static function (array $user) use ($client, $csrf, $password, $check): Closure {
    $request = $client();
    $form = $request('/login');
    $response = $request('/login', ['email' => $user['email'], 'password' => $password, '_token' => $csrf($form['body'])]);
    $check($form['status'] === 200 && $response['status'] === 302 && str_ends_with($response['location'], '/day'), 'login de usuário sintético concluído');
    return $request;
};

$httpA = $login($ownerA);
$httpB = $login($ownerB);
$listA = $httpA('/studio/users');
$check($listA['status'] === 200 && str_contains($listA['body'], $memberA['email']) && !str_contains($listA['body'], $ownerB['email']), 'lista do Studio contém membros do tenant A e exclui usuários do tenant B');
$formA = $httpA('/studio/users/edit/' . $memberA['id']);
$formBId = $httpA('/studio/users/edit/' . $ownerB['id']);
$check($formA['status'] === 200 && $formBId['status'] === 302, 'edição direta de membro do tenant B é negada ao tenant A');
$protectedOwnerForm = $httpA('/studio/users/edit/' . $ownerA['id']);
$protectedPost = $httpA('/studio/users/save', ['_token' => $csrf($protectedOwnerForm['body']), 'id' => $ownerA['id'], 'role_id' => 1, 'status' => 'inactive']);
$protectedState = $pdo->prepare('SELECT status,role FROM talk_tenant_users WHERE tenant_id=? AND user_id=?');
$protectedState->execute([$tenantA, $ownerA['id']]);
$check($protectedPost['status'] === 302 && $protectedState->fetch(PDO::FETCH_ASSOC) === ['status' => 'active', 'role' => 'admin'], 'administrador principal permanece protegido contra alteração tenant-scoped');
$roleA = $pdo->prepare("SELECT id FROM platform_roles WHERE tenant_id=? AND slug='owner'");
$roleA->execute([$tenantA]);
$forgedSave = $httpA('/studio/users/save', [
    '_token' => $csrf($formA['body']), 'id' => $ownerB['id'], 'name' => 'FORGED NAME', 'email' => 'forged@example.test',
    'password' => 'Forged-password-2026!', 'role_id' => (int) $roleA->fetchColumn(), 'status' => 'inactive',
]);
$bAccount = $pdo->prepare('SELECT name,email,status FROM users WHERE id=?');
$bAccount->execute([$ownerB['id']]);
$bMembership = $pdo->prepare('SELECT status,role FROM talk_tenant_users WHERE tenant_id=? AND user_id=?');
$bMembership->execute([$tenantB, $ownerB['id']]);
$bState = $bAccount->fetch(PDO::FETCH_ASSOC);
$bMemberState = $bMembership->fetch(PDO::FETCH_ASSOC);
$check($forgedSave['status'] === 302 && $bState === ['name' => $ownerB['name'], 'email' => $ownerB['email'], 'status' => 'active'] && $bMemberState === ['status' => 'active', 'role' => 'admin'], 'POST adulterado com ID de B não altera conta nem vínculo de B');
$beforeAction = $bMemberState;
$forgedAction = $httpA('/studio/users/action', ['_token' => $csrf($formA['body']), 'id' => $ownerB['id'], 'action' => 'delete']);
$bMembership->execute([$tenantB, $ownerB['id']]);
$check($forgedAction['status'] === 302 && $bMembership->fetch(PDO::FETCH_ASSOC) === $beforeAction, 'ação administrativa por ID de B não altera vínculo de B');
$invalidCsrf = $httpA('/studio/users/save', ['_token' => 'invalid', 'id' => $memberA['id'], 'role_id' => (int) $roleA->fetchColumn(), 'status' => 'inactive']);
$aMembership = $pdo->prepare('SELECT status,role FROM talk_tenant_users WHERE tenant_id=? AND user_id=?');
$aMembership->execute([$tenantA, $memberA['id']]);
$check($invalidCsrf['status'] === 302 && $aMembership->fetch(PDO::FETCH_ASSOC) === ['status' => 'active', 'role' => 'agent'], 'CSRF inválido não altera vínculo autorizado');
$operatorRole = $pdo->prepare("SELECT id FROM platform_roles WHERE tenant_id=? AND slug='operator'");
$operatorRole->execute([$tenantA]);
$validUpdate = $httpA('/studio/users/save', [
    '_token' => $csrf($formA['body']), 'id' => $memberA['id'], 'name' => 'FORGED NAME', 'email' => 'forged@example.test',
    'password' => 'Forged-password-2026!', 'role_id' => (int) $operatorRole->fetchColumn(), 'status' => 'inactive',
]);
$aMembership->execute([$tenantA, $memberA['id']]);
$memberState = $aMembership->fetch(PDO::FETCH_ASSOC);
$memberAccount = $pdo->prepare('SELECT name,email,status FROM users WHERE id=?');
$memberAccount->execute([$memberA['id']]);
$memberGlobalState = $memberAccount->fetch(PDO::FETCH_ASSOC);
$audit = $pdo->prepare("SELECT tenant_id,actor_user_id,event_type,subject_id FROM platform_audit_events WHERE tenant_id=? AND actor_user_id=? AND event_type='member.role_status_changed' AND subject_id=? ORDER BY id DESC LIMIT 1");
$audit->execute([$tenantA, $ownerA['id'], $memberA['id']]);
$auditEvent = $audit->fetch(PDO::FETCH_ASSOC);
$check($validUpdate['status'] === 302 && $memberState === ['status' => 'inactive', 'role' => 'operator'], 'tenant A atualiza apenas o perfil e o estado do próprio vínculo');
$check($memberGlobalState === ['name' => $memberA['name'], 'email' => $memberA['email'], 'status' => 'active'], 'campos globais de conta ignoram mass assignment do formulário');
$check(is_array($auditEvent) && (int) $auditEvent['tenant_id'] === $tenantA && (int) $auditEvent['actor_user_id'] === $ownerA['id'] && (int) $auditEvent['subject_id'] === $memberA['id'], 'alteração gera auditoria com tenant, ator e subject corretos');

echo "OK: $checks verificações HTTP Studio users/tenant.\n";
