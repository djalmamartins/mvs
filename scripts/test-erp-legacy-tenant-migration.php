<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Moves\Boot\Connection;
use Moves\Boot\Environment;
use Moves\Core\Config;

Environment::load(dirname(__DIR__));
$database=(string)Config::get('DB_DATABASE','');
if(Config::environment()!=='testing'||!preg_match('/^moves_codex_erp_people_[a-zA-Z0-9_]+$/',$database)){
    throw new RuntimeException('Teste de backfill requer APP_ENV=testing e DB_DATABASE moves_codex_erp_people_*.');
}
$pdo=Connection::getInstance();$suffix=bin2hex(random_bytes(5));$password=password_hash('ERP-backfill-test-password',PASSWORD_DEFAULT);
$checkCount=0;$check=static function(bool $ok,string $label)use(&$checkCount):void{if(!$ok)throw new RuntimeException('FAIL: '.$label);++$checkCount;echo 'PASS: '.$label.PHP_EOL;};
$seed=static function(string $label,bool $erpEnabled)use($pdo,$suffix,$password):array{
    $slug='erp-bf-'.substr(hash('sha256',$label.$suffix),0,20);$email=$slug.'@example.test';
    $pdo->prepare("INSERT INTO users(name,email,password,status,role) VALUES(?,?,?,'active','user')")->execute([$label,$email,$password]);$userId=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO talk_tenants(name,slug,status) VALUES(?,?,'active')")->execute([$label,$slug]);$tenantId=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO platform_roles(tenant_id,slug,name,is_system) VALUES(?,'owner','Proprietário',1)")->execute([$tenantId]);$roleId=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO talk_tenant_users(tenant_id,user_id,role,role_id,status,is_default) VALUES(?,?,'owner',?,'active',1)")->execute([$tenantId,$userId,$roleId]);
    $pdo->prepare("INSERT INTO platform_tenant_products(tenant_id,product,enabled) VALUES(?,'erp',?)")->execute([$tenantId,$erpEnabled?1:0]);
    return ['tenant_id'=>$tenantId,'user_id'=>$userId,'role_id'=>$roleId];
};
$legacy=$seed('Legacy ERP tenant',true);$disabled=$seed('ERP disabled tenant',false);
$sql=file_get_contents(dirname(__DIR__).'/database/migrations/20261006_028_backfill_erp_administrators.sql');if(!is_string($sql)||trim($sql)==='')throw new RuntimeException('Migration de backfill vazia ou indisponível.');$pdo->exec($sql);
$administrator=$pdo->prepare('SELECT id,legal_name,tax_id FROM erp_administrators WHERE tenant_id=? AND status=\'active\'');$administrator->execute([$legacy['tenant_id']]);$row=$administrator->fetch(PDO::FETCH_ASSOC);
$check(is_array($row),'tenant legado ativo com ERP ganha cadastro de administradora');$check(is_array($row)&&$row['legal_name']==='Legacy ERP tenant'&&$row['tax_id']==='TENANT-'.$legacy['tenant_id'],'cadastro usa identidade disponível e tax ID interno quando ausente');
$permission=$pdo->prepare("SELECT COUNT(*) FROM platform_role_permissions rp JOIN platform_permissions p ON p.id=rp.permission_id WHERE rp.role_id=? AND p.slug='erp.access'");$permission->execute([$legacy['role_id']]);$check((int)$permission->fetchColumn()===1,'owner recebe erp.access pela permissão de papel');
$scope=$pdo->prepare("SELECT COUNT(DISTINCT capability) FROM erp_scope_grants WHERE user_id=? AND scope_type='administrator' AND scope_id=? AND revoked_at IS NULL");$scope->execute([$legacy['user_id'],(int)$row['id']]);$check((int)$scope->fetchColumn()===2,'owner recebe somente os escopos cadastrais padrão da administradora');
$administrator->execute([$disabled['tenant_id']]);$check($administrator->fetch(PDO::FETCH_ASSOC)===false,'tenant sem ERP habilitado não ganha administradora');
$permission->execute([$disabled['role_id']]);$check((int)$permission->fetchColumn()===0,'tenant sem ERP habilitado não ganha erp.access');
$pdo->exec($sql);$administrator->execute([$legacy['tenant_id']]);$check(is_array($administrator->fetch(PDO::FETCH_ASSOC)),'backfill pode ser reaplicado sem duplicar perfil');$scope->execute([$legacy['user_id'],(int)$row['id']]);$check((int)$scope->fetchColumn()===2,'reaplicação não duplica nem amplia escopos');
echo "OK: $checkCount verificações do backfill ERP legado.\n";
