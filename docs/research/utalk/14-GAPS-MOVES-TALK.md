# 14 — Gaps do Moves Talk

## CRÍTICO PARA V1

- Isolamento multiempresa em banco, queries, cache, arquivos, eventos e transporte.
- Autorização central por ação/recurso/tenant; rotas administrativas protegidas.
- Canal WhatsApp oficial produtivo: onboarding, webhook assinado, idempotência, retries e observabilidade.
- Modelo de múltiplos números/canais por administradora.
- Janela de atendimento e templates obrigatórios fora da janela.
- Identidade do contato e vínculo seguro a condomínio/unidade/morador.
- LGPD: consentimento/base legal, retenção, anonimização, exportação e auditoria.
- Testes automatizados do Talk; não foram encontrados testes específicos no diretório `tests`.
- Jobs/filas duráveis para inbound, outbound, autoatribuição e Jack; hoje parte do processamento ocorre em `/talk/sync`.

## IMPORTANTE PARA V1

- Horário de atendimento, feriados, fallback e SLA por horário útil.
- Respostas rápidas com variáveis controladas.
- Painel supervisor “Agora”, backlog por idade e alertas de SLA.
- Busca/filtros consistentes, histórico completo e trilha de transferência.
- Saúde do canal, status de entrega e recuperação de falhas.
- Handoff do Jack com resumo e contexto ERP.
- Preferências de notificação, menções e som por usuário.

## PODE FICAR PARA V1.1

- Avaliação de atendimento.
- Mensagens agendadas.
- Sugestão/melhoria de resposta por IA.
- Webhooks públicos, API externa e logs de entrega.
- Campos personalizados genéricos.
- Transcrição, favoritos, fixados, reações e encaminhamento em massa.
- Bases de conhecimento administráveis e versionadas.

## FUTURO

- Editor visual de chatbot.
- WhatsApp Flows.
- Campanhas de marketing.
- Webchat.
- Boards/Kanban de contatos.
- Grupos genéricos de objetos e biblioteca de stickers.

## NÃO NECESSÁRIO PARA MOVES

- Copiar o CRM genérico ou a identidade visual do uTalk.
- Reproduzir cobrança por crédito dentro da área do atendente.
- Criar múltiplas abstrações sobrepostas (grupo/setor/fila) sem necessidade do domínio.
- Priorizar campanhas antes de consentimento, operação e integrações condominiais.
