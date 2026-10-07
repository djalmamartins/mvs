<?php

declare(strict_types=1);

$this->layout('layouts/default', compact('title', 'productName', 'activeProduct', 'currentPage'));
$companyName = (string) (($company['name'] ?? '') ?: 'Administradora atual');
?>
<main class="erp-page">
    <?php $this->insert('components/erp-page-header', ['heading'=>'Visão geral','description'=>'Central financeira e operacional da administradora.','kicker'=>'MOVES · ERP']); ?>

    <section class="erp-context-bar" aria-label="Contexto do ERP">
        <div><span class="erp-context-label">Administradora</span><strong><?= $this->e($companyName) ?></strong></div>
        <form method="get" action="/erp" class="erp-context-form">
            <label for="erp-condominium">Condomínio</label>
            <select id="erp-condominium" name="condominium_id">
                <option value="0">Todos os condomínios</option>
                <?php foreach ($condominiums as $condominium): ?>
                    <?php $condominiumName = (string) (($condominium['trade_name'] ?? '') ?: ($condominium['legal_name'] ?? 'Condomínio')); ?>
                    <option value="<?= (int) $condominium['id'] ?>" <?= (int) $selectedCondominiumId === (int) $condominium['id'] ? 'selected' : '' ?>><?= $this->e($condominiumName) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="erp-button" type="submit">Aplicar</button>
        </form>
    </section>

    <?php if ($demoEnabled): ?>
        <?php $this->insert('components/erp-demo-notice'); ?>
        <section class="erp-metrics" aria-label="Resumo financeiro demonstrativo">
            <?php foreach ([['Saldo disponível',$demo['balance'],'Posição demonstrativa'],['A receber',$demo['receivable'],'Títulos em aberto'],['A pagar',$demo['payable'],'Obrigações previstas'],['Inadimplência',$demo['delinquency'],'Valores vencidos']] as [$label,$value,$note]): ?>
                <article class="erp-metric"><span><?= $this->e($label) ?></span><strong><?= $this->e($value) ?></strong><small><?= $this->e($note) ?></small></article>
            <?php endforeach; ?>
        </section>

        <div class="erp-dashboard-grid">
            <section class="erp-panel erp-cashflow">
                <header class="erp-panel-head"><div><h2>Fluxo de caixa</h2><p>Entradas e saídas · <?= $this->e($demo['period_label']) ?></p></div><form class="erp-period-form" method="get" action="/erp"><input type="hidden" name="condominium_id" value="<?= (int) $selectedCondominiumId ?>"><label class="sr-only" for="erp-period">Período do fluxo de caixa</label><select id="erp-period" name="period"><option value="7" <?= $selectedPeriod==='7'?'selected':'' ?>>7 dias</option><option value="30" <?= $selectedPeriod==='30'?'selected':'' ?>>30 dias</option><option value="month" <?= $selectedPeriod==='month'?'selected':'' ?>>Este mês</option></select><button class="erp-button" type="submit">Aplicar</button></form></header>
                <div class="erp-cashflow-summary"><div><span>Entradas</span><strong><?= $this->e($demo['inflows']) ?></strong></div><div><span>Saídas</span><strong><?= $this->e($demo['outflows']) ?></strong></div><div><span>Saldo projetado</span><strong><?= $this->e($demo['projected']) ?></strong></div></div>
                <div class="erp-chart erp-chart-count-<?= count($demo['cashflow']) ?>" role="img" aria-label="Gráfico demonstrativo de entradas e saídas em <?= $this->e($demo['period_label']) ?>">
                    <?php $maxCashflow = max(array_map(static fn (array $point): int => max($point['in'], $point['out']), $demo['cashflow'])); ?>
                    <?php foreach ($demo['cashflow'] as $point): ?>
                        <?php $inHeight = max(10, min(100, (int) (round(($point['in'] / $maxCashflow) * 10) * 10))); $outHeight = max(10, min(100, (int) (round(($point['out'] / $maxCashflow) * 10) * 10))); ?>
                        <div class="erp-chart-day"><div class="erp-chart-bars"><span class="erp-bar-in erp-chart-height-<?= $inHeight ?>" title="Entrada demonstrativa"></span><span class="erp-bar-out erp-chart-height-<?= $outHeight ?>" title="Saída demonstrativa"></span></div><small><?= $this->e($point['label']) ?></small></div>
                    <?php endforeach; ?>
                </div>
                <footer class="erp-chart-legend"><span><i class="erp-legend-in"></i>Entradas</span><span><i class="erp-legend-out"></i>Saídas</span><strong><?= (int) $demo['due_today'] ?> vencimentos hoje · exemplo</strong></footer>
            </section>
            <section class="erp-panel erp-activity">
                <header class="erp-panel-head"><div><h2>Atividade recente</h2><p>Eventos ilustrativos do produto</p></div></header>
                <ul><?php foreach ($demo['activity'] as $activity): ?><li><span class="erp-activity-dot"></span><div><strong><?= $this->e($activity['title']) ?></strong><small><?= $this->e($activity['detail']) ?></small></div><time><?= $this->e($activity['time']) ?></time></li><?php endforeach; ?></ul>
            </section>
        </div>
    <?php else: ?>
        <section class="erp-panel erp-empty-dashboard"><strong>Os dados financeiros ainda não estão conectados.</strong><p>Os cadastros reais do tenant aparecem nas áreas de condomínios. Nenhum valor financeiro fictício foi carregado.</p><a class="erp-button erp-button-primary" href="/erp/condominiums">Ver condomínios</a></section>
    <?php endif; ?>

    <section class="erp-section-block">
        <header class="erp-section-head"><div><p class="erp-eyebrow">ACESSO RÁPIDO</p><h2>Rotinas do ERP</h2><p>Abra uma área para consultar a operação.</p></div></header>
        <div class="erp-shortcuts">
        <?php foreach ([['Plano de Contas','/erp/chart-of-accounts','Classifique contas por condomínio','icon-list-outline'],['Competências','/erp/periods','Organize os meses abertos de cada condomínio','icon-calendar-outline'],['Contas a pagar','/erp/payables','Acompanhe vencimentos e fornecedores','icon-card-outline'],['Contas a receber','/erp/receivables','Consulte valores por unidade','icon-wallet-outline'],['Cobranças','/erp/billing','Acompanhe a competência e o status','icon-receipt-outline'],['Condomínios','/erp/condominiums','Cadastros da administradora','icon-business'],['Pessoas','/erp/people','Consulte vínculos e contatos','icon-people-outline'],['Unidades','/erp/units','Propriedade e ocupação','icon-home-outline'],['Contas bancárias','/erp/bank-accounts','Contas por condomínio','icon-business-outline'],['Conciliação','/erp/reconciliation','Compare movimentos e lançamentos','icon-git-compare-outline']] as [$label,$href,$description,$icon]): ?>
                <a href="<?= $this->e($href) ?>"><i class="<?= $this->e($icon) ?>"></i><span><strong><?= $this->e($label) ?></strong><small><?= $this->e($description) ?></small></span><b aria-hidden="true">›</b></a>
            <?php endforeach; ?>
        </div>
    </section>
</main>
