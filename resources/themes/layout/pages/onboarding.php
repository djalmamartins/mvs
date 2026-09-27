<?php
/** @var int $step @var array<string,mixed> $data */
$this->layout('layouts/default', ['title' => $title]);
$account = is_array($data['account'] ?? null) ? $data['account'] : [];
$company = is_array($data['company'] ?? null) ? $data['company'] : [];
$products = is_array($data['products'] ?? null) ? $data['products'] : ['talk'];
?>
<main class="auth-page"><section class="auth-card" style="max-width:720px">
<p class="auth-eyebrow">CONFIGURAÇÃO INICIAL · ETAPA <?= $step ?> DE 4</p><h1><?= $this->e($title) ?></h1>
<form method="post" action="/onboarding"><?= $this->csrf() ?><input type="hidden" name="step" value="<?= $step ?>">
<?php if ($step === 1): ?><label>Seu nome<input name="name" required value="<?= $this->e((string)($account['name']??'')) ?>"></label><label>E-mail<input type="email" name="email" required value="<?= $this->e((string)($account['email']??'')) ?>"></label><label>Senha<input type="password" name="password" minlength="10" required autocomplete="new-password"></label>
<?php elseif ($step === 2): ?><label>Nome da administradora<input name="company_name" required value="<?= $this->e((string)($company['name']??'')) ?>"></label><label>Razão social<input name="legal_name" required value="<?= $this->e((string)($company['legal_name']??'')) ?>"></label><label>CNPJ<input name="tax_id" inputmode="numeric" value="<?= $this->e((string)($company['tax_id']??'')) ?>"></label><label>E-mail comercial<input type="email" name="company_email" value="<?= $this->e((string)($company['email']??'')) ?>"></label>
<?php elseif ($step === 3): ?><fieldset><legend>Produtos habilitados</legend><?php foreach (['talk'=>'Talk','erp'=>'ERP','support'=>'Support','cms'=>'CMS','studio'=>'Studio'] as $key=>$label): ?><label><input type="checkbox" name="products[]" value="<?= $key ?>" <?= in_array($key,$products,true)?'checked':'' ?>> <?= $label ?></label><?php endforeach; ?></fieldset>
<?php else: ?><h2>Revisar e criar</h2><p><strong><?= $this->e((string)($company['name']??'')) ?></strong><br><?= $this->e((string)($account['email']??'')) ?></p><p>Produtos: <?= $this->e(implode(', ', $products)) ?></p><?php endif; ?>
<button type="submit" class="primary-btn"><?= $step===4?'Criar administradora':'Continuar' ?></button></form>
</section></main>
