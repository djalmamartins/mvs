<?php

/** Moves | Public services page. */

$this->layout('layouts/default', [
    'title' => $title,
    'description' => $description,
    'canonical' => $canonical ?? null,
    'robots' => $robots ?? 'noindex, nofollow',
    'currentPage' => 'services',
]);
?>

<section class="page-hero section-shell services-hero">
    <p class="eyebrow">NOSSOS SERVIÇOS</p>
    <h1>Tecnologia para cada<br><span class="brand-gradient">próximo passo.</span></h1>
    <p class="page-lead">Da presença digital às ferramentas do dia a dia. Conheça as quatro frentes de trabalho da Moves.</p>
</section>

<nav class="section-shell service-jump" aria-label="Explorar serviços">
    <a href="#criacao-de-sites">Criação de Sites ↓</a>
    <a href="#sistemas-web">Sistemas Web ↓</a>
    <a href="#aplicativos">Aplicativos ↓</a>
    <a href="#hospedagem">Hospedagem ↓</a>
</nav>

<section class="section-shell service-fold" id="criacao-de-sites">
    <div class="service-fold-photo card-1" role="img" aria-label="Imagem ilustrativa de Criação de Sites"><span class="service-fold-number" aria-hidden="true">01</span><span class="service-fold-label" aria-hidden="true">Criação de Sites</span></div>
    <div class="service-fold-copy"><p class="eyebrow">01 / CRIAÇÃO DE SITES</p><h2>Sua marca merece uma<br><span class="brand-gradient">presença à altura.</span></h2><p>Sites institucionais e landing pages que apresentam seu negócio com clareza e ajudam o visitante a dar o próximo passo.</p><ul><li>Arquitetura de informação e organização do conteúdo</li><li>Design responsivo para celular, tablet e computador</li><li>Desenvolvimento com atenção a desempenho e acessibilidade</li><li>Estrutura técnica para indexação em buscadores</li></ul><a class="text-link" href="/contato?servico=criacao-de-sites">Conversar sobre criação de sites ↗</a></div>
</section>

<section class="section-shell service-fold" id="sistemas-web">
    <div class="service-fold-photo card-2" role="img" aria-label="Imagem ilustrativa de Sistemas Web"><span class="service-fold-number" aria-hidden="true">02</span><span class="service-fold-label" aria-hidden="true">Sistemas Web</span></div>
    <div class="service-fold-copy"><p class="eyebrow">02 / SISTEMAS WEB</p><h2>Menos barreiras.<br><span class="brand-gradient">Mais possibilidades.</span></h2><p>Transformamos processos do seu negócio em ferramentas práticas, com regras e integrações planejadas para sua operação.</p><ul><li>Mapeamento de processos e perfis de acesso</li><li>Painéis, cadastros e fluxos sob medida</li><li>Integração com APIs e ferramentas existentes</li><li>Documentação e planejamento de evolução</li></ul><a class="text-link" href="/contato?servico=sistemas-web">Conversar sobre sistemas web ↗</a></div>
</section>

<section class="section-shell service-fold" id="aplicativos">
    <div class="service-fold-photo card-apps" role="img" aria-label="Imagem ilustrativa de Aplicativos"><span class="service-fold-number" aria-hidden="true">03</span><span class="service-fold-label" aria-hidden="true">Aplicativos</span></div>
    <div class="service-fold-copy"><p class="eyebrow">03 / APLICATIVOS</p><h2>Sua ideia, mais perto<br><span class="brand-gradient">das pessoas.</span></h2><p>Planejamos experiências para dispositivos móveis com foco nos fluxos que o seu público precisa realizar.</p><ul><li>Definição do produto e público</li><li>Protótipos dos principais fluxos</li><li>Desenvolvimento e integrações conforme o escopo</li><li>Preparação para distribuição e evolução</li></ul><a class="text-link" href="/contato?servico=aplicativos">Conversar sobre aplicativos ↗</a></div>
</section>

<section class="section-shell service-fold" id="hospedagem">
    <div class="service-fold-photo card-hosting" role="img" aria-label="Imagem ilustrativa de Hospedagem"><span class="service-fold-number" aria-hidden="true">04</span><span class="service-fold-label" aria-hidden="true">Hospedagem</span></div>
    <div class="service-fold-copy"><p class="eyebrow">04 / HOSPEDAGEM</p><h2>Uma base para sua<br><span class="brand-gradient">presença digital.</span></h2><p>Planejamos o ambiente de hospedagem de acordo com as necessidades técnicas do seu site e da sua operação.</p><ul><li>Análise dos requisitos do site</li><li>Orientação sobre domínio e certificado</li><li>Configuração de ambiente conforme a proposta</li><li>Definição de rotinas de atualização e cópias de segurança</li></ul><a class="text-link" href="/contato?servico=hospedagem">Conversar sobre hospedagem ↗</a></div>
</section>

<section class="cta"><div class="cta-inner"><div><p class="eyebrow">SEU PRÓXIMO MOVIMENTO</p><h2>Vamos tirar sua<br>ideia do papel?</h2><p>Conte seu desafio. Construímos o próximo passo juntos.</p></div><a class="button button-primary large" href="/contato">Conversar sobre meu projeto ↗</a></div></section>
