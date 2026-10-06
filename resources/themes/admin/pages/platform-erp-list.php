<?php

declare(strict_types=1);

$this->layout('layouts/default', compact('title', 'productName', 'activeProduct', 'currentPage'));
$actionHref = $section === 'condominiums' ? '/settings' : null;
$actionLabel = $section === 'condominiums' ? 'Cadastrar condomínio' : null;
?>
<main class="erp-page erp-page-<?= $this->e($section) ?>">
    <?php $this->insert('components/erp-page-header', ['heading'=>$page['title'],'description'=>$page['description'],'kicker'=>'MOVES · ERP','actionHref'=>$actionHref,'actionLabel'=>$actionLabel]); ?>
    <?php if ($demoEnabled && $section !== 'condominiums'): ?><?php $this->insert('components/erp-demo-notice'); ?><?php endif; ?>

    <?php if ($page['filters'] !== []): ?>
        <form method="get" action="/erp/<?= $this->e($section) ?>" class="erp-filter-bar" role="search">
            <label class="erp-search-field"><span class="sr-only">Buscar nesta lista</span><i class="icon-search-outline" aria-hidden="true"></i><input type="search" name="q" value="<?= $this->e($filters['q']) ?>" placeholder="Buscar nesta lista"></label>
            <?php foreach ($page['filters'] as $filter): ?>
                <?php if ($filter === 'period'): ?><label><span class="sr-only">Período</span><select name="period"><option value="7" <?= $filters['period']==='7'?'selected':'' ?>>7 dias</option><option value="30" <?= $filters['period']==='30'?'selected':'' ?>>30 dias</option><option value="month" <?= $filters['period']==='month'?'selected':'' ?>>Este mês</option></select></label>
                <?php elseif ($filter === 'condominium'): ?><label><span class="sr-only">Condomínio</span><select name="condominium"><option value="">Todos os condomínios</option><?php foreach ($filterOptions['condominiums'] as $option): ?><option value="<?= $this->e($option) ?>" <?= $filters['condominium']===$option?'selected':'' ?>><?= $this->e($option) ?></option><?php endforeach; ?></select></label>
                <?php elseif ($filter === 'supplier'): ?><label><span class="sr-only">Fornecedor</span><select name="supplier"><option value="">Todos os fornecedores</option><?php foreach ($filterOptions['suppliers'] as $option): ?><option value="<?= $this->e($option) ?>" <?= $filters['supplier']===$option?'selected':'' ?>><?= $this->e($option) ?></option><?php endforeach; ?></select></label>
                <?php elseif ($filter === 'status'): ?><label><span class="sr-only">Status</span><select name="status"><option value="">Todos os status</option><?php foreach ($filterOptions['statuses'] as $option): ?><option value="<?= $this->e($option) ?>" <?= $filters['status']===$option?'selected':'' ?>><?= $this->e($option) ?></option><?php endforeach; ?></select></label><?php endif; ?>
            <?php endforeach; ?>
            <button class="erp-button" type="submit">Filtrar</button>
            <?php if ($filters['q']!=='' || $filters['status']!=='' || $filters['condominium']!=='' || $filters['supplier']!==''): ?><a class="erp-filter-reset" href="/erp/<?= $this->e($section) ?>">Limpar</a><?php endif; ?>
        </form>
    <?php endif; ?>

    <section class="erp-panel erp-data-panel">
        <header class="erp-panel-head"><div><h2><?= $this->e($page['title']) ?></h2><p><?= count($page['rows']) ?> registro(s)<?= $demoEnabled ? ' · demonstração' : '' ?></p></div><?php if (!empty($page['action'])): ?><button class="erp-button" type="button" disabled aria-disabled="true" title="Ação disponível quando o fluxo financeiro estiver implementado"><?= $this->e($page['action']) ?></button><?php endif; ?></header>
        <?php if ($page['rows'] === []): ?>
            <div class="erp-empty-state"><strong><?= $section === 'condominiums' ? 'Nenhum condomínio cadastrado' : 'Nenhum dado disponível nesta área' ?></strong><p><?= $section === 'condominiums' ? 'Cadastre um condomínio pela área de Administração.' : 'Esta tela será conectada aos serviços reais quando o fluxo correspondente estiver disponível.' ?></p><?php if ($section === 'condominiums'): ?><a href="/settings">Abrir Administração</a><?php endif; ?></div>
        <?php else: ?>
            <div class="erp-table-wrap"><table><thead><tr><?php foreach ($page['columns'] as $column): ?><th class="<?= in_array($column['key'], ['amount','balance','bank_amount','erp_amount'], true) ? 'numeric' : '' ?>"><?= $this->e($column['label']) ?></th><?php endforeach; ?><?php if ($section==='payables'): ?><th aria-label="Ações"></th><?php endif; ?></tr></thead><tbody>
                <?php foreach ($page['rows'] as $row): ?><tr><?php foreach ($page['columns'] as $column): ?><?php $value = (string) ($row[$column['key']] ?? '—'); ?><td class="<?= in_array($column['key'], ['amount','balance','bank_amount','erp_amount'], true) ? 'numeric' : '' ?>"><?php if ($column['key']==='status' || $column['key']==='finance'): ?><?php $statusClass = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '')); ?><span class="erp-status <?= $this->e($statusClass) ?>"><?= $this->e($value) ?></span><?php elseif ($column['key']==='name' || $column['key']==='description' || $column['key']==='supplier' || $column['key']==='payer'): ?><strong><?= $this->e($value) ?></strong><?php else: ?><?= $this->e($value) ?><?php endif; ?></td><?php endforeach; ?><?php if ($section==='payables'): ?><td class="erp-row-action"><button type="button" disabled aria-label="Ações indisponíveis nesta etapa" title="Ações financeiras ainda não disponíveis">···</button></td><?php endif; ?></tr><?php endforeach; ?>
            </tbody></table></div>
            <footer class="erp-table-foot"><?= $demoEnabled && $section !== 'condominiums' ? 'Valores e vínculos ilustrativos para avaliação visual. Nenhuma operação financeira pode ser executada nesta etapa.' : 'Dados exibidos a partir dos cadastros reais disponíveis para esta administradora.' ?></footer>
        <?php endif; ?>
    </section>
</main>
