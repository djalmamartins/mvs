<?php

/** Moves | Customer Area navigation. */

$groups = [
    'Visão geral' => [['label'=>'Início','icon'=>'⌂','href'=>'/app','key'=>'dashboard']],
    'Serviços' => [
        ['label'=>'Meus serviços','icon'=>'◇'],['label'=>'Projetos','icon'=>'▧'],
        ['label'=>'Hospedagem','icon'=>'≋'],['label'=>'Domínios','icon'=>'◎'],
    ],
    'Atendimento' => [['label'=>'Chamados','icon'=>'◌']],
    'Financeiro' => [['label'=>'Faturas','icon'=>'▤']],
    'Arquivos' => [['label'=>'Documentos','icon'=>'□']],
    'Conta' => [['label'=>'Notificações','icon'=>'•'],['label'=>'Meu perfil','icon'=>'○','href'=>'/app/profile','key'=>'profile']],
];
?>
<aside class="customer-sidebar" id="customer-sidebar" aria-label="Navegação da Área do Cliente">
    <a class="customer-brand" href="/app"><img src="/themes/site/images/brand/moves-logo.svg" alt="MOVES" width="116" height="17"><small>Área do Cliente</small></a>
    <nav><?php foreach($groups as $group=>$items):?><section class="customer-nav-group"><h2><?= $this->e($group) ?></h2><?php foreach($items as $item):?><?php if(isset($item['href'])):?><a href="<?= $this->e($item['href']) ?>"<?= ($currentPage??null)===$item['key']?' class="active" aria-current="page"':'' ?>><span aria-hidden="true"><?= $this->e($item['icon']) ?></span><?= $this->e($item['label']) ?></a><?php else:?><span class="customer-nav-disabled" aria-disabled="true" title="Disponível quando houver dados vinculados à sua conta"><span aria-hidden="true"><?= $this->e($item['icon']) ?></span><?= $this->e($item['label']) ?><small>Em breve</small></span><?php endif;?><?php endforeach;?></section><?php endforeach;?></nav>
</aside><button class="customer-backdrop" type="button" aria-label="Fechar menu" tabindex="-1"></button>
