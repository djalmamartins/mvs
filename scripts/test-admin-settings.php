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
$managedUserId = null;
$versionId = null;
$logFingerprint = null;
$proposalId = null;
$contentIds = [];
$taxonomyId = null;
$mediaIds = [];
$mediaPaths = [];

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
        $hasFile = array_filter($data, static fn (mixed $value): bool => $value instanceof CURLFile) !== [];
        curl_setopt($client, CURLOPT_POSTFIELDS, $hasFile ? $data : http_build_query($data));
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
    preg_match('/name="_token"\s+value="([^"]+)"/', $page['body'], $match);
    $token = $match[1] ?? '';

    if ($token === '') {
        throw new RuntimeException('FAIL: formulário administrativo sem CSRF.');
    }

    if ($request('/admin')['status'] !== 200) {
        throw new RuntimeException('FAIL: admin não acessa /admin.');
    }

    $versions = $request('/admin/versions');
    if ($versions['status'] !== 200 || !str_contains($versions['body'], 'Migrations disponíveis')) {
        throw new RuntimeException('FAIL: inventário de versões indisponível.');
    }
    preg_match('/name="_token"\s+value="([^"]+)"/', $versions['body'], $match);
    $versionToken = $match[1] ?? '';
    $testVersion = '99.0.' . random_int(1000, 9999);
    $recorded = $request('/admin/versions', ['_token' => $versionToken, 'version' => $testVersion, 'name' => 'Teste automatizado', 'notes' => 'Registro temporário criado pelo teste HTTP.']);
    $versionId = (int) $pdo->query("SELECT id FROM studio_versions WHERE version=" . $pdo->quote($testVersion) . " LIMIT 1")->fetchColumn();
    if ($recorded['status'] !== 302 || $versionId < 1) {
        throw new RuntimeException('FAIL: histórico seguro de versões não foi persistido.');
    }

    $logs = $request('/admin/logs');
    if ($logs['status'] !== 200 || !str_contains($logs['body'], 'Contexto sanitizado')) {
        throw new RuntimeException('FAIL: leitura segura do log indisponível.');
    }
    preg_match('/name="fingerprint"\s+value="([a-f0-9]{64})"/', $logs['body'], $match);
    $logFingerprint = $match[1] ?? null;
    if ($logFingerprint !== null) {
        preg_match('/name="_token"\s+value="([^"]+)"/', $logs['body'], $match);
        $triaged = $request('/admin/logs', ['_token' => $match[1] ?? '', 'fingerprint' => $logFingerprint, 'action' => 'resolved']);
        $state = $pdo->prepare('SELECT status FROM studio_log_states WHERE fingerprint=?');
        $state->execute([$logFingerprint]);
        if ($triaged['status'] !== 302 || $state->fetchColumn() !== 'resolved') {
            throw new RuntimeException('FAIL: triagem segura do log não foi persistida.');
        }
    }

    $userForm = $request('/admin/users/create');
    preg_match('/name="_token"\s+value="([^"]+)"/', $userForm['body'], $match);
    $managedEmail = 'managed-' . bin2hex(random_bytes(6)) . '@example.invalid';
    $created = $request('/admin/users/save', ['_token' => $match[1] ?? '', 'name' => 'Usuário gerenciado', 'email' => $managedEmail, 'password' => 'Test1234!', 'role' => 'user', 'status' => 'active']);
    $managedUserId = (int) $pdo->query("SELECT id FROM users WHERE email=" . $pdo->quote($managedEmail) . " LIMIT 1")->fetchColumn();
    if ($created['status'] !== 302 || $managedUserId < 1) {
        throw new RuntimeException('FAIL: usuário administrativo não foi criado.');
    }
    $deactivated = $request('/admin/users/action', ['_token' => $match[1] ?? '', 'id' => $managedUserId, 'action' => 'deactivate']);
    $managedStatus = $pdo->query('SELECT status FROM users WHERE id=' . $managedUserId)->fetchColumn();
    if ($deactivated['status'] !== 302 || $managedStatus !== 'inactive') {
        throw new RuntimeException('FAIL: estado do usuário não foi alterado.');
    }

    foreach (['pages','articles','media','highlights','testimonials','faq','proposals','notifications','reports'] as $module) {
        $modulePage = $request('/admin/' . $module);
        if ($modulePage['status'] !== 200) {
            throw new RuntimeException('FAIL: módulo Studio indisponível: ' . $module);
        }
    }

    $articlesPage = $request('/admin/articles');
    preg_match('/name="_token"\s+value="([^"]+)"/', $articlesPage['body'], $match);
    $studioToken = $match[1] ?? '';
    $fixture = tempnam(sys_get_temp_dir(), 'moves-media-');
    $canvas = imagecreatetruecolor(80, 60); imagefill($canvas, 0, 0, imagecolorallocate($canvas, 104, 16, 159)); imagepng($canvas, $fixture);
    $uploaded = $request('/admin/media', ['_token' => $studioToken, 'action' => 'upload', 'image' => new CURLFile($fixture, 'image/png', 'teste-midia.png')]);
    @unlink($fixture);
    $mediaRow = $pdo->query("SELECT id,path FROM studio_media WHERE name LIKE 'teste-midia%' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($uploaded['status'] !== 302 || !$mediaRow) { throw new RuntimeException('FAIL: upload seguro de mídia não foi persistido.'); }
    $mediaIds[] = (int) $mediaRow['id']; $mediaPaths[] = (string) $mediaRow['path'];
    $cropped = $request('/admin/media', ['_token' => $studioToken, 'action' => 'crop', 'id' => $mediaRow['id'], 'crop_x' => 10, 'crop_y' => 10, 'crop_width' => 40, 'crop_height' => 30]);
    $cropRow = $pdo->query('SELECT id,path FROM studio_media WHERE parent_id=' . (int) $mediaRow['id'] . ' ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    if ($cropped['status'] !== 302 || !$cropRow) { throw new RuntimeException('FAIL: recorte derivado não foi criado.'); }
    $mediaIds[] = (int) $cropRow['id']; $mediaPaths[] = (string) $cropRow['path'];
    $categoryName = 'Categoria teste ' . bin2hex(random_bytes(3));
    $request('/admin/articles', ['_token' => $studioToken, 'action' => 'category', 'category_name' => $categoryName]);
    $taxonomyId = (int) $pdo->query('SELECT id FROM studio_taxonomies WHERE name=' . $pdo->quote($categoryName))->fetchColumn();
    foreach (['articles' => 'Artigo', 'pages' => 'Página', 'highlights' => 'Destaque', 'testimonials' => 'Depoimento'] as $module => $label) {
        $slug = 'teste-' . $module . '-' . bin2hex(random_bytes(3));
        $payload = ['_token' => $studioToken, 'action' => 'save', 'title' => $label . ' automatizado', 'slug' => $slug, 'excerpt' => 'Conteúdo temporário para validar o módulo.', 'content' => 'Texto de validação funcional do conteúdo no Moves Studio.', 'media_id' => $mediaRow['id'], 'status' => $module === 'articles' ? 'published' : 'draft', 'position' => 7, 'seo_title' => $label . ' SEO', 'seo_description' => 'Descrição segura de teste.'];
        if ($module === 'articles') { $payload['category_id'] = $taxonomyId; $payload['video'] = ''; }
        if ($module === 'pages') { $payload['template'] = 'landing'; }
        if ($module === 'highlights') { $payload += ['cta_label' => 'Saiba mais', 'cta_url' => '/contato', 'alignment' => 'center']; }
        if ($module === 'testimonials') { $payload += ['company' => 'Moves', 'job_title' => 'Cliente']; }
        $saved = $request('/admin/' . $module, $payload);
        $contentId = (int) $pdo->query('SELECT id FROM studio_content WHERE slug=' . $pdo->quote($slug))->fetchColumn();
        if ($saved['status'] !== 302 || $contentId < 1) { throw new RuntimeException('FAIL: conteúdo não persistido em ' . $module); }
        $contentIds[] = $contentId;
        if ($module === 'articles') {
            $publicArticle = $request('/conteudo/' . $slug);
            $publicMedia = $request('/media/' . (int) $mediaRow['id']);
            if ($publicArticle['status'] !== 200 || !str_contains($publicArticle['body'], $label . ' automatizado') || $publicMedia['status'] !== 200) { throw new RuntimeException('FAIL: artigo ou mídia publicada indisponível.'); }
        }
    }
    $protectedDelete = $request('/admin/media', ['_token' => $studioToken, 'action' => 'delete', 'id' => $mediaRow['id']]);
    if ($protectedDelete['status'] !== 302 || !(bool) $pdo->query('SELECT 1 FROM studio_media WHERE id=' . (int) $mediaRow['id'])->fetchColumn()) { throw new RuntimeException('FAIL: mídia associada pôde ser excluída.'); }

    $contact = $request('/contato');
    preg_match('/name="_token"\s+value="([^"]+)"/', $contact['body'], $match);
    $contactToken = $match[1] ?? '';
    $beforeProposal = (int) $pdo->query('SELECT COUNT(*) FROM proposals')->fetchColumn();
    $submitted = $request('/contato', ['_token'=>$contactToken,'nome'=>'Teste Studio','email'=>'studio-test@example.invalid','empresa'=>'Moves','servico'=>'sistemas-web','mensagem'=>'Solicitação segura criada pelo teste automatizado.']);
    if ($submitted['status'] !== 302 || (int) $pdo->query('SELECT COUNT(*) FROM proposals')->fetchColumn() !== $beforeProposal + 1) {
        throw new RuntimeException('FAIL: proposta pública não foi persistida.');
    }
    $proposalId = (int) $pdo->query("SELECT id FROM proposals WHERE email='studio-test@example.invalid' ORDER BY id DESC LIMIT 1")->fetchColumn();

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
    if ($logFingerprint !== null) {
        $pdo->prepare('DELETE FROM studio_log_states WHERE fingerprint=?')->execute([$logFingerprint]);
    }
    if ($versionId !== null) {
        $pdo->prepare('DELETE FROM studio_versions WHERE id=?')->execute([$versionId]);
    }
    $pdo->exec("UPDATE studio_versions SET status='current' WHERE product='studio' ORDER BY id DESC LIMIT 1");
    if ($managedUserId !== null) {
        $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$managedUserId]);
    }
    foreach ($contentIds as $contentId) { $pdo->prepare('DELETE FROM studio_content WHERE id=?')->execute([$contentId]); }
    if ($taxonomyId !== null) { $pdo->prepare('DELETE FROM studio_taxonomies WHERE id=?')->execute([$taxonomyId]); }
    foreach (array_reverse($mediaIds) as $mediaId) { $pdo->prepare('DELETE FROM studio_media WHERE id=?')->execute([$mediaId]); }
    foreach ($mediaPaths as $mediaPath) { if (is_file($mediaPath)) { @unlink($mediaPath); } }
    if ($id !== null) {
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    }
    if ($proposalId !== null) {
        $pdo->prepare('DELETE FROM proposals WHERE id = ?')->execute([$proposalId]);
        $pdo->prepare("DELETE FROM notifications WHERE message LIKE 'Proposta de Teste Studio%'")->execute();
    }
}
