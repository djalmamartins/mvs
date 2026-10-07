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
    || !preg_match('/^moves_codex_erp_periods_[a-zA-Z0-9_]+$/', $database)
    || !in_array(gethostbyname($host), ['127.0.0.1', '::1'], true)) {
    throw new RuntimeException('Periods E2E requer APP_ENV=testing, APP_URL local e DB_DATABASE moves_codex_erp_periods_*.');
}

$pdo = Connection::getInstance();
$suffix = bin2hex(random_bytes(5));
$password = 'MovesERP-PeriodTest-2026!';
$seed = static function (string $name, string $slug, string $email, bool $enabled = true) use ($pdo, $password): array {
    $pdo->prepare("INSERT INTO users(name,email,password,status,role) VALUES(?,?,?,'active','user')")
        ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
    $userId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO talk_tenants(name,slug,status) VALUES(?,?,'active')")->execute([$name, $slug]);
    $tenantId = (int) $pdo->lastInsertId();
    (new CompanyService($pdo))->ensureRoles($tenantId);
    $roleQuery = $pdo->prepare("SELECT id FROM platform_roles WHERE tenant_id=? AND slug='owner'");
    $roleQuery->execute([$tenantId]);
    $pdo->prepare("INSERT INTO talk_tenant_users(tenant_id,user_id,role,role_id,status,is_default) VALUES(?,?,'admin',?,'active',1)")
        ->execute([$tenantId, $userId, (int) $roleQuery->fetchColumn()]);
    $pdo->prepare("INSERT INTO platform_tenant_products(tenant_id,product,enabled) VALUES(?,'erp',?)")
        ->execute([$tenantId, $enabled ? 1 : 0]);
    $suffix = substr(hash('sha256', $slug), 0, 10);
    $pdo->prepare("INSERT INTO erp_administrators(tenant_id,legal_name,trade_name,tax_id,status) VALUES(?,?,?,?, 'active')")
        ->execute([$tenantId, $name, $name, 'P' . $suffix]);
    $administratorId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO erp_scope_grants(user_id,capability,scope_type,scope_id) VALUES(?,'erp.access','administrator',?)")
        ->execute([$userId, $administratorId]);
    $pdo->prepare("INSERT INTO erp_condominiums(administrator_id,legal_name,trade_name,tax_id,status) VALUES(?,?,?,?,'active')")
        ->execute([$administratorId, 'Residencial ' . $name, 'Residencial ' . $name, 'C' . $suffix]);
    return ['user_id'=>$userId,'tenant_id'=>$tenantId,'administrator_id'=>$administratorId,'condominium_id'=>(int)$pdo->lastInsertId(),'email'=>$email];
};
$a = $seed('Administradora Períodos A', 'periods-a-' . $suffix, 'periods-a-' . $suffix . '@example.test');
$b = $seed('Administradora Períodos B', 'periods-b-' . $suffix, 'periods-b-' . $suffix . '@example.test');
$noProduct = $seed('Administradora sem ERP', 'periods-noerp-' . $suffix, 'periods-noerp-' . $suffix . '@example.test', false);
$pdo->prepare("INSERT INTO erp_condominiums(administrator_id,legal_name,trade_name,tax_id,status) VALUES(?,?,?,?,'active')")
    ->execute([$a['administrator_id'], 'Residencial A2 ' . $suffix, 'A2 ' . $suffix, 'A2' . $suffix]);
$aCondo2 = (int) $pdo->lastInsertId();

$checks = 0;
$check = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) {
        throw new RuntimeException('FAIL: ' . $message);
    }
    ++$checks;
    echo 'PASS: ' . $message . PHP_EOL;
};
$csrf = static function (string $body): string {
    if (!preg_match('/name="_token"\s+value="([^"]+)"/', $body, $match)) {
        throw new RuntimeException('Token CSRF ausente.');
    }
    return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5);
};
$client = static function () use ($base): Closure {
    $curl = curl_init();
    if (!$curl instanceof CurlHandle) {
        throw new RuntimeException('cURL indisponível.');
    }
    $cookie = tempnam(sys_get_temp_dir(), 'moves-period-http-');
    if (!is_string($cookie)) {
        throw new RuntimeException('Não foi possível criar cookie jar.');
    }
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_PROXY=>'',CURLOPT_TIMEOUT=>15]);
    return static function (string $path, ?array $data = null) use ($curl, $base): array {
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
        $size = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $headers = substr($raw, 0, $size);
        preg_match('/^Location:\s*(.+)$/mi', $headers, $location);
        return ['status'=>(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE),'location'=>trim($location[1]??''),'body'=>substr($raw,$size)];
    };
};
$login = static function (array $person) use ($client, $csrf, $check): Closure {
    $request = $client();
    $page = $request('/login');
    $response = $request('/login', ['email'=>$person['email'],'password'=>'MovesERP-PeriodTest-2026!','_token'=>$csrf($page['body'])]);
    if ($page['status'] !== 200 || $response['status'] !== 302 || !str_ends_with($response['location'], '/day')) {
        throw new RuntimeException('Login E2E falhou: GET ' . $page['status'] . ', POST ' . $response['status'] . ', destino ' . $response['location'] . ', corpo ' . substr(strip_tags($response['body']), 0, 240));
    }
    $check(true, 'login de ' . $person['email'] . ' autenticado');
    return $request;
};

$requestA = $login($a);
$check($requestA('/erp/periods')['status'] === 200, 'listagem vazia HTTP 200');
$empty = $requestA('/erp/periods');
$check(str_contains($empty['body'],'Nenhuma competência cadastrada.') && str_contains($empty['body'],'Navegação do ERP') && str_contains($empty['body'],'erp.css'), 'empty state, shell, sidebar e CSS presentes');
$form = $requestA('/erp/periods/new');
$check($form['status']===200 && str_contains($form['body'],'Abrir competência'), 'formulário HTTP 200');
$token = $csrf($form['body']);
$before = (int)$pdo->query('SELECT COUNT(*) FROM erp_accounting_periods')->fetchColumn();
$badCsrf = $requestA('/erp/periods',['_token'=>'invalid','condominium_id'=>$a['condominium_id'],'month'=>10,'year'=>2026]);
$check($badCsrf['status']===302 && (int)$pdo->query('SELECT COUNT(*) FROM erp_accounting_periods')->fetchColumn()===$before, 'CSRF inválido bloqueia persistência');
$cross = $requestA('/erp/periods',['_token'=>$token,'condominium_id'=>$b['condominium_id'],'month'=>10,'year'=>2026]);
$check($cross['status']===302 && (int)$pdo->query('SELECT COUNT(*) FROM erp_accounting_periods')->fetchColumn()===$before, 'criação com condomínio tenant B recusada');
$create = $requestA('/erp/periods',['_token'=>$token,'condominium_id'=>$a['condominium_id'],'month'=>10,'year'=>2026,'status'=>'closed','administrator_id'=>$b['administrator_id']]);
preg_match('~/erp/periods/(\d+)~',$create['location'],$match);
$periodId = (int)($match[1]??0);
$check($create['status']===302 && $periodId>0, 'competência criada por HTTP');
$row = $pdo->query('SELECT * FROM erp_accounting_periods WHERE id=' . $periodId)->fetch(PDO::FETCH_ASSOC);
$check(is_array($row) && $row['status']==='open' && (int)$row['administrator_id']===$a['administrator_id'], 'persistência aberta ignora escopo/status enviados pelo cliente');
$duplicateConstraint = false;
try {
    $pdo->prepare("INSERT INTO erp_accounting_periods(administrator_id,condominium_id,period_year,period_month,status,created_by_user_id,updated_by_user_id) VALUES(?,?,2026,10,'open',?,?)")
        ->execute([$a['administrator_id'],$a['condominium_id'],$a['user_id'],$a['user_id']]);
} catch (PDOException) {
    $duplicateConstraint = true;
}
$check($duplicateConstraint, 'constraint MySQL impede duplicidade mesmo em INSERT direto');
$crossTenantConstraint = false;
try {
    $pdo->prepare("INSERT INTO erp_accounting_periods(administrator_id,condominium_id,period_year,period_month,status,created_by_user_id,updated_by_user_id) VALUES(?,?,2027,1,'open',?,?)")
        ->execute([$a['administrator_id'],$b['condominium_id'],$a['user_id'],$a['user_id']]);
} catch (PDOException) {
    $crossTenantConstraint = true;
}
$check($crossTenantConstraint, 'FK composta MySQL impede referência direta a condomínio de outro tenant');
$detail = $requestA('/erp/periods/' . $periodId);
$check($detail['status']===200 && str_contains($detail['body'],'10/2026') && str_contains($detail['body'],'Histórico de auditoria') && str_contains($detail['body'],'erp.accounting_period.created'), 'detalhe e auditoria HTTP 200');
$list = $requestA('/erp/periods?condominium=' . $a['condominium_id'] . '&year=2026&status=open');
$check($list['status']===200 && str_contains($list['body'],'10/2026') && str_contains($list['body'],'Residencial Administradora Períodos A'), 'filtros condomínio, ano e situação');
$duplicate = $requestA('/erp/periods',['_token'=>$token,'condominium_id'=>$a['condominium_id'],'month'=>10,'year'=>2026]);
$duplicateLocation = (string) (parse_url($duplicate['location'], PHP_URL_PATH) ?? '/erp/periods/new');
$duplicatePage = $requestA($duplicateLocation);
$check($duplicate['status']===302 && $duplicatePage['status']===200 && str_contains($duplicatePage['body'],'Já existe uma competência aberta'), 'duplicidade responde controladamente sem HTTP 500');
$createSecond = $requestA('/erp/periods',['_token'=>$token,'condominium_id'=>$aCondo2,'month'=>10,'year'=>2026]);
$check($createSecond['status']===302, 'mesmo período em outro condomínio permitido');
$requestB = $login($b);
$check($requestB('/erp/periods/' . $periodId)['status']===404, 'tenant B não acessa detalhe tenant A');
$bList = $requestB('/erp/periods');
$check($bList['status']===200 && !str_contains($bList['body'],'10/2026'), 'tenant B não lista competência tenant A');
$unauthenticated = $client();
$check($unauthenticated('/erp/periods')['status']===302 && str_contains($unauthenticated('/erp/periods')['location'],'/login'), 'visitante não autenticado redirecionado ao login');
$requestNoProduct = $login($noProduct);
$check($requestNoProduct('/erp/periods')['status']===403, 'tenant sem entitlement ERP recebe HTTP 403');
$pdo->prepare("INSERT INTO users(name,email,password,status,role) VALUES(?,?,?,'active','user')")->execute(['Atendente sem ERP','periods-agent-'.$suffix.'@example.test',password_hash($password,PASSWORD_DEFAULT)]);
$agentId=(int)$pdo->lastInsertId();
$role=$pdo->prepare("SELECT id FROM platform_roles WHERE tenant_id=? AND slug='agent'");$role->execute([$a['tenant_id']]);
$pdo->prepare("INSERT INTO talk_tenant_users(tenant_id,user_id,role,role_id,status,is_default) VALUES(?,?,'agent',?,'active',0)")->execute([$a['tenant_id'],$agentId,(int)$role->fetchColumn()]);
$requestAgent=$login(['email'=>'periods-agent-'.$suffix.'@example.test']);
$check($requestAgent('/erp/periods')['status']===302, 'usuário sem erp.access barrado pelo middleware');
echo "OK: $checks verificações HTTP ERP Competências.\n";
