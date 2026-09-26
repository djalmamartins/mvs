# 07 — Supervisão

## Capacidades encontradas

- Relatórios gerais e painel operacional em tempo real.
- Contagem de conversas em espera/em andamento e atendentes online.
- Filtros por canal, atendente, período, setor, etiqueta e contato.
- Listagem de todas as conversas com canal, status e datas.
- Transferência e reatribuição entre pessoas/setores.
- Logs de atividade e histórico de eventos (Enterprise/interface e API).
- Avaliação de atendimentos e desempenho individual.

## Matriz de papéis recomendada

| Recurso | Atendente | Supervisor | Administrador |
|---|---:|---:|---:|
| Ver fila autorizada | Sim | Sim | Sim |
| Ver próprios atendimentos | Sim | Sim | Sim |
| Ver todos da operação | Não | Sim | Sim |
| Assumir/devolver/transferir próprios | Sim | Sim | Sim |
| Intervir em atendimento alheio | Não | Sim, auditado | Sim, auditado |
| Reabrir | Não | Sim | Sim |
| Ver SLA e painel Agora | Próprios | Operação | Organização |
| Gerir filas/equipe | Não | Limitado ao escopo | Sim |
| Gerir canais, IA e integrações | Não | Consulta | Sim |
| Gerir cobrança/tenant | Não | Não | Proprietário |
| Exportar dados pessoais | Não | Conforme permissão | Conforme política |

O uTalk observado usa Membro, Operador, Admin e Proprietário; “supervisor” é melhor tratado no Moves como papel de domínio com escopo por fila/administradora, não como sinônimo de Admin.

## Moves atual

O Moves distingue `agent`, `supervisor` e `admin`; supervisor/admin podem operar e ver qualquer ticket pelas funções `canManage`, `canOperateTicket` e `canViewTicket`. Isso atende a regra básica, porém falta escopo multiempresa/fila e middleware específico nas rotas administrativas.
