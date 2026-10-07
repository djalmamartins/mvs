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
    || !preg_match('/^moves_codex_erp_visual_[a-zA-Z0-9_]+$/', $database)
    || !in_array(gethostbyname($host), ['127.0.0.1', '::1'], true)) {
    throw new RuntimeException('ERP E2E requer APP_ENV=testing, APP_URL local e DB_DATABASE moves_codex_erp_visual_*.');
}

$pdo = Connection::getInstance();
$suffix = bin2hex(random_bytes(5));
$password = 'MovesERP-VisualTest-2026!';
$email = 'erp-visual-' . $suffix . '@example.test';
$pdo->prepare("INSERT INTO users(name,email,password,status,role) VALUES(?,?,?,'active','admin')")
    ->execute(['Usuário de teste ERP', $email, password_hash($password, PASSWORD_DEFAULT)]);
$userId = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO talk_tenants(name,slug,status) VALUES(?,?,'active')")
    ->execute(['Administradora de teste', 'erp-visual-' . $suffix]);
$tenantId = (int) $pdo->lastInsertId();
$roles = new CompanyService($pdo);
$roles->ensureRoles($tenantId);
$roleQuery = $pdo->prepare("SELECT id FROM platform_roles WHERE tenant_id=? AND slug='owner'");
$roleQuery->execute([$tenantId]);
$roleId = (int) $roleQuery->fetchColumn();
$pdo->prepare("INSERT INTO talk_tenant_users(tenant_id,user_id,role,role_id,status,is_default) VALUES(?,?,'admin',?,'active',1)")
    ->execute([$tenantId, $userId, $roleId]);
$pdo->prepare("INSERT INTO platform_tenant_products(tenant_id,product,enabled) VALUES(?,'erp',1)")
    ->execute([$tenantId]);
$pdo->prepare('INSERT INTO erp_administrators(tenant_id,legal_name,trade_name,tax_id,status) VALUES(?,?,?,?,?)')
    ->execute([$tenantId, 'Administradora de teste', 'Administradora de teste', 'TESTE-' . $suffix, 'active']);
$administratorId = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO erp_scope_grants(user_id,capability,scope_type,scope_id) VALUES(?,'erp.access','administrator',?)")
    ->execute([$userId, $administratorId]);
$pdo->prepare("INSERT INTO erp_scope_grants(user_id,capability,scope_type,scope_id) VALUES(?,'erp.cadastros.read','administrator',?),(?,'erp.cadastros.write','administrator',?)")
    ->execute([$userId,$administratorId,$userId,$administratorId]);
$pdo->prepare('INSERT INTO erp_condominiums(administrator_id,legal_name,trade_name,tax_id,status,city,state) VALUES(?,?,?,?,?,?,?)')
    ->execute([$administratorId, 'Residencial QA', 'Residencial QA', 'QA-' . $suffix, 'active', 'São Paulo', 'SP']);
$condominiumId = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO talk_tenants(name,slug,status) VALUES(?,?,'active')")
    ->execute(['Administradora de tenant B', 'erp-visual-b-' . $suffix]);
$otherTenantId = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO erp_administrators(tenant_id,legal_name,trade_name,tax_id,status) VALUES(?,?,?,?,?)')
    ->execute([$otherTenantId, 'Administradora de tenant B', 'Administradora de tenant B', 'TESTE-B-' . $suffix, 'active']);
$otherAdministratorId = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO erp_condominiums(administrator_id,legal_name,trade_name,tax_id,status,city,state) VALUES(?,?,?,?,?,?,?)')
    ->execute([$otherAdministratorId, 'Residencial Tenant B', 'Residencial Tenant B', 'QA-B-' . $suffix, 'active', 'São Paulo', 'SP']);
$otherCondominiumId = (int) $pdo->lastInsertId();

$checks = 0;
$check = static function (bool $condition, string $label) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $label);
    }
    ++$checks;
    echo 'PASS: ' . $label . PHP_EOL;
};
$curl = curl_init();
if (!$curl instanceof CurlHandle) {
    throw new RuntimeException('cURL indisponível.');
}
$cookieFile = tempnam(sys_get_temp_dir(), 'moves-erp-e2e-');
if (!is_string($cookieFile)) {
    throw new RuntimeException('Não foi possível criar o cookie jar temporário.');
}
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_COOKIEFILE => $cookieFile, CURLOPT_COOKIEJAR => $cookieFile, CURLOPT_PROXY => '', CURLOPT_TIMEOUT => 15,
]);
$request = static function (string $path, ?array $data = null) use ($curl, $base): array {
    curl_setopt($curl, CURLOPT_URL, $base . $path);
    if ($data === null) {
        curl_setopt($curl, CURLOPT_HTTPGET, true);
        curl_setopt($curl, CURLOPT_POST, false);
    } else {
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    $raw = curl_exec($curl);
    if (!is_string($raw)) {
        throw new RuntimeException('HTTP falhou: ' . curl_error($curl));
    }
    $headerSize = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $headers = substr($raw, 0, $headerSize);
    preg_match('/^Location:\s*(.+)$/mi', $headers, $location);
    return [
        'status' => (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE),
        'location' => trim($location[1] ?? ''),
        'body' => substr($raw, $headerSize),
    ];
};
$csrf = static function (string $body): string {
    if (!preg_match('/name="_token"\s+value="([^"]+)"/', $body, $match)) {
        throw new RuntimeException('Token CSRF não foi renderizado no formulário de login.');
    }
    return $match[1];
};

$login = $request('/login');
$check($login['status'] === 200, 'login responde HTTP 200');
$auth = $request('/login', ['email'=>$email,'password'=>$password,'_token'=>$csrf($login['body'])]);
$check($auth['status'] === 302, 'autenticação retorna redirect HTTP 302 (' . $auth['location'] . ')');
if (!str_ends_with($auth['location'], '/day')) {
    $failurePage = $request('/login');
    $failure = match (true) {
        str_contains($failurePage['body'], 'Token de segurança inválido') => 'CSRF recusado',
        str_contains($failurePage['body'], 'E-mail ou senha inválidos') => 'credenciais recusadas',
        str_contains($failurePage['body'], 'Não foi possível validar a segurança') => 'checagem MFA falhou',
        default => 'redirect inesperado',
    };
    throw new RuntimeException('FAIL: login sem MFA (' . $failure . ', ' . $auth['location'] . ').');
}
$check(true, 'login sem MFA redireciona para Meu Dia');
$day = $request('/day');
$check($day['status'] === 200 && str_contains($day['body'], 'Navegação do Meu Dia'), 'Meu Dia autenticado responde 200 no shell Moves');

$dashboard = $request('/erp');
$check($dashboard['status'] === 200 && str_contains($dashboard['body'], 'Central financeira e operacional'), 'dashboard ERP responde 200');
$check(str_contains($dashboard['body'], 'Administradora de teste') && str_contains($dashboard['body'], 'Todos os condomínios'), 'contexto mostra administradora e seletor do tenant');
$check(!str_contains($dashboard['body'], 'Ambiente demonstrativo') && !str_contains($dashboard['body'], 'R$ 842.430,25'), 'dashboard não injeta dados demonstrativos no ambiente testing');
$check(str_contains($dashboard['body'], 'erp.css') && str_contains($dashboard['body'], 'Navegação do ERP'), 'shell carrega CSS ERP e sidebar interna');
$dashboard30 = $request('/erp?period=30');
$check($dashboard30['status'] === 200 && str_contains($dashboard30['body'], 'Os dados financeiros ainda não estão conectados') && !str_contains($dashboard30['body'], 'erp-chart-count-6'), 'ambiente testing não renderiza fluxo de caixa demonstrativo');
$dashboardMonth = $request('/erp?period=month');
$check($dashboardMonth['status'] === 200 && str_contains($dashboardMonth['body'], 'Os dados financeiros ainda não estão conectados') && !str_contains($dashboardMonth['body'], 'erp-chart-count-6'), 'ambiente testing continua sem valores financeiros fictícios');

$pages = [
    'payables'=>'Contas a pagar', 'receivables'=>'Contas a receber', 'billing'=>'Cobranças',
    'condominiums'=>'Condomínios', 'people'=>'Pessoas', 'units'=>'Unidades',
    'bank-accounts'=>'Contas bancárias', 'reconciliation'=>'Conciliação',
];
foreach ($pages as $path => $heading) {
    $page = $request('/erp/' . $path);
    $check($page['status'] === 200 && str_contains($page['body'], '<h1>' . $heading . '</h1>'), $path . ' responde 200 com título correto');
    $check(!str_contains($page['body'], 'Warning:') && !str_contains($page['body'], 'Fatal error'), $path . ' sem erro PHP visível');
}
$payables = $request('/erp/payables');
$check($payables['status'] === 200 && str_contains($payables['body'], 'Nenhuma obrigação registrada') && str_contains($payables['body'], 'erp-payables.js'), 'contas a pagar usa dados persistidos e carrega o formulário dinâmico');
$payableForm = $request('/erp/payables/new');
$check($payableForm['status'] === 200 && str_contains($payableForm['body'], 'Registrar obrigação') && str_contains($payableForm['body'], 'Adicionar parcela'), 'formulário de obrigação responde e permite parcelas explícitas');
$condominiumContext = $request('/erp?condominium_id=' . $condominiumId);
$check($condominiumContext['status'] === 200 && str_contains($condominiumContext['body'], 'value="' . $condominiumId . '" selected'), 'contexto do condomínio atual pode ser selecionado');
$otherTenantContext = $request('/erp?condominium_id=' . $otherCondominiumId);
$check($otherTenantContext['status'] === 200 && !str_contains($otherTenantContext['body'], 'Residencial Tenant B'), 'seletor não expõe condomínio de outro tenant');
$check($request('/themes/admin/css/erp.css')['status'] === 200, 'asset ERP CSS responde HTTP 200');
$check($request('/erp/not-a-route')['status'] === 404, 'rota inexistente responde 404');

$anonymousCurl = curl_init();
curl_setopt_array($anonymousCurl, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROXY=>'',CURLOPT_TIMEOUT=>10]);
curl_setopt($anonymousCurl, CURLOPT_URL, $base . '/erp/payables');
$anonymousResponse = curl_exec($anonymousCurl);
$anonymousHeaders = is_string($anonymousResponse) ? substr($anonymousResponse, 0, (int) curl_getinfo($anonymousCurl, CURLINFO_HEADER_SIZE)) : '';
preg_match('/^Location:\s*(.+)$/mi', $anonymousHeaders, $anonymousLocation);
$check(str_ends_with(trim($anonymousLocation[1] ?? ''), '/login'), 'rota ERP exige autenticação');

@unlink($cookieFile);
echo "OK: $checks verificações HTTP ERP visual.\n";
