# Auditoria comparativa de lançamento — Moves

Data da auditoria: 13/09/2026  
Base nova: `1fda3c2` (`main`)  
Escopo: sistema público, Área do Cliente, Moves Studio, core, banco, operação e preparação para produção.

## Resumo executivo

**Conclusão: o Moves ainda não está pronto para um lançamento completo com Área do Cliente.** O site público e o CMS já formam um produto utilizável, e o novo código é estruturalmente mais seguro que o legado. Contudo, a Área do Cliente ainda é apenas um shell autenticado com dashboard, status e perfil; não há modelo de cliente, vínculo de propriedade, projetos/serviços do cliente, chamados, documentos, cobranças ou histórico. Também faltam garantias operacionais mínimas de produção: migrations controladas, backup/restauração testados, entrega de e-mail, proteção antispam do contato, observabilidade e pipeline de deploy.

Um lançamento **restrito ao site público + Studio interno**, sem anunciar portal do cliente, é viável após o P0 deste relatório. Para lançar o produto prometendo Área do Cliente funcional, é necessário concluir também o P1.

O legado é mais amplo, mas não é um bom alvo arquitetural. Ele reúne CMS, ERP condominial, operação de campo, helpdesk e portal de moradores. O novo Moves deve reaproveitar tarefas úteis — não telas ou regras específicas de condomínio.

## Método e evidências

A auditoria combinou:

- navegação HTTP dos dois sistemas, inclusive redirecionamentos das áreas autenticadas;
- leitura de rotas, controllers, models, templates, assets, serviços, SQL e migrations;
- inventário estrutural assistido pelo Graphify do repositório novo (444 nós no grafo);
- comparação por tarefa que o usuário consegue concluir, não apenas por existência de página;
- execução de Composer, PHPUnit, PHPStan, scripts de acesso/configuração e diagnóstico HTTP;
- revisão manual dos controles de autenticação, autorização, sessão, CSRF, saída, uploads e headers.

As áreas autenticadas do legado redirecionaram para seus logins na sessão de auditoria; suas funções foram confirmadas por rotas, controllers, models, views, migrations e capturas de referência. Não foram executadas mutações destrutivas nem envios reais.

## Produto antigo: inventário funcional

O diretório legado contém seis aplicações principais:

| Aplicação | Capacidades encontradas |
|---|---|
| Site público | Home institucional, soluções, diferenciais, histórias/depoimentos, blog, contato, proposta, termos e privacidade. |
| Studio | Dashboard, páginas, artigos/categorias, mídia, slides, depoimentos, FAQ, propostas/PDF, notificações, relatórios, usuários, configurações, versões e logs. |
| ERP | Condomínios, unidades, proprietários, usuários PF/PJ, carteiras, faturas, receitas e cadastros financeiros. |
| Operation | Meu dia, condomínios, demandas, visitas, checklists, ocorrências, planos de ação, ativos, solicitações, cotações, fornecedores, pessoas, documentos, agenda e relatórios. |
| Helpdesk | Chamados, mensagens, agenda, base de conhecimento, categorias, artigos de suporte, usuários e configurações. |
| Residents | Login, dashboard, permissões e consentimento; o código confirma uma área menor que a arquitetura conceitual do projeto. |

Capacidades invisíveis relevantes: fila de e-mail, worker periódico, agendamento, geração de PDF, impressão, migrations com comandos de verificação, exportação de schema, deploy check, logs estruturados e histórico parcial. A qualidade varia por módulo: coexistem fluxos recentes com CSRF/prepared statements e fluxos legados com logout por GET, recuperação de senha fraca e mensagens que permitem enumeração de conta.

## Moves novo: inventário funcional

| Área | Capacidades atuais |
|---|---|
| Site público | Home, serviços, projetos, sobre, conteúdo/listagem/artigo, FAQ, páginas dinâmicas, contato/proposta e login. Projetos, conteúdo, destaques, depoimentos e FAQ leem do banco. |
| Área do Cliente | Login, dashboard com estados vazios, status de sessão e perfil. Módulos futuros aparecem desabilitados. |
| Studio | Dashboard e busca; usuários, configurações, versões, logs, páginas, projetos, artigos, destaques, depoimentos, FAQ, mídia, propostas, notificações, relatórios e diagnóstico. |
| Core | Bootstrap modular, configuração por ambiente, conexão PDO, autenticação, RBAC, CSRF, validação, resposta segura, tema, logs, diagnóstico, storage e throttle de login. |
| Banco | Usuários, configurações, tentativas de login, conteúdo, taxonomias, mídia, propostas/histórico, notificações, versões e estados de log. |

O novo possui separação de superfícies mais clara (`/`, `/app`, `/admin`), menos acoplamento ao domínio antigo e controles de segurança mais uniformes. A amplitude transacional, porém, é muito menor.

## Matriz funcional antigo × novo

Legenda: `[OK]` completo para o escopo atual; `[PARCIAL]` tarefa incompleta; `[AUSENTE]` não existe; `[MELHORAR]` existe com dívida; `[DESCARTADO]` não pertence ao produto; `[FUTURO]` útil após o MVP.

| Função | Área | Antigo | Novo / estado | Melhor? | Migrar? | Prioridade | Bloqueia? | Observação |
|---|---|---:|---|---:|---:|---:|---:|---|
| Site institucional e navegação | Público | Sim | [OK] | Sim | — | P1 | Não | Identidade Moves e arquitetura responsiva são superiores. |
| Serviços | Público | Sim | [PARCIAL] | Sim | Sim | P1 | Não* | Conteúdo existe; falta fonte administrativa estruturada se o catálogo variar. |
| Portfólio/projetos | Público | Não equivalente | [OK] | Sim | — | P1 | Não | Banco e Studio já publicam projetos. |
| Blog/conteúdo | Público/CMS | Sim | [OK] | Sim | Concluído | P1 | Não | Taxonomia, SEO, mídia e publicação presentes. |
| Páginas dinâmicas | CMS | Sim | [OK] | Sim | Concluído | P1 | Não | Editor e SEO integrados. |
| Destaques/slides | CMS | Sim | [OK] | Sim | Concluído | P2 | Não | Novo modelo é mais aderente à marca. |
| Depoimentos | CMS | Sim | [OK] | Sim | Concluído | P2 | Não | Ordenação, empresa, cargo e foto. |
| FAQ/canais | CMS | Sim | [OK] | Sim | Concluído | P2 | Não | Canais e ordenação mantidos. |
| Editor rico + imagens | CMS | Sim | [OK] | Sim | Concluído | P1 | Não | Organic Editor incorporado com biblioteca. |
| SEO automático | Público/CMS | Parcial | [OK] | Sim | — | P1 | Não | Slug, meta, canonical contextual e OpenGraph. |
| Preview editorial | CMS | Sim/parcial | [MELHORAR] | Igual | Sim | P2 | Não | Falta preview autenticado consistente antes da publicação. |
| Autosave e revisão | CMS | Parcial | [AUSENTE] | Não | Sim | P2 | Não | Reduz perda de trabalho e risco editorial. |
| Busca global | Studio | Sim | [OK] | Sim | Concluído | P2 | Não | Precisa evolução de ranking/filtros. |
| Filtros/paginação/lote | Studio | Sim | [PARCIAL] | Não | Sim | P2 | Não | Cobertura varia entre módulos; mídia é mais completa. |
| Biblioteca de mídia | CMS | Sim | [OK] | Sim | Concluído | P1 | Não | Upload/crop/associação presentes. |
| Ciclo de vida da mídia | CMS | Parcial | [MELHORAR] | Igual | Sim | P1 | Sim, se houver dados privados | Falta quota, órfãos e política explícita de publicação. |
| Contato vira proposta | Público/Studio | Sim | [OK] | Sim | Concluído | P0 | Sim | Persistência existe; faltam antispam e alerta externo. |
| Histórico de proposta | Studio | Sim | [OK] | Sim | Concluído | P1 | Não | Estados e observações persistidos. |
| Proposta em PDF | Studio | Sim | [AUSENTE] | Não | Sim | P2 | Não | Útil para processo comercial, não para primeira publicação. |
| Conversão proposta → cliente/projeto | Comercial | Sim/parcial | [AUSENTE] | Não | Sim | P1 | Sim para portal | Dependência central do modelo de cliente. |
| Notificações internas | Studio | Sim | [OK] | Sim | Concluído | P1 | Não | Fila visual e ações existem. |
| E-mail/fila/agendamento | Operação | Sim | [AUSENTE] | Não | Sim | P0/P1 | Sim | Não há entrega confiável ou worker no novo. |
| Relatórios reais | Studio | Sim | [PARCIAL] | Igual | Sim | P2 | Não | Só manter métricas derivadas de dados reais. |
| CSV/exportação/impressão | Gestão | Sim | [AUSENTE] | Não | Sim | P2 | Não | Selecionar exportações úteis; evitar relatórios decorativos. |
| Usuários administrativos | Gestão | Sim | [OK] | Sim | Concluído | P1 | Não | CRUD e roles simples. |
| Permissões granulares | Gestão | Sim/misto | [PARCIAL] | Sim em consistência | Sim | P1 | Depende do time | Hoje há `admin` e `user`; suficiente apenas para operação pequena. |
| Auditoria de alterações | Gestão | Sim/parcial | [PARCIAL] | Igual | Sim | P0 | Sim | Logs existem, mas não um trilho imutável de todas as mutações. |
| Configurações do produto | Gestão | Sim | [OK] | Sim | Concluído | P1 | Não | Identidade, contato, e-mail, aparência, redes e módulos. |
| Histórico de versões | Gestão | Sim | [OK] | Sim | Concluído | P3 | Não | É administrativo, não mecanismo de deploy. |
| Diagnóstico | Operação | Sim | [OK] | Sim | Concluído | P0 | Não | Ambiente, storage e banco são verificáveis. |
| Cliente/organização | Cliente | Sim (condomínio) | [AUSENTE] | — | Reconstruir | P1 | Sim | Modelo deve ser agência/cliente, não condomínio. |
| Dashboard do cliente | Cliente | Sim | [PARCIAL] | Visualmente sim | Sim | P1 | Sim | Exibe shell/zeros sem dados pertencentes ao cliente. |
| Perfil do cliente | Cliente | Sim | [OK] | Sim | Concluído | P1 | Não | Sessão ativa é revalidada no banco. |
| Serviços contratados | Cliente | Sim equivalente | [AUSENTE] | — | Reconstruir | P1 | Sim | Contrato/plano/status e datas. |
| Projetos do cliente | Cliente | Sim equivalente | [AUSENTE] | — | Reconstruir | P1 | Sim | Vínculo e autorização por objeto são obrigatórios. |
| Chamados e mensagens | Helpdesk | Sim | [AUSENTE] | — | Reconstruir | P1 | Sim se suporte for prometido | Fluxo mínimo: abrir, responder, status, anexos. |
| Documentos privados | ERP/Operation | Sim | [AUSENTE] | — | Reconstruir | P2 | Não no MVP reduzido | Download autorizado e storage privado. |
| Faturas/pagamentos | ERP | Sim | [AUSENTE] | — | Futuro/integrar | P3 | Não | Preferir integração financeira, sem recriar banco. |
| Domínios/hospedagem | Não como produto agência | Parcial | [AUSENTE] | — | Futuro | P3 | Não | Só após validar demanda e fonte de dados. |
| Agenda/tarefas internas | Operation | Sim | [AUSENTE] | — | Futuro | P3 | Não | Pode integrar ferramenta externa inicialmente. |
| Backup e restauração | Operação | Sim/parcial | [AUSENTE] | Não | Sim | P0 | Sim | Precisa política e restauração comprovada. |
| Migrations versionadas/ledger | Operação | Sim | [MELHORAR] | Não | Sim | P0 | Sim | SQL novo é sequencial, sem ledger/rollback/idempotência. |
| Deploy check/pipeline | Operação | Sim | [PARCIAL] | Não | Sim | P0 | Sim | Há diagnóstico local, não promoção reprodutível. |
| Logs redigidos | Segurança | Misto | [OK] | Sim | — | P0 | Não | Leitor remove contexto sensível; retenção ainda precisa política. |
| Recuperação de senha | Auth | Sim, insegura | [AUSENTE] | Segurança melhor por ausência | Reconstruir | P1 | Sim para portal | Token forte, expiração e resposta não enumerável. |
| Logout | Auth | GET em áreas | [OK] | Sim | — | P0 | Não | Novo usa POST + CSRF. |
| Condomínios/unidades/proprietários | ERP | Sim | [DESCARTADO] | — | Não | — | Não | Fora do Moves atual. |
| Visitas/geolocalização/checklists | Operation | Sim | [DESCARTADO] | — | Não | — | Não | Vertical de operação condominial. |
| Carteiras/transações/inadimplência | ERP | Sim | [DESCARTADO] | — | Não | — | Não | Complexidade financeira sem aderência. |
| Portal de moradores | Residents | Sim | [DESCARTADO] | — | Não | — | Não | Público e problema diferentes. |

`*` Torna-se bloqueador apenas se o catálogo de serviços editável for parte da oferta comercial inicial.

## Avaliação das 26 grandes áreas

| Área | Estado | Diagnóstico |
|---|---|---|
| 1. Site público | [OK] | Completo para apresentação e aquisição; faltam validação final de conteúdo/legal e antispam. |
| 2. CMS / Studio | [OK] | Núcleo editorial completo; preview, revisão e ações em lote podem evoluir. |
| 3. Usuários | [PARCIAL] | Gestão administrativa pronta; onboarding, recuperação e escopo por cliente ausentes. |
| 4. Área do cliente | [AUSENTE] | Shell e perfil não entregam tarefas de cliente. |
| 5. Conteúdo | [OK] | Artigos e páginas publicados a partir do banco. |
| 6. Mídia | [MELHORAR] | Biblioteca funcional; governança e privacidade precisam endurecimento. |
| 7. Propostas | [PARCIAL] | Captação e gestão funcionam; conversão e PDF faltam. |
| 8. Notificações | [PARCIAL] | Internas funcionam; entrega externa e preferências faltam. |
| 9. Relatórios | [PARCIAL] | Base visual existe, cobertura e exportações ainda pequenas. |
| 10. Logs | [PARCIAL] | Redação segura presente; auditoria completa e retenção faltam. |
| 11. Configurações | [OK] | Bom escopo e separação por assunto. |
| 12. Segurança | [MELHORAR] | Base forte; há riscos operacionais e de autorização futura. |
| 13. Performance | [MELHORAR] | Frontend leve; faltam medição, budgets, cache e teste de carga. |
| 14. UX/UI | [OK] | Coerente com as referências; fluxos longos e feedback assíncrono podem melhorar. |
| 15. Responsividade | [MELHORAR] | Implementada, ainda sem matriz automatizada de dispositivos. |
| 16. Acessibilidade | [MELHORAR] | Semântica e redução de movimento presentes; falta auditoria WCAG/teclado/leitor. |
| 17. Banco | [PARCIAL] | CMS coberto; domínio de cliente/serviço/projeto/suporte ausente. |
| 18. Migrations | [MELHORAR] | Scripts existem, mas execução repetível e rastreável não está garantida. |
| 19. Uploads | [MELHORAR] | Tipo/tamanho/path tratados; falta política completa de arquivos e malware. |
| 20. Armazenamento | [MELHORAR] | Contenção de caminho e diagnóstico presentes; falta quota, ciclo de vida e storage privado. |
| 21. SEO | [OK] | Automação editorial é um ganho claro do novo. |
| 22. Deploy | [AUSENTE] | Não há pipeline/runbook/gates de produção comprovados. |
| 23. Backup | [AUSENTE] | Não há restauração ensaiada e registrada. |
| 24. Diagnóstico | [OK] | PHP, PDO, configuração, storage e banco são verificados. |
| 25. Observabilidade | [PARCIAL] | Logs existem; alertas, métricas, tracing e monitoramento de jobs não. |
| 26. Documentação | [OK] | Arquitetura e migrações estão documentadas; falta runbook operacional. |

## Segurança para lançamento

### Controles confirmados no novo

- `password_verify`, regeneração de sessão e revalidação de usuário ativo;
- middleware de autenticação e permissão, com rotas administrativas protegidas;
- CSRF em mutações, logout por POST e cookies endurecidos por ambiente;
- queries preparadas, escape de saída e sanitização do HTML editorial;
- bloqueio de redirects externos e injeção em headers;
- throttle de login, CSP e demais security headers;
- contenção de caminhos e validação de imagens no Storage;
- erros controlados em produção e redação de contexto sensível nos logs.

### Riscos reais

| Risco | Severidade | Situação e ação |
|---|---|---|
| Falta de autorização por objeto/cliente | Alta antes do portal | Ainda não há objetos de cliente. Implementar ownership central e testes negativos antes dos módulos. |
| Mídia endereçável por ID sem estado explícito de publicação | Média | Separar pública/privada; impedir enumeração de arquivo não publicado ou sensível. |
| Contato sem proteção antispam/rate limit dedicado | Alta operacional | Limitar por IP/identidade, honeypot e telemetria; evitar CAPTCHA intrusivo inicialmente. |
| Upload sem antivírus/quota/ciclo de vida | Média | Aceitável apenas para imagens públicas controladas; obrigatório para anexos/documentos. |
| Sem recuperação segura de senha | Alta para portal | Implementar token aleatório com hash, validade, uso único e resposta não enumerável. |
| Segredos/configuração de produção sem gate automatizado | Alta | Validar `.env`, debug, URL, cookies, SMTP e permissões no deploy. |
| Auditoria incompleta de mutações | Média/Alta | Registrar ator, ação, alvo, antes/depois seguro, IP/contexto e correlação. |
| Sem backup/restauração comprovados | Alta | Bloqueador operacional, não apenas melhoria. |

No legado foram encontrados padrões que **não devem ser copiados**: logout por GET, token de recuperação com `md5(uniqid(rand()))`, mensagens distintas para contas existentes e controles de segurança inconsistentes entre módulos.

## Qualidade verificada

| Verificação | Resultado novo | Referência antiga |
|---|---|---|
| Composer validate | OK | OK |
| Composer audit | 0 advisories | 0 advisories |
| PHPUnit | 13 testes, 33 assertions, OK | 49 testes, 357 assertions, OK |
| PHPStan | OK, sem erros | Não há gate equivalente configurado |
| Auth/middleware | 48 verificações, OK | Cobertura PHPUnit maior, arquitetura mista |
| Configurações Studio | Script funcional, OK | Não comparável diretamente |
| Diagnóstico | PHP/PDO/config/storage/banco, OK | Ferramentas de DB/deploy mais amplas |
| HTTP | Público 200; `/app` e `/admin` redirecionam ao login | Público responde; áreas redirecionam aos logins |

Ainda faltam CI, testes end-to-end, acessibilidade automatizada, teste de carga, teste de migration em banco limpo e ensaio de restore.

## Maturidade (0–10)

| Dimensão | Nota | Justificativa |
|---|---:|---|
| Arquitetura | 8,0 | Superfícies e core bem separados; domínio de cliente ainda ausente. |
| Segurança | 8,0 | Bons defaults e testes; faltam ownership, operação de produção e upload privado. |
| Código | 7,5 | Base clara e tipada; controllers/templates concentram responsabilidades e CSS é grande. |
| Banco | 5,5 | CMS bem modelado, mas núcleo comercial/cliente não existe. |
| Testes | 7,0 | Gates atuais passam; cobertura funcional e e2e ainda pequena. |
| Studio | 7,8 | Todos os módulos previstos existem; profundidade varia. |
| CMS | 8,5 | Conteúdo, mídia, taxonomia, publicação e SEO integrados. |
| Site público | 8,5 | Experiência forte e dinâmica; faltam gates finais e legal. |
| Área do cliente | 2,5 | Autenticação e perfil, sem tarefas de negócio. |
| UI/UX | 8,0 | Design coerente; fluxos, feedback e estados precisam teste sistemático. |
| Responsividade | 7,5 | Boa fundação, sem cobertura formal de dispositivos. |
| Acessibilidade | 7,0 | Cuidados básicos presentes, sem conformidade WCAG demonstrada. |
| SEO | 8,0 | Automação robusta; falta validação externa/produção e dados estruturados completos. |
| Deploy | 4,0 | Diagnóstico local existe; promoção, rollback e runbook não. |
| Documentação | 8,0 | Decisões e módulos registrados; operação de produção incompleta. |
| Observabilidade | 5,5 | Logs e diagnóstico, sem métricas/alertas/SLO. |

## Estimativa de conclusão

Os percentuais usam pesos por tarefa entregável, segurança e operação — não contagem de telas.

| Área | Conclusão | Critério |
|---|---:|---|
| Site público | 85% | Rotas e conteúdo dinâmico completos; faltam hardening operacional, legal e validação final. |
| Studio | 78% | 14 módulos presentes; automações, auditoria, exportação e profundidade ainda variam. |
| CMS | 85% | Fluxo editorial principal completo; preview/revisão/lote são gaps. |
| Área do Cliente | 20% | Fundação autenticada pronta, mas quase todas as tarefas de negócio ausentes. |
| Core | 82% | Segurança e infraestrutura sólidas; domínio, jobs e operação de produção faltam. |
| Segurança | 78% | Controles de aplicação fortes; ownership, antispam, backups e gates de produção pendentes. |
| Testes | 70% | Unitários/integração local passam; faltam CI, e2e, browser, carga e restore. |
| Lançamento completo | **66%** | Média ponderada com peso maior para segurança, operação e jornada do cliente. |

Para o escopo reduzido **site + Studio interno**, a prontidão estimada é **82%**. Para a promessa completa com portal do cliente, permanece **66%**.

## Veredito

O Moves novo preservou e melhorou o núcleo útil do CMS antigo, mas ainda não substitui as tarefas transacionais que justificam uma Área do Cliente. O caminho seguro é lançar em duas fronteiras: primeiro site e Studio após os bloqueadores operacionais; depois portal com modelo de ownership, serviços/projetos, suporte e recuperação de acesso. O legado deve orientar os fluxos, nunca a implementação.
