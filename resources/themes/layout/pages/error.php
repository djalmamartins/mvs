<?php
declare(strict_types=1);
$messages=[
403=>['title'=>'Acesso negado','message'=>'Você não tem permissão para acessar esta página.'],
404=>['title'=>'Página não encontrada','message'=>'A página solicitada não foi encontrada.'],
405=>['title'=>'Método não permitido','message'=>'O método utilizado não é permitido para esta página.'],
500=>['title'=>'Erro interno','message'=>'Ocorreu um erro ao carregar esta tela do laboratório.'],
];
$error=$messages[$code]??$messages[500];
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= htmlspecialchars($error['title']) ?> · MVS Layout</title><link rel="stylesheet" href="/themes/layout/css/layout.css"></head><body>
<section class="auth"><div class="auth-main"><div class="auth-card"><a class="brand" href="/layout/login"><div class="mark">M</div>MVS</a><div class="auth-kicker">Erro <?= (int)$code ?></div><h1><?= htmlspecialchars($error['title']) ?></h1><p class="lead"><?= htmlspecialchars($error['message']) ?></p>
<?php if(($debug??false)&&isset($exception)): ?><div class="notice"><b>Erro original:</b><br><?= $this->e($exception->getMessage()) ?><br><small><?= $this->e($exception->getFile()) ?>:<?= (int)$exception->getLine() ?></small></div><?php endif; ?>
<a class="btn primary auth-error-link" href="/layout/login">Voltar ao login</a></div></div><aside class="visual"><div class="visual-copy"><span class="eyebrow">MOVES PLATFORM</span><h2>Laboratório visual.</h2><p>Esta tela preserva o erro original para facilitar a correção durante o desenvolvimento.</p></div></aside></section></body></html>