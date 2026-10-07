<?php
declare(strict_types=1);
$this->layout('layouts/default',compact('title','productName','activeProduct','currentPage'));
$editing = is_array($unit);
$heading = $editing ? 'Editar unidade '.(string)$unit['code'] : 'Cadastrar unidade';
$action = $editing ? '/erp/units/'.(int)$unit['id'].'/edit' : '/erp/units';
?>
<main class="erp-page">
    <nav class="erp-breadcrumb"><a href="/erp/units">Unidades</a><span aria-hidden="true">/</span><span><?= $editing ? $this->e((string)$unit['code']) : 'Novo cadastro' ?></span></nav>
    <?php $this->insert('components/erp-page-header',['heading'=>$heading,'description'=>$editing?'Atualize somente a identificação e o complemento desta unidade.':'A unidade será vinculada a um condomínio da administradora atual.','kicker'=>'MOVES · ERP']); ?>
    <form method="post" action="<?= $this->e($action) ?>" class="erp-panel erp-form-panel">
        <?= $this->csrf() ?>
        <section>
            <h2><?= $editing ? 'Identificação da unidade' : 'Dados da unidade' ?></h2>
            <?php if($editing): ?>
                <p class="erp-form-help">Condomínio, bloco e situação são preservados. Proprietários, moradores, fração ideal e histórico temporal não são alterados por esta edição.</p>
                <div class="erp-form-grid">
                    <label class="wide">Condomínio<input type="text" value="<?= $this->e((string)($unit['condominium_name']?:$unit['condominium_legal_name'])) ?>" readonly></label>
                    <label>Bloco ou torre<input type="text" value="<?= $this->e((string)($unit['block_name']?:'—')) ?>" readonly></label>
                    <label>Identificador da unidade<input name="code" maxlength="40" required value="<?= $this->e((string)$unit['code']) ?>"></label>
                    <label class="wide">Complemento (opcional)<input name="complement" maxlength="120" value="<?= $this->e((string)($unit['complement']??'')) ?>" placeholder="Ex.: cobertura, fundos"></label>
                </div>
            <?php else: ?>
                <div class="erp-form-grid">
                    <label class="wide">Condomínio<select name="condominium_id" required><option value="">Selecione um condomínio</option><?php foreach($condominiums as $condominium): ?><option value="<?= (int)$condominium['id'] ?>"><?= $this->e((string)($condominium['trade_name']?:$condominium['legal_name'])) ?></option><?php endforeach; ?></select></label>
                    <label>Bloco ou torre (opcional)<input name="block" maxlength="120" placeholder="Ex.: Torre A"></label>
                    <label>Identificador da unidade<input name="code" maxlength="40" required placeholder="Ex.: 301"></label>
                    <label class="wide">Complemento (opcional)<input name="complement" maxlength="120" placeholder="Ex.: cobertura, fundos"></label>
                </div>
            <?php endif; ?>
        </section>
        <footer class="erp-form-actions"><a class="erp-button" href="<?= $editing ? '/erp/units/'.(int)$unit['id'] : '/erp/units' ?>">Cancelar</a><button class="erp-button erp-button-primary" type="submit"><?= $editing ? 'Salvar alterações' : 'Salvar unidade' ?></button></footer>
    </form>
</main>
