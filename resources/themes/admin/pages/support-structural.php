<?php $this->layout('layouts/default', compact('title', 'productName', 'activeProduct', 'currentPage')); ?>
<link rel="stylesheet" href="/themes/admin/css/support.css?v=20260919c">
<section class="support-workspace-page">
<?php $this->insert('components/support-page-header', ['heading'=>$title,'description'=>'Workspace de atendimento do Moves Support.']); ?>
<div class="support-toolbar-static"><span><i class="icon-search-outline"></i> Buscar chamados</span><span>Todos os status</span><span>Todas as prioridades</span></div>
<section class="support-panel support-structural-panel"><?php $this->insert('components/support-empty-state', ['icon'=>$icon,'heading'=>'Nenhum dado disponível','description'=>$description,'note'=>'A interface está preparada sem simular chamados, SLA ou integrações inexistentes.']); ?></section>
</section>
