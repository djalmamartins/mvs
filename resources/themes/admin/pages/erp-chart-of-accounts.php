<?php declare(strict_types=1); $this->layout('layouts/default', compact('title','productName','activeProduct','currentPage')); ?>
<main class="erp-page erp-page-chart-index">
    <?php $this->insert('components/erp-page-header', ['heading'=>'Plano de Contas','description'=>'Estruturas de classificação financeira por condomínio.','kicker'=>'MOVES · ERP','actionHref'=>'/erp/chart-of-accounts/new','actionLabel'=>'Criar plano']); ?>
    <form method="get" action="/erp/chart-of-accounts" class="erp-filter-bar" role="search">
        <label class="erp-search-field"><span class="sr-only">Pesquisar plano ou condomínio</span><input type="search" name="q" value="<?= $this->e($query) ?>" placeholder="Plano ou condomínio"></label>
        <button class="erp-button" type="submit">Pesquisar</button>
    </form>
    <section class="erp-panel erp-data-panel">
        <header class="erp-panel-head"><div><h2>Planos cadastrados</h2><p><?= count($plans) ?> plano(s) · um por condomínio</p></div></header>
        <?php if($plans===[]): ?><div class="erp-empty-state"><strong>Nenhum plano de contas encontrado.</strong><p>Crie um plano para um condomínio ativo e adicione contas conforme a operação exigir. O Moves não cria categorias financeiras pré-definidas.</p><?php if($condominiums!==[]): ?><a class="erp-button erp-button-primary" href="/erp/chart-of-accounts/new">Criar plano</a><?php else: ?><a class="erp-button erp-button-primary" href="/erp/condominiums/new">Cadastrar condomínio</a><?php endif; ?></div>
        <?php else: ?><div class="erp-table-wrap"><table class="erp-responsive-directory-table"><thead><tr><th>Plano</th><th>Condomínio</th><th>Contas</th><th>Situação</th><th></th></tr></thead><tbody><?php foreach($plans as $plan): ?><tr><td data-label="Plano"><a class="erp-record-link" href="/erp/chart-of-accounts/<?= (int)$plan['id'] ?>"><strong><?= $this->e((string)$plan['name']) ?></strong></a></td><td data-label="Condomínio"><?= $this->e((string)($plan['trade_name']?:$plan['legal_name'])) ?></td><td data-label="Contas"><?= (int)$plan['account_count'] ?></td><td data-label="Situação"><span class="erp-status <?= $plan['status']==='active'?'ativo':'' ?>"><?= $plan['status']==='active'?'Ativo':'Inativo' ?></span></td><td data-label="Ação"><a class="erp-record-link" href="/erp/chart-of-accounts/<?= (int)$plan['id'] ?>">Visualizar</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
        <footer class="erp-table-foot">Naturezas Moves: ativo, passivo, patrimônio líquido, receita e despesa. Plano independente de competência e contas bancárias.</footer>
    </section>
</main>
