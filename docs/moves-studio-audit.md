# Auditoria funcional do Studio legado

Auditoria realizada em 12/09/2026 sobre a interface autenticada em `http://localhost/erp/studio/` e sobre controller, modelos, views, assets e scripts em `/Applications/XAMPP/xamppfiles/htdocs/erp/`. O legado foi usado como referência funcional e de UX; sua arquitetura não foi copiada.

## Inventário e fluxos

- **Shell:** sidebar agrupada, item ativo, topbar com tema, ajuda, site, notificações e conta, busca global, seletor de sistemas e biblioteca de mídia modal. No mobile a navegação é off-canvas.
- **Dashboard:** KPIs reais de páginas, artigos/publicados, usuários/online e acessos; atalhos editoriais; feeds condicionais de logs e propostas. Também há uma variante operacional, acoplada a visitas, demandas, cotações e condomínios.
- **Relatórios:** total e série de acessos, conteúdos populares e atividade de usuários; gráficos dependentes dos dados registrados. Não há justificativa para reproduzi-los antes de existir telemetria editorial no Moves.
- **Notificações:** contagem/lista assíncrona, leitura, ações individuais, marcar todas, composição por destinatários/perfis e fila de e-mail. Relaciona-se a propostas, auditoria, suporte e sistema.
- **Propostas:** busca, filtro e paginação; estados `new`, `contacted`, `qualified`, `proposal_sent`, `won`, `lost`, `archived`; responsável, detalhe, respostas, geração/visualização de PDF e envio por fila de e-mail.
- **Páginas:** listagem e formulário de criar/editar/excluir; título, slug, corpo, autor, capa, publicação e estados `draft`, `post`, `trash`.
- **Artigos:** busca/paginação, categorias, autor, título, resumo, slug, editor, capa, vídeo, data/agendamento e estados `draft`, `post`, `trash`; upload integrado ao editor.
- **Mídia:** upload, grade, preview, busca, tipo, orientação, uso, período, ordenação e paginação; seleção em lote; exclusão bloqueada quando o arquivo está referenciado; picker para formulários e editor.
- **Destaques:** CRUD de banners com título, corpo, imagem, alinhamento e estado; utiliza a biblioteca de mídia. O Moves adotará o nome Destaques.
- **Depoimentos:** CRUD com nome/título, vínculo/empresa, texto, avaliação, imagem e estado `draft`/`published`.
- **FAQ:** abas de perguntas, composição e canais; ordenação, vínculo com suporte, criação e exclusão com proteção de canais em uso.
- **Usuários:** busca, paginação, criação, perfil, endereço, foto, estado, papel e overrides de permissão; impede escalada sobre papéis superiores e a remoção do último desenvolvedor.
- **Configurações:** site, identidade, domínio, endereço, temas, uploads, imagem, e-mail, pagamentos, redes, idioma/fuso e acesso a módulos. Contém campos secretos e, por isso, o Moves manterá uma superfície mínima nesta etapa.
- **Versões:** histórico por produto e publicação de releases semânticos. A versão nova será apenas informativa, sem release, update ou rollback.
- **Logs:** banco de incidentes com nível, status, canal, período, datas, busca, ordenação, ocorrências e paginação; resolver, ignorar, reabrir e excluir em lote. O Moves lerá somente o Logger JSONL atual.
- **Fora do menu principal:** busca global, agenda, suporte/artigos de ajuda, tickets, eventos, resultados, pastas/categorias e associações. São funções do ecossistema ERP/Help Desk, não do CMS Moves atual.

## Dados, arquitetura e segurança observados

O controller legado `Studio.php` reúne cerca de dois mil linhas e mistura coordenação HTTP, SQL, upload, e-mail, PDF e renderização. Modelos e tabelas sustentam posts, pages, categories, slides, briefs/depoimentos, FAQ/canais, proposals/responses, notifications, access/online e `app_log`. Há componentes visuais úteis — cabeçalho de página, cards, filtros, tabela responsiva, badges, empty state e paginação — mas eles devem ser reconstruídos no tema atual.

Foram confirmadas checagens de permissão por módulo, CSRF em mutações recentes, prepared statements em fluxos sensíveis, validação de status e contenção de caminho em mídia/PDF. Riscos arquiteturais: controller monolítico; configurações de muitos domínios e segredos na mesma tela; acesso direto frequente a superglobais; HTML rico persistido exige política explícita de sanitização; varredura recursiva de mídia e procura de referências podem custar caro; exclusões físicas e múltiplos estados aumentam o risco operacional. Não se concluiu vulnerabilidade apenas pela presença desses padrões.

## Matriz antigo × Moves

| Módulo | Função antiga | Estado antigo | Existe no Moves? | Decisão | Prioridade |
| --- | --- | --- | --- | --- | --- |
| Shell | Sidebar, topbar, responsividade | Completo | Sim | Preservar e lapidar | P0 |
| Dashboard | KPIs, atalhos e feeds | Completo | Parcial | Consolidar só com dados reais | P0 |
| Usuários | Lista, perfis, papéis e overrides | Completo | Gestão segura de contas e papéis simples | Migrado sem endereço, foto ou overrides | P0 |
| Configurações | Site, temas, e-mail, pagamentos e integrações | Amplo | Identidade, SEO, contato e redes | Migrado sem segredos ou infraestrutura | P0 |
| Diagnóstico | Estado técnico | Integrado | Sim | Preservar | P0 |
| Versões | Publicação e histórico por produto | Completo | Inventário e histórico seguro | Migrado sem deploy, tag ou rollback | P0 |
| Log | Incidentes, filtros e mutações em lote | Completo | Leitura sanitizada e triagem separada | Migrado sem alterar o JSONL original | P0 |
| Mídia | Biblioteca, upload, filtros, uso e picker | Completo | Biblioteca, recorte e vínculos | Migrado com Storage e original preservado | P1 |
| Artigos | CRUD, categorias, autor, SEO/capa/agendamento | Completo | Taxonomia, SEO, mídia e publicação | Migrado e integrado a `/conteudo` | P1 |
| Páginas | CRUD editorial e publicação | Completo | SEO, imagem, template e ordem | Migrado com templates em allowlist | P2 |
| Destaques | CRUD de slides/banners | Completo | Período, CTA, imagem e ordem | Migrado com validação de período/URL | P2 |
| Depoimentos | CRUD, foto, avaliação e status | Completo | Empresa, cargo, foto e ordem | Migrado sem avaliação sem consumidor | P2 |
| FAQ | Canais, perguntas, ordem e suporte | Completo | Não | Simplificar categorias/publicação | P2 |
| Propostas | Pipeline, responsável, PDF e e-mail | Completo | Formulário público sem pipeline | Reconstruir a partir de `/contato` | P3 |
| Notificações | Central, badge, destinatários e e-mail | Completo | Não | Reconstruir após propostas | P3 |
| Relatórios | Acessos, conteúdo e usuários | Parcial/real | Não | Adiar até existir telemetria confiável | P4 |
| Busca global | Conteúdo, usuários e propostas | Completo | Não | Adiar até haver módulos pesquisáveis | P4 |
| Agenda/suporte/tickets | Operação e Help Desk | Completo | Não | Descartar do escopo CMS | Fora |
| Eventos/resultados/associações | Módulos verticais antigos | Legado paralelo | Não | Descartar do produto atual | Fora |

## Decisões para o Moves

Nesta rodada foram concluídos Usuários, Configurações, Versões e Log com o shell visual atual. Permanecem oficiais `Auth`, `Access`, `Session`, `Csrf`, `LoginThrottle`, `PermissionMiddleware`, `Logger`, `Validator`, `Response`, CSP e headers. Não foram importados o RBAC complexo, campos secretos, updater, exclusão do histórico técnico ou módulos verticais do ERP. A triagem de logs vive em tabela separada e referencia somente fingerprints que ainda existem no JSONL sanitizado.
