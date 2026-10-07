<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Moves\Boot\Connection;
use Moves\Boot\Environment;
use Moves\Core\Config;
use Moves\Services\Day\OperationalPendingService;
use Moves\Services\Platform\CompanyService;
use Moves\Services\Platform\CondominiumService;

Environment::load(dirname(__DIR__));
$base = rtrim((string) Config::get('APP_URL', ''), '/');
$database = (string) Config::get('DB_DATABASE', '');
$host = (string) parse_url($base, PHP_URL_HOST);
if (Config::environment() !== 'testing' || !preg_match('/^moves_codex_erp_pending_[a-zA-Z0-9_]+$/', $database) || !in_array(gethostbyname($host), ['127.0.0.1', '::1'], true)) {
    throw new RuntimeException('E2E requer APP_ENV=testing, APP_URL local e DB_DATABASE moves_codex_erp_pending_*.');
}

$pdo = Connection::getInstance();
$suffix = bin2hex(random_bytes(5));
$makeCnpj = static function (bool $alphanumeric): string {
    $base = $alphanumeric
        ? 'AB' . strtoupper(bin2hex(random_bytes(5)))
        : implode('', array_map(static fn (): string => (string) random_int(0, 9), range(1, 12)));
    $digit = static function (string $value, array $weights): string {
        $sum = 0;
        foreach ($weights as $index => $weight) { $sum += (ord($value[$index]) - 48) * $weight; }
        $remainder = $sum % 11;
        return (string) ($remainder < 2 ? 0 : 11 - $remainder);
    };
    $first = $digit($base, [5,4,3,2,9,8,7,6,5,4,3,2]);
    $second = $digit($base . $first, [6,5,4,3,2,9,8,7,6,5,4,3,2]);
    return $base . $first . $second;
};
$password = 'MovesERP-PendingTest-2026!';
$seed = static function (string $label) use ($pdo, $suffix, $password): array {
    $slug = 'pending-' . $suffix . '-' . $label;
    $email = $slug . '@example.test';
    $pdo->prepare("INSERT INTO users(name,email,password,status,role) VALUES(?,?,?,'active','user')")->execute(['Pending ' . $label, $email, password_hash($password, PASSWORD_DEFAULT)]);
    $userId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO talk_tenants(name,slug,status) VALUES(?,?,'active')")->execute(['Pending ' . $label, $slug]);
    $tenantId = (int) $pdo->lastInsertId();
    (new CompanyService($pdo))->ensureRoles($tenantId);
    $role = $pdo->prepare("SELECT id FROM platform_roles WHERE tenant_id=? AND slug='owner'");
    $role->execute([$tenantId]);
    $pdo->prepare("INSERT INTO talk_tenant_users(tenant_id,user_id,role,role_id,status,is_default) VALUES(?,?,'owner',?,'active',1)")->execute([$tenantId, $userId, (int) $role->fetchColumn()]);
    $pdo->prepare("INSERT INTO platform_tenant_products(tenant_id,product,enabled) VALUES(?,'erp',1)")->execute([$tenantId]);
    $pdo->prepare("INSERT INTO erp_administrators(tenant_id,legal_name,trade_name,tax_id,status) VALUES(?,?,?,?,'active')")->execute([$tenantId, 'Administradora ' . $label, 'Administradora ' . $label, 'PENDING-' . $suffix . '-' . $label]);
    $administratorId = (int) $pdo->lastInsertId();
    $grant = $pdo->prepare("INSERT INTO erp_scope_grants(user_id,capability,scope_type,scope_id) VALUES(?,?,'administrator',?)");
    foreach (['erp.access', 'erp.cadastros.read', 'erp.cadastros.write'] as $capability) { $grant->execute([$userId, $capability, $administratorId]); }
    return ['email' => $email, 'user' => $userId, 'tenant' => $tenantId, 'administrator' => $administratorId];
};
$a = $seed('A');
$b = $seed('B');
$condos = new CondominiumService($pdo);
$alphaCnpj = $makeCnpj(true);
$condominiumIds = [
    $condos->save($a['tenant'], ['legal_name' => 'Condomínio numérico ' . $suffix, 'tax_id' => $makeCnpj(false)], $a['user']),
    $condos->save($a['tenant'], ['legal_name' => 'Condomínio alfanumérico ' . $suffix, 'tax_id' => $alphaCnpj], $a['user']),
    $condos->save($a['tenant'], ['legal_name' => 'Condomínio sem CNPJ ' . $suffix], $a['user']),
    $condos->save($a['tenant'], ['legal_name' => 'Condomínio em processo ' . $suffix], $a['user']),
    $condos->save($a['tenant'], ['legal_name' => 'Condomínio complexo ' . $suffix, 'tax_id' => $makeCnpj(false)], $a['user']),
];
$pending = new OperationalPendingService($pdo);
$tasks = $pending->list($a['tenant'], $a['administrator']);
if (count($tasks) !== 2 || array_map('intval', array_column($tasks, 'condominium_id')) !== [$condominiumIds[2], $condominiumIds[3]]) {
    throw new RuntimeException('Os cinco cenários de homologação não produziram exatamente duas pendências.');
}
$taskC = (int) $tasks[0]['id'];
$taskD = (int) $tasks[1]['id'];

$checks = 0;
$check = static function (bool $ok, string $message) use (&$checks): void { if (!$ok) { throw new RuntimeException('FAIL: ' . $message); } ++$checks; echo 'PASS: ' . $message . PHP_EOL; };
$csrf = static function (string $body): string { if (!preg_match('/name="_token"\s+value="([^"]+)"/', $body, $match)) { throw new RuntimeException('CSRF token ausente.'); } return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5); };
$client = static function () use ($base): Closure {
    $curl = curl_init();
    if (!$curl instanceof CurlHandle) { throw new RuntimeException('cURL indisponível.'); }
    $cookie = tempnam(sys_get_temp_dir(), 'moves-pending-http-');
    if (!is_string($cookie)) { throw new RuntimeException('Cookie jar temporário indisponível.'); }
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_PROXY=>'',CURLOPT_TIMEOUT=>15]);
    return static function (string $path, ?array $data = null) use ($curl, $base): array {
        curl_setopt($curl, CURLOPT_URL, $base . $path);
        if ($data === null) { curl_setopt($curl, CURLOPT_HTTPGET, true); curl_setopt($curl, CURLOPT_POST, false); }
        else { curl_setopt($curl, CURLOPT_POST, true); curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data)); }
        $raw = curl_exec($curl);
        if (!is_string($raw)) { throw new RuntimeException('HTTP falhou: ' . curl_error($curl)); }
        $size = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $headers = substr($raw, 0, $size);
        preg_match('/^Location:\s*(.+)$/mi', $headers, $location);
        return ['status'=>(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE),'location'=>trim($location[1]??''),'body'=>substr($raw,$size)];
    };
};
$login = static function (array $person) use ($client, $csrf): Closure { $request = $client(); $page = $request('/login'); $auth = $request('/login', ['email'=>$person['email'],'password'=>'MovesERP-PendingTest-2026!','_token'=>$csrf($page['body'])]); if ($auth['status']!==302 || !str_ends_with($auth['location'],'/day')) { throw new RuntimeException('Login de teste falhou: '.$auth['status'].' '.$auth['location']); } return $request; };
$requestA = $login($a);
$list = $requestA('/erp/pending');
preg_match('/<tbody>(.*?)<\/tbody>/s', $list['body'], $listTable);
$listRows = $listTable[1] ?? '';
$check($list['status']===200 && str_contains($listRows,'Condomínio sem CNPJ '.$suffix) && str_contains($listRows,'Condomínio em processo '.$suffix) && !str_contains($listRows,'Condomínio numérico '.$suffix) && str_contains($listRows,'Sem responsável'), 'visão supervisora lista as duas pendências, sem incluir CNPJs válidos');
$byCondominium = $requestA('/erp/pending?condominium_id='.$condominiumIds[2]);
preg_match('/<tbody>(.*?)<\/tbody>/s', $byCondominium['body'], $filteredTable);
$filteredRows = $filteredTable[1] ?? '';
$check($byCondominium['status']===200 && str_contains($filteredRows,'Condomínio sem CNPJ '.$suffix) && !str_contains($filteredRows,'Condomínio em processo '.$suffix), 'filtro por condomínio restringe a visão');
$detail = $requestA('/erp/pending/'.$taskD);
$check($detail['status']===200 && str_contains($detail['body'],'/erp/condominiums/'.$condominiumIds[3].'/edit') && str_contains($detail['body'],'Histórico'), 'detalhe liga a pendência ao cadastro autorizado e mostra histórico');
$badCsrf = $requestA('/erp/pending/'.$taskD, ['_token'=>'invalid','assigned_user_id'=>(string)$a['user'],'status'=>'in_progress','priority'=>'urgent','description'=>'invalid']);
$check($badCsrf['status']===302 && $pending->detail($a['tenant'],$a['administrator'],$taskD)['status']==='pending', 'CSRF inválido não altera a pendência');
$update = $requestA('/erp/pending/'.$taskD, ['_token'=>$csrf($detail['body']),'assigned_user_id'=>(string)$a['user'],'status'=>'in_progress','priority'=>'high','due_date'=>date('Y-m-d',strtotime('+5 days')),'description'=>'CNPJ em processamento; prazo interno acompanhado pela equipe.']);
$check($update['status']===302 && $pending->detail($a['tenant'],$a['administrator'],$taskD)['status']==='in_progress', 'responsável, status e prazo operacional persistem por HTTP');
$day = $requestA('/day');
$check($day['status']===200 && str_contains($day['body'],'Condomínio em processo '.$suffix) && str_contains($day['body'],'Pendência automática') && str_contains($day['body'],'/erp/condominiums/'.$condominiumIds[3]), 'Meu Dia mostra a tarefa atribuída e deep link para o condomínio');
$edit = $requestA('/erp/condominiums/'.$condominiumIds[3].'/edit');
$resolved = $requestA('/erp/condominiums/'.$condominiumIds[3].'/edit', ['_token'=>$csrf($edit['body']),'legal_name'=>'Condomínio em processo '.$suffix,'tax_id'=>$makeCnpj(true),'status'=>'active']);
$check($resolved['status']===302 && $pending->detail($a['tenant'],$a['administrator'],$taskD)['status']==='done', 'CNPJ alfanumérico informado por HTTP resolve a mesma tarefa');
$check(!str_contains($requestA('/day')['body'],'Condomínio em processo '.$suffix), 'Meu Dia deixa de exibir a tarefa após resolução automática');
$editC = $requestA('/erp/condominiums/'.$condominiumIds[2].'/edit');
$resolvedC = $requestA('/erp/condominiums/'.$condominiumIds[2].'/edit', ['_token'=>$csrf($editC['body']),'legal_name'=>'Condomínio sem CNPJ '.$suffix,'tax_id'=>$makeCnpj(false),'status'=>'active']);
$check($resolvedC['status']===302 && $pending->detail($a['tenant'],$a['administrator'],$taskC)['status']==='done', 'CNPJ numérico informado por HTTP resolve a pendência');
$requestB = $login($b);
$listB = $requestB('/erp/pending');
$check($listB['status']===200 && !str_contains($listB['body'],'Condomínio sem CNPJ '.$suffix) && !str_contains($listB['body'],'Condomínio em processo '.$suffix), 'tenant B vê somente sua própria lista de pendências');
$check($requestB('/erp/pending/'.$taskC)['status']===404, 'tenant B não abre pendência de tenant A por ID');
$condoB = $requestB('/erp/condominiums/'.$condominiumIds[2]);
$check($condoB['status']===404, 'tenant B não abre condomínio de tenant A por ID');
$check($requestA('/themes/admin/css/erp.css')['status']===200, 'asset CSS do ERP responde HTTP 200');
$cnpjSearch = $requestA('/erp/condominiums?q=' . urlencode($alphaCnpj));
$check($cnpjSearch['status']===200 && str_contains($cnpjSearch['body'],'Condomínio alfanumérico '.$suffix), 'busca encontra CNPJ alfanumérico normalizado');
echo "OK: $checks verificações HTTP/E2E pendências ERP e cinco cenários.\n";
