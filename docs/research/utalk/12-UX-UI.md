# 12 — UX/UI

## Avaliação

| Padrão | Classificação | Motivo |
|---|---|---|
| Inbox em três painéis | APROVEITAR O CONCEITO | reduz troca de contexto entre lista, conversa e cliente |
| Estados Entrada/Esperando/Finalizados | ADAPTAR PARA MOVES | nomes precisam refletir fila, meus e supervisão sem ambiguidade |
| Configuração agrupada por domínio | APROVEITAR O CONCEITO | boa encontrabilidade apesar do volume |
| Menu extenso com recursos bloqueados | NÃO UTILIZAR | aumenta ruído e frustração; usar permissões e disclosure progressivo |
| Painel “Agora” | APROVEITAR O CONCEITO | orienta supervisão operacional imediata |
| Boards de contatos | FUTURO/ADAPTAR | útil comercialmente, pouco central ao atendimento condominial V1 |
| Finalização configurável | APROVEITAR O CONCEITO | permite mensagem, avaliação e automação consistente |
| Créditos misturados à operação | NÃO UTILIZAR como padrão | custo deve ser visível ao admin, sem distrair atendente |
| Contexto do contato lateral | ADAPTAR PARA MOVES | substituir CRM genérico por condomínio/unidade/financeiro/chamados |

## Pontos fortes

- Hierarquia clara entre operação e configurações.
- Descrições contextuais e estados vazios instrutivos.
- Filtros ricos nos relatórios.
- Ações administrativas acompanhadas de explicação.

## Pontos fracos

- Muitos menus e conceitos próximos (setores, grupos, canais, filas implícitas).
- Recursos Enterprise permanecem visíveis e competem com tarefas disponíveis.
- A conta pendente leva a telas vazias/desabilitadas sem permitir avaliação do fluxo completo.
- Algumas rotas/labels são inconsistentes (`chatbot`/`chatbots`).

## Moves

A inbox atual replica corretamente o padrão estrutural, mas existem duas superfícies concorrentes (`talk-inbox.php` e `talk-ticket.php`) com capacidades diferentes. Consolidar em uma única workspace responsiva e acessível. Preservar foco do teclado, feedback assíncrono, rascunho local e prevenção de envio duplicado.
