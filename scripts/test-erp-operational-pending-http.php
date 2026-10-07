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
$condominiumNames = ['Condomínio numérico '.$suffix,'Condomínio alfanumérico '.$suffix,'Condomínio sem CNPJ '.$suffix,'Condomínio em processo '.$suffix,'Condomínio complexo '.$suffix];
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
$dayPage = $requestA('/day');
$check($dayPage['status']===200 && !str_contains($dayPage['body'],'Internal Server Error'), 'fluxo começa no Meu Dia sem erro 500');
$alphaCondominiumList = $requestA('/erp/condominiums?q=' . rawurlencode($alphaCnpj));
$formattedAlphaCnpj = substr($alphaCnpj, 0, 2).'.'.substr($alphaCnpj, 2, 3).'.'.substr($alphaCnpj, 5, 3).'/'.substr($alphaCnpj, 8, 4).'-'.substr($alphaCnpj, 12, 2);
$check($alphaCondominiumList['status']===200 && str_contains($alphaCondominiumList['body'], $formattedAlphaCnpj), 'listagem de condomínios mostra CNPJ alfanumérico formatado');
$unitForm = $requestA('/erp/units/new');
$unitToken = $csrf($unitForm['body']);
$unitsByCondo = [];
foreach ($condominiumIds as $index => $condominiumId) {
    $count = $index === 0 ? 10 : ($index === 1 ? 20 : ($index === 4 ? 3 : 1));
    $unitsByCondo[$condominiumId] = [];
    for ($number = 1; $number <= $count; $number++) {
        $code = sprintf('%02d%02d', $index + 1, $number);
        $created = $requestA('/erp/units', ['_token'=>$unitToken,'condominium_id'=>(string)$condominiumId,'block'=>'Torre A','code'=>$code]);
        if ($created['status'] !== 302 || !preg_match('~/erp/units/(\d+)~', $created['location'], $match)) {
            throw new RuntimeException('Falha ao criar unidade HTTP no condomínio '.$condominiumId.': '.$created['status'].' '.$created['location']);
        }
        $unitsByCondo[$condominiumId][] = (int)$match[1];
    }
    $filter = $requestA('/erp/units?condominium='.$condominiumId);
    $check($filter['status']===200 && str_contains($filter['body'],'Torre A'), 'estrutura do condomínio '.($index+1).' criada pela UI HTTP');
}
$unitCount = (int)$pdo->query('SELECT COUNT(*) FROM erp_units WHERE condominium_id IN ('.implode(',',array_map('intval',$condominiumIds)).')')->fetchColumn();
$blockCount = (int)$pdo->query('SELECT COUNT(*) FROM erp_blocks WHERE condominium_id IN ('.implode(',',array_map('intval',$condominiumIds)).')')->fetchColumn();
$check($unitCount===35 && $blockCount===5, 'MySQL persiste 35 unidades e cinco blocos com escopo por condomínio');
$peopleForm = $requestA('/erp/people/new');
$personPayload = ['_token'=>$csrf($peopleForm['body']),'entity_type'=>'person','full_name'=>'Proprietária multiunidade '.$suffix,'document_type'=>'cpf','document_number'=>'52998224725','create_link'=>'1','condominium_id'=>(string)$condominiumIds[0],'unit_id'=>(string)$unitsByCondo[$condominiumIds[0]][0],'role'=>'owner','ownership_fraction_pct'=>'60','starts_at'=>date('Y-m-d')];
$personResponse = $requestA('/erp/people',$personPayload);
preg_match('~/erp/people/(\d+)~',$personResponse['location'],$personMatch);
$personId = (int)($personMatch[1]??0);
$check($personResponse['status']===302 && $personId>0, 'pessoa PF vinculada à primeira unidade do condomínio 1');
$personDetail = $requestA('/erp/people/'.$personId);
$multiLink = $requestA('/erp/people/'.$personId.'/links',['_token'=>$csrf($personDetail['body']),'condominium_id'=>(string)$condominiumIds[0],'unit_id'=>(string)$unitsByCondo[$condominiumIds[0]][1],'role'=>'owner','starts_at'=>date('Y-m-d')]);
$check($multiLink['status']===302, 'proprietária mantém vínculo multiunidade sem duplicação');
$coproForm = $requestA('/erp/people/new');
$copro = $requestA('/erp/people',['_token'=>$csrf($coproForm['body']),'entity_type'=>'person','full_name'=>'Coproprietária '.$suffix,'create_link'=>'1','condominium_id'=>(string)$condominiumIds[0],'unit_id'=>(string)$unitsByCondo[$condominiumIds[0]][0],'role'=>'owner','ownership_fraction_pct'=>'40','starts_at'=>date('Y-m-d')]);
preg_match('~/erp/people/(\d+)~',$copro['location'],$coproMatch);
$coproId=(int)($coproMatch[1]??0);
$check($copro['status']===302 && $coproId>0 && str_contains($requestA('/erp/units/'.$unitsByCondo[$condominiumIds[0]][0])['body'],'Coproprietária '.$suffix), 'unidade admite e exibe coproprietária PF');
$coproDetail=$requestA('/erp/people/'.$coproId);
foreach ([2,3] as $index) {
    $link=$requestA('/erp/people/'.$coproId.'/links',['_token'=>$csrf($coproDetail['body']),'condominium_id'=>(string)$condominiumIds[$index],'unit_id'=>(string)$unitsByCondo[$condominiumIds[$index]][0],'role'=>'owner','starts_at'=>date('Y-m-d')]);
    $check($link['status']===302, 'pessoa vinculada ao condomínio sem CNPJ '.($index+1));
}
$complexOwnerForm=$requestA('/erp/people/new');
$complexOwner=$requestA('/erp/people',['_token'=>$csrf($complexOwnerForm['body']),'entity_type'=>'person','full_name'=>'Proprietário três unidades '.$suffix,'create_link'=>'1','condominium_id'=>(string)$condominiumIds[4],'unit_id'=>(string)$unitsByCondo[$condominiumIds[4]][0],'role'=>'owner','ownership_fraction_pct'=>'70','starts_at'=>date('Y-m-d')]);
preg_match('~/erp/people/(\d+)~',$complexOwner['location'],$complexOwnerMatch);
$complexOwnerId=(int)($complexOwnerMatch[1]??0);
$complexOwnerDetail=$requestA('/erp/people/'.$complexOwnerId);
$complexOwnerLinks=[];
foreach ([$unitsByCondo[$condominiumIds[4]][1],$unitsByCondo[$condominiumIds[4]][2]] as $complexUnitId) {
    $complexOwnerLinks[]=$requestA('/erp/people/'.$complexOwnerId.'/links',['_token'=>$csrf($complexOwnerDetail['body']),'condominium_id'=>(string)$condominiumIds[4],'unit_id'=>(string)$complexUnitId,'role'=>'owner','starts_at'=>date('Y-m-d')]);
}
$check($complexOwner['status']===302&&$complexOwnerId>0&&count($unitsByCondo[$condominiumIds[4]])===3&&count(array_filter($complexOwnerLinks,static fn(array $response):bool=>$response['status']===302))===2,'proprietário do condomínio complexo é vinculado às unidades 301, 302 e 303');
$complexCoproForm=$requestA('/erp/people/new');
$complexCopro=$requestA('/erp/people',['_token'=>$csrf($complexCoproForm['body']),'entity_type'=>'person','full_name'=>'Coproprietário complexo '.$suffix,'create_link'=>'1','condominium_id'=>(string)$condominiumIds[4],'unit_id'=>(string)$unitsByCondo[$condominiumIds[4]][0],'role'=>'owner','ownership_fraction_pct'=>'30','starts_at'=>date('Y-m-d')]);
$complexUnit=$requestA('/erp/units/'.$unitsByCondo[$condominiumIds[4]][0]);
$check($complexCopro['status']===302&&str_contains($complexUnit['body'],'Proprietário três unidades '.$suffix)&&str_contains($complexUnit['body'],'Coproprietário complexo '.$suffix)&&str_contains($complexUnit['body'],'70,0000%')&&str_contains($complexUnit['body'],'30,0000%'),'condomínio complexo mantém copropriedade com frações ideais de 70% e 30%');
$companyForm = $requestA('/erp/people/new');
$company = $requestA('/erp/people',['_token'=>$csrf($companyForm['body']),'entity_type'=>'organization','full_name'=>'Empresa condomínio 2 '.$suffix,'document_type'=>'cnpj','document_number'=>$alphaCnpj,'create_link'=>'1','condominium_id'=>(string)$condominiumIds[1],'unit_id'=>(string)$unitsByCondo[$condominiumIds[1]][0],'role'=>'owner','starts_at'=>date('Y-m-d')]);
preg_match('~/erp/people/(\d+)~',$company['location'],$companyMatch);
$companyId=(int)($companyMatch[1]??0);
$check($company['status']===302 && $companyId>0 && str_contains($requestA('/erp/people/'.$companyId)['body'],substr($alphaCnpj,0,2).'.'.substr($alphaCnpj,2,3).'.'.substr($alphaCnpj,5,3).'/'.substr($alphaCnpj,8,4).'-'.substr($alphaCnpj,12,2)), 'pessoa PJ recebe CNPJ alfanumérico no condomínio 2');
$supplierForm = $requestA('/erp/suppliers/new');
$supplierToken = $csrf($supplierForm['body']);
$categoryQuery = $pdo->prepare("SELECT id FROM erp_supplier_categories WHERE administrator_id=? AND slug='elevadores'");
$categoryQuery->execute([$a['administrator']]);
$categoryId=(int)$categoryQuery->fetchColumn();
$supplierPj = $requestA('/erp/suppliers',['_token'=>$supplierToken,'existing_person_id'=>(string)$companyId,'category_id'=>(string)$categoryId,'starts_at'=>date('Y-m-d'),'condominium_ids'=>[(string)$condominiumIds[1],(string)$condominiumIds[4]]]);
preg_match('~/erp/suppliers/(\d+)~',$supplierPj['location'],$supplierMatch);
$supplierId=(int)($supplierMatch[1]??0);
$check($supplierPj['status']===302 && $supplierId>0 && str_contains($requestA('/erp/suppliers/'.$supplierId)['body'],'Condomínio complexo '.$suffix), 'fornecedor PJ existente atende condomínios 2 e 5 sem duplicar pessoa');
$supplierPf = $requestA('/erp/suppliers',['_token'=>$supplierToken,'entity_type'=>'person','full_name'=>'Prestador PF '.$suffix,'document_type'=>'cpf','document_number'=>'52998224725','category_id'=>(string)$categoryId,'starts_at'=>date('Y-m-d'),'condominium_ids'=>[(string)$condominiumIds[0],(string)$condominiumIds[2],(string)$condominiumIds[3]]]);
preg_match('~/erp/suppliers/(\d+)~',$supplierPf['location'],$supplierPfMatch);
$supplierPfId=(int)($supplierPfMatch[1]??0);
$check($supplierPf['status']===302 && $supplierPfId>0 && str_contains($requestA('/erp/suppliers/'.$supplierPfId)['body'],'Condomínio sem CNPJ '.$suffix), 'fornecedor PF atende condomínios 1, 3 e 4 pela mesma UI');
$periodForm = $requestA('/erp/periods/new');
$periodToken = $csrf($periodForm['body']);
$periodIds=[];
foreach ($condominiumIds as $index => $condominiumId) {
    $period = $requestA('/erp/periods',['_token'=>$periodToken,'condominium_id'=>(string)$condominiumId,'month'=>(string)($index+1),'year'=>'2026']);
    if ($period['status']!==302 || !preg_match('~/erp/periods/(\d+)~',$period['location'],$match)) {
        throw new RuntimeException('Falha ao criar competência HTTP no condomínio '.$condominiumId.': '.$period['status'].' '.$period['location']);
    }
    $periodIds[]=(int)$match[1];
    $periodDetail=$requestA('/erp/periods/'.$periodIds[array_key_last($periodIds)]);
    $check($periodDetail['status']===200 && str_contains($periodDetail['body'],'Aberta') && str_contains($periodDetail['body'],$condominiumNames[$index]), 'competência do condomínio '.($index+1).' abre e pode ser consultada');
}
$periodCount=(int)$pdo->query('SELECT COUNT(*) FROM erp_accounting_periods WHERE administrator_id='.(int)$a['administrator'].' AND condominium_id IN ('.implode(',',array_map('intval',$condominiumIds)).') AND status=\'open\'')->fetchColumn();
$check($periodCount===5, 'competência aberta criada para cada condomínio pela UI HTTP');
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
$identityQuery=$pdo->prepare('SELECT id FROM erp_condominiums WHERE administrator_id=? AND id=?');
$identityQuery->execute([$a['administrator'],$condominiumIds[3]]);
$sameCondoId=(int)$identityQuery->fetchColumn();
$check($sameCondoId===$condominiumIds[3] && (int)$pdo->query('SELECT COUNT(*) FROM erp_units WHERE condominium_id='.(int)$condominiumIds[3])->fetchColumn()===1 && (int)$pdo->query('SELECT COUNT(*) FROM erp_accounting_periods WHERE condominium_id='.(int)$condominiumIds[3])->fetchColumn()===1 && (int)$pdo->query('SELECT COUNT(*) FROM erp_person_links WHERE administrator_id='.(int)$a['administrator'].' AND condominium_id='.(int)$condominiumIds[3])->fetchColumn()===1 && (int)$pdo->query('SELECT COUNT(*) FROM erp_supplier_condominiums WHERE administrator_id='.(int)$a['administrator'].' AND condominium_id='.(int)$condominiumIds[3])->fetchColumn()===1, 'troca posterior para CNPJ alfanumérico preserva ID, bloco, unidade, vínculo, fornecedor e competência');
$check(!str_contains($requestA('/day')['body'],'Condomínio em processo '.$suffix), 'Meu Dia deixa de exibir a tarefa após resolução automática');
$editC = $requestA('/erp/condominiums/'.$condominiumIds[2].'/edit');
$resolvedC = $requestA('/erp/condominiums/'.$condominiumIds[2].'/edit', ['_token'=>$csrf($editC['body']),'legal_name'=>'Condomínio sem CNPJ '.$suffix,'tax_id'=>$makeCnpj(false),'status'=>'active']);
$check($resolvedC['status']===302 && $pending->detail($a['tenant'],$a['administrator'],$taskC)['status']==='done', 'CNPJ numérico informado por HTTP resolve a pendência');
$requestB = $login($b);
$listB = $requestB('/erp/pending');
$check($listB['status']===200 && !str_contains($listB['body'],'Condomínio sem CNPJ '.$suffix) && !str_contains($listB['body'],'Condomínio em processo '.$suffix), 'tenant B vê somente sua própria lista de pendências');
$check($requestB('/erp/units/'.$unitsByCondo[$condominiumIds[0]][0])['status']===404, 'tenant B não visualiza unidade de tenant A');
$check($requestB('/erp/people/'.$personId)['status']===404, 'tenant B não visualiza pessoa de tenant A');
$check($requestB('/erp/suppliers/'.$supplierId)['status']===404, 'tenant B não visualiza fornecedor de tenant A');
$check($requestB('/erp/periods/'.$periodIds[0])['status']===404, 'tenant B não visualiza competência de tenant A');
$check($requestB('/erp/pending/'.$taskC)['status']===404, 'tenant B não abre pendência de tenant A por ID');
$condoB = $requestB('/erp/condominiums/'.$condominiumIds[2]);
$check($condoB['status']===404, 'tenant B não abre condomínio de tenant A por ID');
$check($requestA('/themes/admin/css/erp.css')['status']===200, 'asset CSS do ERP responde HTTP 200');
$cnpjSearch = $requestA('/erp/condominiums?q=' . urlencode($alphaCnpj));
$check($cnpjSearch['status']===200 && str_contains($cnpjSearch['body'],'Condomínio alfanumérico '.$suffix), 'busca encontra CNPJ alfanumérico normalizado');
echo "OK: $checks verificações HTTP/E2E pendências ERP e cinco cenários.\n";
