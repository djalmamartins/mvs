<?php $this->layout('layouts/default', compact('title', 'productName', 'activeProduct', 'currentPage')); ?>
<link rel="stylesheet" href="/themes/admin/css/support.css?v=20260919d">
<section class="support-workspace-page">
<?php $this->insert('components/support-page-header', ['heading'=>'Configurações do Support','description'=>'Capacidades do produto separadas entre recursos disponíveis e dependências futuras.']); ?>
<div class="support-settings-grid">
<?php
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$sections = [
    ['Geral', 'Identidade e comportamento do produto.', 'icon-options-outline', 'Disponível', ['Shell autenticado', 'Navegação e busca do Support', 'Preferências globais herdadas do Moves']],
    ['Base de conhecimento', 'Conteúdo disponível agora.', 'icon-library-outline', 'Disponível', ['Artigos, categorias, produtos e tags', 'Rascunhos, revisões e lixeira', 'SEO, mídia e publicação']],
    ['Atendimento', 'Operação aguardando backend próprio.', 'icon-headset-outline', 'Planejado', ['Caixa de entrada multicanal', 'Atribuição e estados de chamados', 'Histórico completo da conversa']],
    ['SLA', 'Métricas dependentes dos chamados.', 'icon-timer-outline', 'Planejado', ['Calendário de atendimento', 'Primeira resposta e resolução', 'Pausas e violações auditáveis']],
    ['Notificações', 'Alertas vinculados a eventos reais.', 'icon-notifications-outline', 'Planejado', ['Eventos do atendimento', 'Preferências por destinatário', 'Canais de envio integrados']],
    ['Permissões', 'Controle central da plataforma.', 'icon-shield-checkmark-outline', 'Disponível', ['Autenticação obrigatória', 'Perfis existentes preservados', 'Acesso administrativo protegido']],
];
foreach ($sections as [$heading, $description, $icon, $status, $items]): ?>
    <section class="support-panel support-settings-card">
        <header><div><h2><?= $escape($heading) ?></h2><p><?= $escape($description) ?></p></div><i class="<?= $escape($icon) ?>"></i></header>
        <span class="support-state <?= $status === 'Disponível' ? 'success' : '' ?>"><?= $escape($status) ?></span>
        <ul><?php foreach ($items as $item): ?><li><?= $escape($item) ?></li><?php endforeach; ?></ul>
    </section>
<?php endforeach; ?>
</div>
<p class="support-info"><i class="icon-alert-circle-outline" aria-hidden="true"></i> Esta tela não grava opções fictícias. Novos controles serão habilitados apenas quando possuírem regras, armazenamento e auditoria próprios.</p>
</section>
