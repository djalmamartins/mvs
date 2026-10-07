<?php declare(strict_types=1); $this->layout('layouts/default', compact('title','productName','activeProduct','currentPage')); ?>
<main class="erp-page erp-page-periods">
    <?php $this->insert('components/erp-page-header', ['heading'=>'Competências','description'=>'Meses civis abertos para os condomínios da administradora.','kicker'=>'MOVES · ERP','actionHref'=>'/erp/periods/new','actionLabel'=>'Abrir competência']); ?>
    <form method="get" action="/erp/periods" class="erp-filter-bar" role="search">
        <label><span class="sr-only">Condomínio</span><select name="condominium"><option value="">Todos os condomínios</option><?php foreach($condominiums as $condominium): ?><option value="<?= (int)$condominium['id'] ?>" <?= $filters['condominium_id']===(int)$condominium['id']?'selected':'' ?>><?= $this->e((string)($condominium['trade_name']?:$condominium['legal_name'])) ?></option><?php endforeach; ?></select></label>
        <label><span class="sr-only">Ano</span><input type="number" name="year" min="2000" max="2100" value="<?= $filters['year']===null?'':(int)$filters['year'] ?>" placeholder="Ano"></label>
        <label><span class="sr-only">Situação</span><select name="status"><option value="">Todas as situações</option><option value="open" <?= $filters['status']==='open'?'selected':'' ?>>Aberta</option></select></label>
        <button class="erp-button" type="submit">Filtrar</button>
    </form>
    <section class="erp-panel erp-data-panel">
        <header class="erp-panel-head"><div><h2>Competências cadastradas</h2><p><?= count($periods) ?> registro(s) · estado aberto</p></div></header>
        <?php if($periods===[]): ?><div class="erp-empty-state"><strong>Nenhuma competência cadastrada.</strong><p>Abra um mês para começar a organizar a rotina financeira do condomínio.</p><a class="erp-button erp-button-primary" href="/erp/periods/new">Abrir competência</a></div>
        <?php else: ?><div class="erp-table-wrap"><table class="erp-responsive-directory-table"><thead><tr><th>Competência</th><th>Condomínio</th><th>Situação</th><th>Criada em</th><th></th></tr></thead><tbody><?php foreach($periods as $period): ?><tr><td data-label="Competência"><strong><?= sprintf('%02d/%04d',(int)$period['period_month'],(int)$period['period_year']) ?></strong></td><td data-label="Condomínio"><?= $this->e((string)($period['trade_name']?:$period['legal_name'])) ?></td><td data-label="Situação"><span class="erp-status ativo">Aberta</span></td><td data-label="Criada em"><?= $this->e((string)$period['created_at']) ?></td><td data-label="Ação"><a class="erp-record-link" href="/erp/periods/<?= (int)$period['id'] ?>">Visualizar</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
        <footer class="erp-table-foot">Nesta etapa, as competências são abertas; fechamento e efeitos financeiros serão tratados posteriormente.</footer>
    </section>
</main>
