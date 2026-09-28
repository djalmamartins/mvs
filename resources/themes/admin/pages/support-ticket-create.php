<?php declare(strict_types=1);
$this->layout('layouts/default',['title'=>$title,'productName'=>'Suporte','activeProduct'=>'support','currentPage'=>'tickets']);
?>
<section class="studio-page">
<header class="studio-page-header"><div><h1>Novo chamado</h1><p>Registre uma solicitação para acompanhamento pelo Support.</p></div><a class="studio-btn" href="/support/my-tickets">Cancelar</a></header>
<section class="studio-card">
<form method="post" action="/support/tickets" class="studio-form"><?= $this->csrf() ?>
<div class="studio-field"><label for="subject">Assunto</label><input id="subject" name="subject" type="text" maxlength="190" required autocomplete="off"></div>
<div class="studio-field"><label for="priority">Prioridade</label><select id="priority" name="priority" required><option value="normal">Normal</option><option value="low">Baixa</option><option value="high">Alta</option><option value="urgent">Urgente</option></select></div>
<div class="studio-field"><label for="description">Descrição</label><textarea id="description" name="description" rows="8" required></textarea></div>
<div class="studio-form-actions"><a class="studio-btn" href="/support/my-tickets">Cancelar</a><button class="studio-btn studio-btn-primary" type="submit">Criar chamado</button></div>
</form>
</section>
</section>
