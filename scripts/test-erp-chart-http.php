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
    || !preg_match('/^moves_codex_erp_chart_[a-zA-Z0-9_]+$/', $database)
    || !in_array(gethostbyname($host), ['127.0.0.1', '::1'], true)) {
    throw new RuntimeException('Chart E2E exige APP_ENV=testing, URL local e banco descartável moves_codex_erp_chart_*.');
}
$pdo = Connection::getInstance();
$suffix = bin2hex(random_bytes(5));
$password = 'MovesERP-ChartTest-2026!';
$createdIds = [];
$seed = static function (string $name, string $slug, string $email, bool $enabled = true) use ($pdo, $password, &$createdIds): array {
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
    $taxSuffix = substr(hash('sha256', $slug), 0, 10);
    $pdo->prepare("INSERT INTO erp_administrators(tenant_id,legal_name,trade_name,tax_id,status) VALUES(?,?,?,?, 'active')")
        ->execute([$tenantId, $name, $name, 'P' . $taxSuffix]);
    $administratorId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO erp_scope_grants(user_id,capability,scope_type,scope_id) VALUES(?,'erp.access','administrator',?)")
        ->execute([$userId, $administratorId]);
    $pdo->prepare("INSERT INTO erp_condominiums(administrator_id,legal_name,trade_name,tax_id,status) VALUES(?,?,?,?,'active')")
        ->execute([$administratorId, 'Residencial ' . $name, 'Residencial ' . $name, 'C' . $taxSuffix]);
    $condominiumId = (int) $pdo->lastInsertId();
    $createdIds[] = ['user' => $userId, 'tenant' => $tenantId];
    return ['user_id'=>$userId,'tenant_id'=>$tenantId,'administrator_id'=>$administratorId,'condominium_id'=>$condominiumId,'email'=>$email];
};
$a = $seed('Administradora Plano A', 'chart-a-' . $suffix, 'chart-a-' . $suffix . '@example.test');
$b = $seed('Administradora Plano B', 'chart-b-' . $suffix, 'chart-b-' . $suffix . '@example.test');
$noProduct = $seed('Administradora sem ERP', 'chart-noerp-' . $suffix, 'chart-noerp-' . $suffix . '@example.test', false);
$ownerPasswordHash = password_hash($password, PASSWORD_DEFAULT);

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
$clients = [];
register_shutdown_function(static function () use (&$clients): void {
    foreach ($clients as [, $cookie]) {
        if (is_file($cookie)) {
            unlink($cookie);
        }
    }
});
$client = static function () use ($base, &$clients): Closure {
    $curl = curl_init();
    if (!$curl instanceof CurlHandle) {
        throw new RuntimeException('cURL indisponível.');
    }
    $cookie = tempnam(sys_get_temp_dir(), 'moves-chart-http-');
    if (!is_string($cookie)) {
        throw new RuntimeException('Não foi possível criar cookie jar.');
    }
    $clients[] = [$curl, $cookie];
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
        preg_match('/^Location:\s*(.+)$/mi', substr($raw, 0, $size), $location);
        return ['status'=>(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE),'location'=>trim($location[1]??''),'body'=>substr($raw,$size)];
    };
};
$login = static function (array $person) use ($client, $csrf, $check): Closure {
    $request = $client();
    $page = $request('/login');
    $response = $request('/login', ['email'=>$person['email'],'password'=>'MovesERP-ChartTest-2026!','_token'=>$csrf($page['body'])]);
    if ($page['status']!==200 || $response['status']!==302 || !str_ends_with($response['location'],'/day')) {
        throw new RuntimeException('Login HTTP inesperado: GET '.$page['status'].', POST '.$response['status'].', Location '.$response['location'].', corpo '.substr(strip_tags($response['body']),0,300));
    }
    $check(true, 'login autenticado para '.$person['email']);
    return $request;
};
$requestA = $login($a);
$list = $requestA('/erp/chart-of-accounts');
$check($list['status']===200 && str_contains($list['body'],'Navegação do ERP') && str_contains($list['body'],'erp.css'), 'página inicial HTTP 200 com shell e asset ERP');
$planForm = $requestA('/erp/chart-of-accounts/new');
$check($planForm['status']===200 && str_contains($planForm['body'],'Criar plano de contas'), 'formulário de plano HTTP 200');
$plansBefore = (int)$pdo->query('SELECT COUNT(*) FROM erp_accounting_plans')->fetchColumn();
$badCsrf = $requestA('/erp/chart-of-accounts',['_token'=>'invalid','name'=>'Inválido','condominium_id'=>$a['condominium_id']]);
$check($badCsrf['status']===302 && (int)$pdo->query('SELECT COUNT(*) FROM erp_accounting_plans')->fetchColumn()===$plansBefore, 'CSRF inválido não persiste plano');
$planToken = $csrf($planForm['body']);
$crossCondo = $requestA('/erp/chart-of-accounts',['_token'=>$planToken,'name'=>'Cross','condominium_id'=>$b['condominium_id']]);
$check($crossCondo['status']===302 && (int)$pdo->query('SELECT COUNT(*) FROM erp_accounting_plans')->fetchColumn()===$plansBefore, 'criação com condomínio tenant B recusada');
$createdPlan = $requestA('/erp/chart-of-accounts',['_token'=>$planToken,'name'=>'Plano Residencial A','condominium_id'=>$a['condominium_id'],'administrator_id'=>$b['administrator_id']]);
preg_match('~/erp/chart-of-accounts/(\d+)~',$createdPlan['location'],$planMatch);
$planId = (int)($planMatch[1]??0);
$check($createdPlan['status']===302 && $planId>0, 'plano persistido por HTTP');
$planRow = $pdo->query('SELECT administrator_id,condominium_id,status FROM erp_accounting_plans WHERE id='.$planId)->fetch(PDO::FETCH_ASSOC);
$check(is_array($planRow) && (int)$planRow['administrator_id']===$a['administrator_id'] && (int)$planRow['condominium_id']===$a['condominium_id'] && $planRow['status']==='active', 'plano guarda escopo ativo do tenant apesar de dados forjados');
$detail = $requestA('/erp/chart-of-accounts/'.$planId);
$check($detail['status']===200 && str_contains($detail['body'],'Plano Residencial A') && str_contains($detail['body'],'Estrutura de contas'), 'detalhe do plano HTTP 200');
$accountForm = $requestA('/erp/chart-of-accounts/'.$planId.'/accounts/new');
$check($accountForm['status']===200 && str_contains($accountForm['body'],'Dados da conta'), 'formulário de conta HTTP 200');
$token = $csrf($accountForm['body']);
$accountsBefore = (int)$pdo->query('SELECT COUNT(*) FROM erp_accounting_accounts')->fetchColumn();
$badAccountCsrf = $requestA('/erp/chart-of-accounts/'.$planId.'/accounts',['_token'=>'invalid','code'=>'1','name'=>'Não','nature'=>'asset','account_type'=>'synthetic']);
$check($badAccountCsrf['status']===302 && (int)$pdo->query('SELECT COUNT(*) FROM erp_accounting_accounts')->fetchColumn()===$accountsBefore, 'CSRF inválido não persiste conta');
$rootResponse = $requestA('/erp/chart-of-accounts/'.$planId.'/accounts',['_token'=>$token,'code'=>'1','name'=>'Receitas','nature'=>'revenue','account_type'=>'synthetic','status'=>'active']);
preg_match('~/accounts/(\d+)~',$rootResponse['location'],$rootMatch);
$rootId = (int)($rootMatch[1]??0);
$check($rootResponse['status']===302 && $rootId>0, 'conta raiz criada por HTTP');
$childForm = $requestA('/erp/chart-of-accounts/'.$planId.'/accounts/new');
$child = $requestA('/erp/chart-of-accounts/'.$planId.'/accounts',['_token'=>$csrf($childForm['body']),'code'=>'1.1','name'=>'Receita recorrente','nature'=>'revenue','account_type'=>'analytic','parent_id'=>$rootId]);
preg_match('~/accounts/(\d+)~',$child['location'],$childMatch);
$childId = (int)($childMatch[1]??0);
$check($child['status']===302 && $childId>0, 'conta analítica filha criada por HTTP');
$after = $requestA('/erp/chart-of-accounts/'.$planId.'?q=1.1&nature=revenue&status=active');
$check($after['status']===200 && str_contains($after['body'],'1.1') && str_contains($after['body'],'Receita recorrente'), 'busca e filtros exibem a conta correta');
$accountDetail = $requestA('/erp/chart-of-accounts/'.$planId.'/accounts/'.$childId);
$check($accountDetail['status']===200 && str_contains($accountDetail['body'],'Histórico de auditoria') && str_contains($accountDetail['body'],'erp.accounting_account.created'), 'detalhe de conta e auditoria HTTP 200');
$editForm = $requestA('/erp/chart-of-accounts/'.$planId.'/accounts/'.$childId.'/edit');
$edit = $requestA('/erp/chart-of-accounts/'.$planId.'/accounts/'.$childId.'/edit',['_token'=>$csrf($editForm['body']),'code'=>'1.1','name'=>'Receita mensal','nature'=>'revenue','account_type'=>'analytic','parent_id'=>$rootId,'status'=>'inactive','sort_order'=>'4']);
$check($edit['status']===302 && $requestA('/erp/chart-of-accounts/'.$planId.'/accounts/'.$childId)['status']===200 && str_contains($requestA('/erp/chart-of-accounts/'.$planId.'/accounts/'.$childId)['body'],'Receita mensal'), 'edição e status da conta persistidos');
$planB = $pdo->prepare("INSERT INTO erp_accounting_plans(administrator_id,condominium_id,name,status,created_by_user_id,updated_by_user_id) VALUES(?,?,?,'active',?,?)");
$planB->execute([$b['administrator_id'],$b['condominium_id'],'Plano B',$b['user_id'],$b['user_id']]);
$planBId = (int)$pdo->lastInsertId();
$accountB = $pdo->prepare("INSERT INTO erp_accounting_accounts(administrator_id,plan_id,code,name,nature,account_type,level,status,created_by_user_id,updated_by_user_id) VALUES(?,?,?,'Conta B','asset','synthetic',1,'active',?,?)");
$accountB->execute([$b['administrator_id'],$planBId,'B1',$b['user_id'],$b['user_id']]);
$accountBId = (int)$pdo->lastInsertId();
$crossParentForm = $requestA('/erp/chart-of-accounts/'.$planId.'/accounts/new');
$crossParent = $requestA('/erp/chart-of-accounts/'.$planId.'/accounts',['_token'=>$csrf($crossParentForm['body']),'code'=>'9','name'=>'Pai externo','nature'=>'asset','account_type'=>'synthetic','parent_id'=>$accountBId]);
$check($crossParent['status']===302 && (int)$pdo->query('SELECT COUNT(*) FROM erp_accounting_accounts WHERE plan_id='.$planId.' AND code=\'9\'')->fetchColumn()===0, 'conta pai de tenant/plano B é recusada sem persistência');
$crossConstraint = false;
try {
    $pdo->prepare("INSERT INTO erp_accounting_accounts(administrator_id,plan_id,parent_id,code,name,nature,account_type,level,status,created_by_user_id,updated_by_user_id) VALUES(?,?,?,?,?,'asset','synthetic',2,'active',?,?)")
        ->execute([$a['administrator_id'],$planId,$accountBId,'8','FK cruzada',$a['user_id'],$a['user_id']]);
} catch (PDOException) {
    $crossConstraint = true;
}
$check($crossConstraint, 'FK composta MySQL impede parent de outro plano/tenant');
$requestB = $login($b);
$check($requestB('/erp/chart-of-accounts/'.$planId)['status']===404 && $requestB('/erp/chart-of-accounts/'.$planId.'/accounts/'.$childId)['status']===404, 'tenant B não acessa plano ou conta A');
$check($requestB('/erp/chart-of-accounts')['status']===200 && !str_contains($requestB('/erp/chart-of-accounts')['body'],'Plano Residencial A'), 'listagem B não revela plano A');
$anonymous = $client();
$check($anonymous('/erp/chart-of-accounts')['status']===302 && str_contains($anonymous('/erp/chart-of-accounts')['location'],'/login'), 'visitante é redirecionado para login');
$noProductRequest = $login($noProduct);
$check($noProductRequest('/erp/chart-of-accounts')['status']===403, 'tenant sem entitlement ERP recebe HTTP 403');
$pdo->prepare("INSERT INTO users(name,email,password,status,role) VALUES(?,?,?,'active','user')")
    ->execute(['Agente sem ERP','chart-agent-'.$suffix.'@example.test',$ownerPasswordHash]);
$agentId = (int)$pdo->lastInsertId();
$role = $pdo->prepare("SELECT id FROM platform_roles WHERE tenant_id=? AND slug='agent'");
$role->execute([$a['tenant_id']]);
$pdo->prepare("INSERT INTO talk_tenant_users(tenant_id,user_id,role,role_id,status,is_default) VALUES(?,?,'agent',?,'active',0)")
    ->execute([$a['tenant_id'],$agentId,(int)$role->fetchColumn()]);
$agentRequest = $login(['email'=>'chart-agent-'.$suffix.'@example.test']);
$check($agentRequest('/erp/chart-of-accounts')['status']===302, 'usuário sem erp.access barrado');
echo "OK: $checks verificações HTTP ERP Plano de Contas.\n";
