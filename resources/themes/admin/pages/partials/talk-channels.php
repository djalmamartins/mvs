<?php declare(strict_types=1); $channels=$channels??[];$channelQueues=$channelQueues??[]; ?>
<section class="talk-channels" data-talk-channels data-csrf="<?= $this->e((string)($_SESSION['_token']??'')) ?>">
  <div class="talk-channel-toolbar">
    <div><h2>Canais WhatsApp</h2><p>Gerencie números e sessões independentes desta administradora.</p></div>
    <button class="studio-btn studio-btn-primary" type="button" data-channel-new><i class="icon-add-outline"></i> Novo canal</button>
  </div>
  <?php if(isset($_GET['error'])): ?><div class="support-info is-error"><?= $this->e((string)$_GET['error']) ?></div><?php endif; ?>
  <div class="support-panel talk-channel-table"><div class="support-table"><table><thead><tr><th>Canal</th><th>Número conectado</th><th>Conexão</th><th>Fila padrão</th><th>Última conexão</th><th>Ações</th></tr></thead><tbody>
  <?php foreach($channels as $channel): $id=(int)$channel['id']; ?><tr data-channel-row="<?= $id ?>" data-status-url="/talk/channels/<?= $id ?>/status">
    <td><strong><?= $this->e((string)$channel['name']) ?></strong><small><?= $this->e((string)($channel['display_name']??$channel['external_id'])) ?></small></td>
    <td data-channel-phone><?= $this->e((string)($channel['phone_number']?:'—')) ?></td>
    <td><span class="support-state <?= ($channel['connection_status']??'')==='connected'?'success':'' ?>" data-channel-state><?= $this->e((string)$channel['connection_status']) ?></span></td>
    <td><?= $this->e((string)($channel['default_queue_name']??'Sem fila padrão')) ?></td><td><?= $this->e((string)($channel['last_connected_at']??'—')) ?></td>
    <td><div class="talk-channel-actions"><button class="studio-btn" type="button" data-channel-edit='<?= $this->e(json_encode($channel,JSON_UNESCAPED_UNICODE|JSON_HEX_APOS|JSON_HEX_QUOT)) ?>'>Editar</button><button class="studio-btn" type="button" data-channel-connect="<?= $id ?>">Conectar</button><button class="studio-btn" type="button" data-channel-logout="<?= $id ?>">Desconectar</button></div><small class="talk-channel-error" data-channel-error><?= $this->e((string)($channel['last_error']??'')) ?></small></td>
  </tr><?php endforeach; ?>
  <?php if(!$channels): ?><tr><td colspan="6"><div class="support-empty-state"><h2>Nenhum canal cadastrado</h2><p>Crie o primeiro número WhatsApp desta administradora.</p></div></td></tr><?php endif; ?>
  </tbody></table></div></div>
  <div class="talk-channel-qr" data-channel-qr hidden><button type="button" data-qr-close aria-label="Fechar">×</button><img alt="QR Code do canal"><div><strong>Conectar este canal</strong><p>Leia o QR em WhatsApp → Aparelhos conectados. O código pertence somente ao canal selecionado.</p></div></div>
  <dialog class="talk-channel-dialog" data-channel-dialog><form method="post" action="/talk/channels"><?= $this->csrf() ?><input type="hidden" name="id" value="0"><header><h2 data-dialog-title>Novo canal</h2><button type="button" data-dialog-close>×</button></header><label>Nome do canal<input name="name" maxlength="120" required placeholder="Ex.: WhatsApp Financeiro"></label><label>Nome de exibição<input name="display_name" maxlength="160" placeholder="Opcional"></label><label>Fila padrão<select name="default_queue_id"><option value="0">Sem fila padrão</option><?php foreach($channelQueues as $queue): ?><option value="<?= (int)$queue['id'] ?>"><?= $this->e((string)$queue['name']) ?></option><?php endforeach; ?></select></label><label>Status<select name="status"><option value="active">Ativo</option><option value="inactive">Inativo</option></select></label><footer><button class="studio-btn" type="button" data-dialog-close>Cancelar</button><button class="studio-btn studio-btn-primary" type="submit">Salvar canal</button></footer></form></dialog>
</section>
