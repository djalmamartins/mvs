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
    || !preg_match('/^moves_codex_erp_receivables_[a-zA-Z0-9_]+$/', $database)
    || !in_array(gethostbyname($host), ['127.0.0.1', '::1'], true)) {
    throw new RuntimeException('Receivables E2E requer APP_ENV=testing, APP_URL local e DB_DATABASE moves_codex_erp_receivables_*.');
}

$pdo = Connection::getInstance();
$suffix = bin2hex(random_bytes(5));
$password = 'MovesERP-ReceivableTest-2026!';
$seed = static function (string $name, string $slug) use ($pdo, $password): array {
    $pdo->prepare("INSERT INTO users(name,email,password,status,role) VALUES(?,?,?,'active','user')")
        ->execute([$name, $slug . '@example.test', password_hash($password, PASSWORD_DEFAULT)]);
    $userId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO talk_tenants(name,slug,status) VALUES(?,?,'active')")->execute([$name, $slug]);
    $tenantId = (int) $pdo->lastInsertId();
    (new CompanyService($pdo))->ensureRoles($tenantId);
    $role = $pdo->prepare("SELECT id FROM platform_roles WHERE tenant_id=? AND slug='owner'");
    $role->execute([$tenantId]);
    $pdo->prepare("INSERT INTO talk_tenant_users(tenant_id,user_id,role,role_id,status,is_default) VALUES(?,?,'admin',?,'active',1)")
        ->execute([$tenantId, $userId, (int) $role->fetchColumn()]);
    $pdo->prepare("INSERT INTO platform_tenant_products(tenant_id,product,enabled) VALUES(?,'erp',1)")->execute([$tenantId]);
    $taxSuffix = substr(hash('sha256', $slug), 0, 10);
    $pdo->prepare("INSERT INTO erp_administrators(tenant_id,legal_name,trade_name,tax_id,status) VALUES(?,?,?,?, 'active')")
        ->execute([$tenantId, $name, $name, 'P' . $taxSuffix]);
    $administratorId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO erp_scope_grants(user_id,capability,scope_type,scope_id) VALUES(?,'erp.access','administrator',?)")
        ->execute([$userId, $administratorId]);
    $pdo->prepare("INSERT INTO erp_condominiums(administrator_id,legal_name,trade_name,tax_id,status) VALUES(?,?,?,?,'active')")
        ->execute([$administratorId, 'Residencial ' . $name, 'Residencial ' . $name, 'C' . $taxSuffix]);
    return ['user_id'=>$userId,'tenant_id'=>$tenantId,'administrator_id'=>$administratorId,'condominium_id'=>(int)$pdo->lastInsertId(),'email'=>$slug.'@example.test'];
};
$a = $seed('Administradora Recebíveis A', 'receivables-a-' . $suffix);
$b = $seed('Administradora Recebíveis B', 'receivables-b-' . $suffix);
$createReceivableFoundation = static function (array $tenant, string $unitCode, string $personName, string $taxSuffix) use ($pdo): array {
    $pdo->prepare('INSERT INTO erp_units(condominium_id,code,status) VALUES(?,?,\'active\')')->execute([$tenant['condominium_id'],$unitCode]);
    $unitId = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO erp_people(administrator_id,entity_type,full_name,status,created_by_user_id) VALUES(?,'person',?,'active',?)")
        ->execute([$tenant['administrator_id'],$personName,$tenant['user_id']]);
    $personId = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO erp_person_links(administrator_id,person_id,condominium_id,unit_id,role,starts_at,status,source,created_by_user_id) VALUES(?,?,?,?,'owner',CURRENT_DATE,'active','staff',?)")
        ->execute([$tenant['administrator_id'],$personId,$tenant['condominium_id'],$unitId,$tenant['user_id']]);
    $linkId = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO erp_accounting_plans(administrator_id,condominium_id,name,status,created_by_user_id,updated_by_user_id) VALUES(?,?,?,'active',?,?)")
        ->execute([$tenant['administrator_id'],$tenant['condominium_id'],'Plano '.$taxSuffix,$tenant['user_id'],$tenant['user_id']]);
    $planId = (int)$pdo->lastInsertId();
    $account = $pdo->prepare("INSERT INTO erp_accounting_accounts(administrator_id,plan_id,code,name,nature,account_type,level,status,created_by_user_id,updated_by_user_id) VALUES(?,?,?,?,?,'analytic',1,'active',?,?)");
    $account->execute([$tenant['administrator_id'],$planId,'1.1','Recebíveis','asset',$tenant['user_id'],$tenant['user_id']]);
    $assetAccountId = (int)$pdo->lastInsertId();
    $account->execute([$tenant['administrator_id'],$planId,'3.1','Receita','revenue',$tenant['user_id'],$tenant['user_id']]);
    $revenueAccountId = (int)$pdo->lastInsertId();
    $periodYear = (int)date('Y'); $periodMonth = (int)date('n');
    $pdo->prepare("INSERT INTO erp_accounting_periods(administrator_id,condominium_id,period_year,period_month,status,created_by_user_id,updated_by_user_id) VALUES(?,?,?,?,'open',?,?)")
        ->execute([$tenant['administrator_id'],$tenant['condominium_id'],$periodYear,$periodMonth,$tenant['user_id'],$tenant['user_id']]);
    return ['unit_id'=>$unitId,'person_link_id'=>$linkId,'plan_id'=>$planId,'asset_account_id'=>$assetAccountId,'revenue_account_id'=>$revenueAccountId,'period_id'=>(int)$pdo->lastInsertId(),'unit_code'=>$unitCode,'person_name'=>$personName];
};
$aData = $createReceivableFoundation($a,'A-12','Pessoa Recebível A',$suffix);
$bData = $createReceivableFoundation($b,'B-3','Pessoa Recebível B',$suffix);

$checks = 0;
$check = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) throw new RuntimeException('FAIL: ' . $message);
    ++$checks;
    echo 'PASS: ' . $message . PHP_EOL;
};
$csrf = static function (string $body): string {
    if (!preg_match('/name="_token"\s+value="([^"]+)"/', $body, $match)) throw new RuntimeException('Token CSRF ausente.');
    return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5);
};
$client = static function () use ($base): Closure {
    $curl = curl_init();
    if (!$curl instanceof CurlHandle) throw new RuntimeException('cURL indisponível.');
    $cookie = tempnam(sys_get_temp_dir(), 'moves-receivable-http-');
    if (!is_string($cookie)) throw new RuntimeException('Não foi possível criar cookie jar.');
    curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_PROXY=>'',CURLOPT_TIMEOUT=>15]);
    return static function (string $path, ?array $data=null) use ($curl,$base): array {
        curl_setopt($curl,CURLOPT_URL,$base.$path);
        if($data===null){curl_setopt($curl,CURLOPT_HTTPGET,true);curl_setopt($curl,CURLOPT_POST,false);}else{curl_setopt($curl,CURLOPT_POST,true);curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($data));}
        $raw=curl_exec($curl);if(!is_string($raw))throw new RuntimeException('HTTP falhou: '.curl_error($curl));
        $size=(int)curl_getinfo($curl,CURLINFO_HEADER_SIZE);$headers=substr($raw,0,$size);preg_match('/^Location:\s*(.+)$/mi',$headers,$location);
        return ['status'=>(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE),'location'=>trim($location[1]??''),'body'=>substr($raw,$size)];
    };
};
$login = static function (array $person) use ($client,$csrf,$check,$password): Closure {
    $request=$client();$page=$request('/login');$response=$request('/login',['email'=>$person['email'],'password'=>$password,'_token'=>$csrf($page['body'])]);
    if($page['status']!==200||$response['status']!==302||!str_ends_with($response['location'],'/day'))throw new RuntimeException('Login falhou para '.$person['email'].'.');
    $check(true,'login de tenant realizado');return $request;
};

$requestA = $login($a);
$unauth = $client();
$guest = $unauth('/erp/receivables');
$check($guest['status']===302 && str_contains($guest['location'],'/login'),'visitante sem sessão retorna ao login');
$empty = $requestA('/erp/receivables');
$check($empty['status']===200 && str_contains($empty['body'],'Nenhum título a receber encontrado') && str_contains($empty['body'],'Navegação do ERP') && str_contains($empty['body'],'erp.css'),'listagem, shell, sidebar e CSS retornam HTTP 200');
$form = $requestA('/erp/receivables/new');
$check($form['status']===200 && str_contains($form['body'],'Adicionar parcela') && str_contains($form['body'],'erp-receivables.js'),'formulário e asset JavaScript carregam');
$token = $csrf($form['body']);
$before = (int)$pdo->query('SELECT COUNT(*) FROM erp_receivables')->fetchColumn();
$badCsrf = $requestA('/erp/receivables',['_token'=>'invalid']);
$check($badCsrf['status']===302 && (int)$pdo->query('SELECT COUNT(*) FROM erp_receivables')->fetchColumn()===$before,'CSRF inválido não persiste título');
$baseData=['_token'=>$token,'person_link_id'=>$aData['person_link_id'],'receivable_account_id'=>$aData['asset_account_id'],'revenue_account_id'=>$aData['revenue_account_id'],'description'=>'Taxa de homologação','total_amount'=>'100.50','installments'=>['period_id'=>[$aData['period_id'],$aData['period_id']],'due_date'=>[date('Y-m-d',strtotime('+7 days')),date('Y-m-d',strtotime('+37 days'))],'amount'=>['50.25','50.25']]];
$badTotal=$baseData;$badTotal['total_amount']='100.51';$failed=$requestA('/erp/receivables',$badTotal);
$check($failed['status']===302 && (int)$pdo->query('SELECT COUNT(*) FROM erp_receivables')->fetchColumn()===$before,'soma diferente do total é rejeitada');
$cross=$baseData;$cross['person_link_id']=$bData['person_link_id'];$crossResponse=$requestA('/erp/receivables',$cross);
$check($crossResponse['status']===302 && (int)$pdo->query('SELECT COUNT(*) FROM erp_receivables')->fetchColumn()===$before,'vínculo de tenant B é recusado no serviço do tenant A');
$created=$requestA('/erp/receivables',$baseData);preg_match('~/erp/receivables/(\d+)~',$created['location'],$match);$receivableId=(int)($match[1]??0);
$check($created['status']===302 && $receivableId>0,'título com duas parcelas criado por HTTP');
$stored=$pdo->prepare('SELECT * FROM erp_receivables WHERE id=?');$stored->execute([$receivableId]);$row=$stored->fetch(PDO::FETCH_ASSOC);
$check(is_array($row)&&(int)$row['administrator_id']===$a['administrator_id']&&(int)$row['unit_id']===$aData['unit_id']&&number_format((float)$row['total_amount'],2,'.','')==='100.50','título persiste em tenant/unidade e total corretos');
$detail=$requestA('/erp/receivables/'.$receivableId);
$check($detail['status']===200&&str_contains($detail['body'],'Pessoa Recebível A')&&str_contains($detail['body'],'50,25')&&str_contains($detail['body'],'erp.receivable.created'),'detalhe, parcelas e auditoria renderizados');
$list=$requestA('/erp/receivables?'.http_build_query(['q'=>'Taxa de homologação']));
$check($list['status']===200&&str_contains($list['body'],'Taxa de homologação'),'busca encontra título persistido');
$bRequest=$login($b);$check($bRequest('/erp/receivables/'.$receivableId)['status']===404,'tenant B recebe 404 ao tentar detalhe do tenant A');
$bList=$bRequest('/erp/receivables');$check($bList['status']===200&&!str_contains($bList['body'],'Taxa de homologação'),'tenant B não lista título de tenant A');

$directCrossTenant=false;
try{$pdo->prepare('INSERT INTO erp_receivables(administrator_id,condominium_id,unit_id,person_link_id,plan_id,receivable_account_id,revenue_account_id,description,total_amount,created_by_user_id) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute([$a['administrator_id'],$a['condominium_id'],$bData['unit_id'],$aData['person_link_id'],$aData['plan_id'],$aData['asset_account_id'],$aData['revenue_account_id'],'FK cross tenant','1.00',$a['user_id']]);}catch(PDOException){$directCrossTenant=true;}
$check($directCrossTenant,'FK composta MySQL impede unidade de outro condomínio/tenant em INSERT direto');
$directCrossPeriod=false;
try{$pdo->prepare('INSERT INTO erp_receivable_installments(administrator_id,condominium_id,receivable_id,installment_number,accounting_period_id,due_date,amount) VALUES(?,?,?,?,?,?,?)')->execute([$a['administrator_id'],$a['condominium_id'],$receivableId,3,$bData['period_id'],date('Y-m-d'),'1.00']);}catch(PDOException){$directCrossPeriod=true;}
$check($directCrossPeriod,'FK composta MySQL impede competência de tenant/condomínio distinto');
echo "OK: $checks verificações HTTP ERP Contas a Receber.\n";
