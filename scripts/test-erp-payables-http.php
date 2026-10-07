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
if (Config::environment() !== 'testing' || !preg_match('/^moves_codex_erp_payables_[a-zA-Z0-9_]+$/', $database)
    || !in_array(gethostbyname($host), ['127.0.0.1', '::1'], true)) {
    throw new RuntimeException('Payables E2E exige ambiente testing, aplicação local e DB moves_codex_erp_payables_* descartável.');
}

$pdo = Connection::getInstance();
$suffix = bin2hex(random_bytes(5));
$password = 'MovesERP-PayableTest-2026!';
$seed = static function (string $name, string $slug) use ($pdo, $password): array {
    $pdo->prepare("INSERT INTO users(name,email,password,status,role) VALUES(?,?,?,'active','user')")
        ->execute([$name, $slug . '@example.test', password_hash($password, PASSWORD_DEFAULT)]);
    $userId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO talk_tenants(name,slug,status) VALUES(?,?,'active')")->execute([$name, $slug]);
    $tenantId = (int) $pdo->lastInsertId();
    (new CompanyService($pdo))->ensureRoles($tenantId);
    $role = $pdo->prepare("SELECT id FROM platform_roles WHERE tenant_id=? AND slug='owner'");
    $role->execute([$tenantId]);
    $pdo->prepare("INSERT INTO talk_tenant_users(tenant_id,user_id,role,role_id,status,is_default) VALUES(?,?,'admin',?,'active',1)")->execute([$tenantId,$userId,(int)$role->fetchColumn()]);
    $pdo->prepare("INSERT INTO platform_tenant_products(tenant_id,product,enabled) VALUES(?,'erp',1)")->execute([$tenantId]);
    $tax = substr(hash('sha256', $slug), 0, 10);
    $pdo->prepare("INSERT INTO erp_administrators(tenant_id,legal_name,trade_name,tax_id,status) VALUES(?,?,?,?,'active')")->execute([$tenantId,$name,$name,'P'.$tax]);
    $administratorId = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO erp_scope_grants(user_id,capability,scope_type,scope_id) VALUES(?,'erp.access','administrator',?)")->execute([$userId,$administratorId]);
    $pdo->prepare("INSERT INTO erp_condominiums(administrator_id,legal_name,trade_name,tax_id,status) VALUES(?,?,?,?,'active')")->execute([$administratorId,'Residencial '.$name,'Residencial '.$name,'C'.$tax]);
    return ['user_id'=>$userId,'tenant_id'=>$tenantId,'administrator_id'=>$administratorId,'condominium_id'=>(int)$pdo->lastInsertId(),'email'=>$slug.'@example.test'];
};
$a = $seed('Administradora Pagar A', 'payables-a-' . $suffix);
$b = $seed('Administradora Pagar B', 'payables-b-' . $suffix);
$foundation = static function (array $tenant, string $name) use ($pdo): array {
    $pdo->prepare("INSERT INTO erp_people(administrator_id,entity_type,full_name,status,created_by_user_id) VALUES(?,'organization',?,'active',?)")->execute([$tenant['administrator_id'],$name,$tenant['user_id']]);
    $personId=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO erp_supplier_categories(administrator_id,name,slug) VALUES(?,'Manutenção','manutencao-".$tenant['administrator_id']."')")->execute([$tenant['administrator_id']]);
    $category=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO erp_suppliers(administrator_id,person_id,category_id,status,created_by_user_id) VALUES(?,?,?,'active',?)")->execute([$tenant['administrator_id'],$personId,$category,$tenant['user_id']]);
    $supplierId=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO erp_supplier_condominiums(administrator_id,supplier_id,condominium_id,starts_at,status,created_by_user_id) VALUES(?,?,?,CURRENT_DATE,'active',?)")->execute([$tenant['administrator_id'],$supplierId,$tenant['condominium_id'],$tenant['user_id']]);
    $pdo->prepare("INSERT INTO erp_accounting_plans(administrator_id,condominium_id,name,status,created_by_user_id,updated_by_user_id) VALUES(?,?,?,'active',?,?)")->execute([$tenant['administrator_id'],$tenant['condominium_id'],'Plano '.$name,$tenant['user_id'],$tenant['user_id']]);
    $plan=(int)$pdo->lastInsertId();
    $account=$pdo->prepare("INSERT INTO erp_accounting_accounts(administrator_id,plan_id,code,name,nature,account_type,level,status,created_by_user_id,updated_by_user_id) VALUES(?,?,?,?,?,'analytic',1,'active',?,?)");
    $account->execute([$tenant['administrator_id'],$plan,'2.1','Obrigações a pagar','liability',$tenant['user_id'],$tenant['user_id']]);$liability=(int)$pdo->lastInsertId();
    $account->execute([$tenant['administrator_id'],$plan,'4.1','Manutenção','expense',$tenant['user_id'],$tenant['user_id']]);$expense=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO erp_accounting_periods(administrator_id,condominium_id,period_year,period_month,status,created_by_user_id,updated_by_user_id) VALUES(?,?,?,?,'open',?,?)")->execute([$tenant['administrator_id'],$tenant['condominium_id'],(int)date('Y'),(int)date('n'),$tenant['user_id'],$tenant['user_id']]);
    return ['supplier_id'=>$supplierId,'liability_id'=>$liability,'expense_id'=>$expense,'period_id'=>(int)$pdo->lastInsertId()];
};
$aData=$foundation($a,'Fornecedor Homologação A');
$bData=$foundation($b,'Fornecedor Homologação B');
$checks=0;
$check=static function(bool $ok,string $message)use(&$checks):void{if(!$ok)throw new RuntimeException('FAIL: '.$message);++$checks;echo 'PASS: '.$message.PHP_EOL;};
$csrf=static function(string $body):string{if(!preg_match('/name="_token"\s+value="([^"]+)"/',$body,$m))throw new RuntimeException('Token CSRF ausente.');return html_entity_decode($m[1],ENT_QUOTES|ENT_HTML5);};
$client=static function()use($base):Closure{$curl=curl_init();if(!$curl instanceof CurlHandle)throw new RuntimeException('cURL indisponível.');$cookie=tempnam(sys_get_temp_dir(),'moves-payable-http-');curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_PROXY=>'',CURLOPT_TIMEOUT=>15]);return static function(string $path,?array $data=null)use($curl,$base):array{curl_setopt($curl,CURLOPT_URL,$base.$path);if($data===null){curl_setopt($curl,CURLOPT_HTTPGET,true);curl_setopt($curl,CURLOPT_POST,false);}else{curl_setopt($curl,CURLOPT_POST,true);curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($data));}$raw=curl_exec($curl);if(!is_string($raw))throw new RuntimeException('HTTP falhou: '.curl_error($curl));$size=(int)curl_getinfo($curl,CURLINFO_HEADER_SIZE);$headers=substr($raw,0,$size);preg_match('/^Location:\s*(.+)$/mi',$headers,$m);return ['status'=>(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE),'location'=>trim($m[1]??''),'body'=>substr($raw,$size)];};};
$login=static function(array $tenant)use($client,$csrf,$check):Closure{$request=$client();$page=$request('/login');$response=$request('/login',['email'=>$tenant['email'],'password'=>'MovesERP-PayableTest-2026!','_token'=>$csrf($page['body'])]);if($page['status']!==200||$response['status']!==302||!str_ends_with($response['location'],'/day'))throw new RuntimeException('Login E2E: GET '.$page['status'].', POST '.$response['status'].', destino '.$response['location'].', resposta '.substr(strip_tags($response['body']),0,240));$check(true,'login '.$tenant['email']);return $request;};
$requestA=$login($a);$empty=$requestA('/erp/payables');
$check($empty['status']===200&&str_contains($empty['body'],'Nenhuma obrigação registrada')&&str_contains($empty['body'],'Navegação do ERP')&&str_contains($empty['body'],'erp.css'),'listagem vazia, shell, sidebar e CSS carregam');
$form=$requestA('/erp/payables/new');$check($form['status']===200&&str_contains($form['body'],'Adicionar parcela')&&str_contains($form['body'],'erp-payables.js'),'formulário e JS carregam');$token=$csrf($form['body']);
$before=(int)$pdo->query('SELECT COUNT(*) FROM erp_payables')->fetchColumn();
$invalidCsrf=$requestA('/erp/payables',['_token'=>'invalid']);$check($invalidCsrf['status']===302&&(int)$pdo->query('SELECT COUNT(*) FROM erp_payables')->fetchColumn()===$before,'CSRF inválido não grava obrigação');
$data=['_token'=>$token,'supplier_id'=>$aData['supplier_id'],'condominium_id'=>$a['condominium_id'],'liability_account_id'=>$aData['liability_id'],'expense_account_id'=>$aData['expense_id'],'description'=>'Manutenção preventiva de homologação','total_amount'=>'100.50','installments'=>['period_id'=>[$aData['period_id'],$aData['period_id']],'due_date'=>[date('Y-m-d',strtotime('+7 days')),date('Y-m-d',strtotime('+37 days'))],'amount'=>['50.25','50.25']]];
$bad=$data;$bad['total_amount']='100.51';$check($requestA('/erp/payables',$bad)['status']===302&&(int)$pdo->query('SELECT COUNT(*) FROM erp_payables')->fetchColumn()===$before,'soma divergente é recusada');
$cross=$data;$cross['supplier_id']=$bData['supplier_id'];$check($requestA('/erp/payables',$cross)['status']===302&&(int)$pdo->query('SELECT COUNT(*) FROM erp_payables')->fetchColumn()===$before,'fornecedor de tenant B é recusado');
$created=$requestA('/erp/payables',$data);preg_match('~/erp/payables/(\d+)~',$created['location'],$m);$id=(int)($m[1]??0);$check($created['status']===302&&$id>0,'cria obrigação com duas parcelas por HTTP');
$row=$pdo->prepare('SELECT * FROM erp_payables WHERE id=?');$row->execute([$id]);$stored=$row->fetch(PDO::FETCH_ASSOC);$check(is_array($stored)&&(int)$stored['administrator_id']===$a['administrator_id']&&(int)$stored['supplier_id']===$aData['supplier_id']&&number_format((float)$stored['total_amount'],2,'.','')==='100.50','obrigação persiste no tenant e condomínio corretos');
$detail=$requestA('/erp/payables/'.$id);$check($detail['status']===200&&str_contains($detail['body'],'Fornecedor Homologação A')&&str_contains($detail['body'],'50,25')&&str_contains($detail['body'],'erp.payable.created'),'detalhe, parcelas e auditoria são consultáveis');
$list=$requestA('/erp/payables?'.http_build_query(['q'=>'Manutenção preventiva']));$check($list['status']===200&&str_contains($list['body'],'Manutenção preventiva de homologação'),'busca encontra obrigação');
$requestB=$login($b);$check($requestB('/erp/payables/'.$id)['status']===404,'tenant B recebe 404 ao acessar detalhe de A');$check(!str_contains($requestB('/erp/payables')['body'],'Manutenção preventiva de homologação'),'tenant B não lista obrigação de A');
$badFk=false;try{$pdo->prepare('INSERT INTO erp_payables(administrator_id,condominium_id,supplier_id,plan_id,liability_account_id,expense_account_id,description,total_amount,created_by_user_id) VALUES(?,?,?,?,?,?,?,?,?)')->execute([$a['administrator_id'],$b['condominium_id'],$aData['supplier_id'],1,$aData['liability_id'],$aData['expense_id'],'FK cruzada','1.00',$a['user_id']]);}catch(PDOException){$badFk=true;}$check($badFk,'FK composta recusa condomínio de tenant diferente');
$guest=$client();$check($guest('/erp/payables')['status']===302,'visitante é direcionado ao login');
echo "OK: $checks verificações HTTP ERP Contas a Pagar.\n";
