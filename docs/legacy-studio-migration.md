# Migração funcional do Studio legado

O Studio em `/Applications/XAMPP/xamppfiles/htdocs/erp` é a referência funcional. O Moves em `/Applications/XAMPP/xamppfiles/htdocs/mvs` permanece a referência de arquitetura, autenticação, autorização, persistência e segurança. Código legado não é copiado diretamente.

## Estado dos módulos

| Ordem | Módulo | Estado | Decisão |
| --- | --- | --- | --- |
| 1 | Dashboard | [CONCLUÍDO] | Migrar e melhorar |
| 2 | Usuários | [CONCLUÍDO] | Gestão segura simplificada |
| 3 | Configurações | [CONCLUÍDO] | Opções públicas úteis, sem segredos |
| 4 | Versões | [CONCLUÍDO] | Histórico semântico sem deploy automático |
| 5 | Log | [CONCLUÍDO] | Triagem por estado sem alterar o log bruto |
| 6 | Mídia | [CONCLUÍDO] | Biblioteca, crop derivado e associações com Storage |
| 7 | Artigos | [CONCLUÍDO] | Taxonomia, SEO, mídia e publicação |
| 8 | Páginas | [CONCLUÍDO] | SEO, imagem, template e ordenação |
| 9 | Destaques | [CONCLUÍDO] | Período, CTA, imagem e ordenação |
| 10 | Depoimentos | [CONCLUÍDO] | Empresa, cargo, foto e ordenação |
| 11 | FAQ | [CONCLUÍDO] | Categoria, resposta e ordenação |
| 12 | Propostas | [CONCLUÍDO] | Histórico, observações, resposta e conversão |
| 13 | Notificações | [CONCLUÍDO] | Destinatário, origem, badge e ações |
| 14 | Relatórios | [CONCLUÍDO] | Métricas reais e exportação CSV |

## Dashboard — [CONCLUÍDO]

### Antigo

- Rotas: `GET /studio/` e `GET /studio/dash`.
- Controller: `Source\Controllers\Studio\Studio::dash()`.
- Models/tabelas consultados: Page, Post, Category, User, Access e Online; o card de propostas apenas abria o módulo.
- Tela: saudação, páginas, artigos, artigos publicados, usuários, usuários online, visualizações dos sete registros recentes e atalhos para criação/configuração.
- Permissão: `dashboard.view` no guard monolítico do legado.
- Problemas: controller acumulava muitos módulos; calculava categorias, posts e usuários recentes que não eram renderizados; logout legado usava GET; métricas de presença dependiam de infraestrutura que não existe no Moves.

### Moves

- Rota: `GET /admin`, protegida por `AuthMiddleware` e `PermissionMiddleware`.
- Controller: `Moves\Controllers\StudioController::dashboard()`.
- Fontes reais: `studio_content`, `studio_media`, `users`, `proposals`, `Diagnostics` e `Logger` sanitizado.
- Funções migradas: contagem de páginas; artigos totais e publicados; usuários; propostas; atalhos de criação e configuração.
- Melhoria preservada: saúde técnica real e atividade recente sanitizada.
- Adiado para Relatórios: visualizações e presença online, pois exigem coleta própria, consentimento/retenção e migration dedicada. Nenhum número fictício substitui essas métricas.
- Descartado: consultas de categorias, posts recentes e usuários recentes que não tinham representação na tela antiga.
- Banco/migrations: nenhuma alteração; as tabelas necessárias já existem no Moves.
- Visual: sidebar branca, estado ativo roxo, cards claros, radius de 6 px, tipografia base de 16 px e controles com borda/foco visíveis.

## Usuários — [CONCLUÍDO]

- Legado: busca, filtros por estado/perfil/data, ordenação, CSV, ações em lote, cadastro, perfil, endereço, foto e overrides de permissão.
- Migrado: busca por nome/e-mail, filtros por status e papel, indicadores, paginação, criação, edição, troca opcional de senha, ativação, desativação e exclusão.
- Segurança: `users.manage`, CSRF em toda mutação, senha com hash, proteção do ID 1, proteção contra desativar/excluir a própria sessão e tratamento de vínculos antes da exclusão.
- Simplificado: papéis `admin` e `user` seguem o `Access` atual. Overrides, endereço, documento e foto foram adiados até existir necessidade de produto e modelo dedicado.
- Descartado: logout GET e alteração hierárquica baseada em níveis opacos do ERP.
- Rotas: `GET /admin/users`, `GET /admin/users/create`, `GET /admin/users/edit/{id}`, `POST /admin/users/save`, `POST /admin/users/action`.

## Configurações — [CONCLUÍDO]

- Legado: identidade, domínio, contato, SMTP, aparência, diretórios, redes e bloqueio global de módulos.
- Migrado: nome da aplicação, título e descrição pública, e-mail/telefone e URLs institucionais.
- Segurança: `settings.manage`, CSRF, allowlist fixa, limites, sanitização, validação de e-mail e URL.
- Adiado: arquivos de marca para a fase de Mídia e configurações que ainda não possuem consumidores.
- Descartado: credenciais SMTP e caminhos de storage no banco; segredos e infraestrutura permanecem no ambiente.
- Rotas: `GET|POST /admin/settings`.

## Versões — [CONCLUÍDO]

- Legado: histórico por produto e publicação de release corrente com arquivamento da anterior.
- Migrado: inventário técnico, migrations disponíveis e histórico semântico do produto Studio com nome, notas, autor, data e estado.
- Segurança: `settings.manage`, CSRF, versão semântica, transação, unicidade e escaping.
- Diferença deliberada: registrar uma versão não altera arquivos, Composer, tags Git, a tag `1.0.0` nem executa deploy/rollback.
- Banco: migration `20260913_002_create_studio_management.sql`, tabela `studio_versions`.
- Rotas: `GET|POST /admin/versions`.

## Log — [CONCLUÍDO]

- Legado: filtros amplos, resolver, reabrir, ignorar, excluir e ações em lote sobre logs persistidos.
- Migrado: busca, nível, estado, paginação, resolver, reabrir e ignorar.
- Segurança: arquivo JSONL original permanece imutável; contexto continua sanitizado; estados usam fingerprint SHA-256; mutações exigem `users.manage` e CSRF.
- Descartado: exclusão do registro bruto e exibição de stack trace, caminho, token, sessão ou credenciais.
- Banco: mesma migration, tabela `studio_log_states`, contendo apenas fingerprint, estado, operador e data.
- Rotas: `GET|POST /admin/logs`.

## Padrão visual aplicado aos quatro módulos

Sidebar branca e off-canvas, item ativo roxo, topbar clara, fonte base de 16 px, auxiliares de ao menos 14 px, radius de 6 px, campos brancos com borda `#cbd5e1`, hover perceptível, foco com borda roxa e ring, labels visíveis, estados disabled legíveis, tabelas roláveis e ações acessíveis por teclado.

## Mídia — [CONCLUÍDO]

- Migrado: upload via `movescode/storage`, busca por nome/texto alternativo, dimensões, tamanho, origem de recorte e contagem de associações.
- Crop: coordenadas validadas no servidor geram uma nova imagem derivada e preservam o original.
- Associações: `studio_content.media_id` é chave estrangeira; mídia em uso não pode ser excluída.
- Segurança: limite de 8 MB, MIME real validado pelo Storage, raiz canônica, IDs preparados, CSRF e saída por endpoint controlado.
- Banco: migration `20260913_003_expand_studio_content.sql`.

## Artigos — [CONCLUÍDO]

- Migrado: categorias próprias, slug, resumo, corpo, mídia, vídeo, SEO, status e publicação.
- Site: `/conteudo` lê categoria, imagem e texto alternativo; o detalhe usa metadados SEO e imagem associados.
- Simplificado: HTML rico não foi liberado sem política de sanitização; o corpo continua armazenado e exibido como texto seguro.

## Páginas — [CONCLUÍDO]

- Migrado: slug, resumo, conteúdo, título/descrição SEO, imagem, templates `default`, `landing` e `wide`, status e ordenação.
- Decisão: templates são uma allowlist de apresentação; não aceitam caminhos ou PHP informados pelo usuário.

## Destaques — [CONCLUÍDO]

- Migrado: título, texto, imagem, alinhamento, CTA interno/URL válida, início, fim, status e ordem.
- Validação: período invertido é rejeitado e URLs arbitrárias fora dos formatos aceitos não são persistidas.

## Depoimentos — [CONCLUÍDO]

- Migrado: nome, depoimento, empresa, cargo, foto, status e ordem.
- Simplificado: avaliação numérica não foi trazida por não ter consumidor comprovado no site atual.

## Padrão visual da rodada editorial

Os editores usam coluna principal para texto/SEO e painel lateral para publicação, mídia e atributos específicos. Em tablet e mobile as colunas viram uma sequência única; biblioteca, formulários de crop, tabelas e ações mantêm labels e foco visíveis.

## FAQ — [CONCLUÍDO]

- Migrado: categorias próprias, pergunta, resposta, slug, estado e ordenação.
- Segurança: categoria validada por tipo, corpo exibido como texto seguro, CSRF e prepared statements.

## Propostas — [CONCLUÍDO]

- Migrado: busca, pipeline, detalhe, responsável, respostas, observações, histórico e conversão para estado ganho.
- Auditoria: cada mudança persiste ação, nota, operador e data em `proposal_history`; a mensagem original não é sobrescrita.
- Simplificado: não há envio automático de e-mail ou geração de PDF até existir infraestrutura de fila e transporte configurada.

## Notificações — [CONCLUÍDO]

- Migrado: notificações globais ou por destinatário, origem, referência, ação interna, contador não lido, marcar lida/não lida, marcar todas e excluir.
- Segurança: um usuário só altera notificações globais ou destinadas a ele; links renderizados são restritos a caminhos internos.
- Integração: novas propostas registram origem, ID e link direto ao detalhe comercial.

## Relatórios — [CONCLUÍDO]

- Migrado: totais/publicados por módulo, distribuição real do pipeline e propostas mensais dos últimos 12 meses.
- Exportação: CSV administrativo gerado sob autenticação com os mesmos totais persistidos.
- Descartado: visitas, conversões estimadas, usuários online e gráficos fictícios sem telemetria confiável.

## Banco da rodada operacional

A migration `20260913_004_complete_studio_operations.sql` adiciona responsável/resposta/conversão às propostas, histórico imutável de ações e metadados de destinatário/origem às notificações.
