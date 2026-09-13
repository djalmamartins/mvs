# Migração funcional do Studio legado

O Studio em `/Applications/XAMPP/xamppfiles/htdocs/erp` é a referência funcional. O Moves em `/Applications/XAMPP/xamppfiles/htdocs/mvs` permanece a referência de arquitetura, autenticação, autorização, persistência e segurança. Código legado não é copiado diretamente.

## Estado dos módulos

| Ordem | Módulo | Estado | Decisão |
| --- | --- | --- | --- |
| 1 | Dashboard | [CONCLUÍDO] | Migrar e melhorar |
| 2 | Usuários | [PLANEJADO] | Auditar funções úteis além da gestão atual |
| 3 | Configurações | [PLANEJADO] | Auditar e migrar somente opções úteis |
| 4 | Versões | [PLANEJADO] | Comparar histórico e operação segura |
| 5 | Log | [PLANEJADO] | Comparar estados e ações sem revelar dados sensíveis |
| 6 | Mídia | [PLANEJADO] | Migrar biblioteca, crop e associações com Storage |
| 7 | Artigos | [PLANEJADO] | Migrar taxonomia, SEO, mídia e publicação |
| 8 | Páginas | [PLANEJADO] | Migrar SEO, imagem, template e ordenação |
| 9 | Destaques | [PLANEJADO] | Migrar período, CTA, imagem e ordenação |
| 10 | Depoimentos | [PLANEJADO] | Migrar empresa, cargo, foto e ordenação |
| 11 | FAQ | [PLANEJADO] | Migrar categoria, resposta e ordenação |
| 12 | Propostas | [PLANEJADO] | Migrar histórico, observações, resposta e conversão |
| 13 | Notificações | [PLANEJADO] | Migrar destinatário, origem, badge e ações |
| 14 | Relatórios | [PLANEJADO] | Migrar somente métricas reais e exportações úteis |

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
