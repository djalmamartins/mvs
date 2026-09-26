# 08 — Usuários e permissões

## uTalk observado

| Papel | Descrição exibida |
|---|---|
| Membro | não altera configurações e não envia mensagens a contatos |
| Operador | envia mensagens e altera configurações básicas úteis ao atendimento |
| Admin | todas as permissões, exceto painel financeiro |
| Proprietário | todas as permissões, inclusive financeiro |

A API confirma permissões individuais, alteração em lote, reset de papel/reatribuição/permissões, ativação/desativação, transferência dos atendimentos ao desativar e grupos Enterprise.

## Modelo recomendado ao Moves

- **Papel base**: atendente, supervisor, administrador, proprietário.
- **Escopo**: administradora, operação, fila, condomínio e canal.
- **Ações**: visualizar, responder, assumir, transferir, reabrir, exportar, configurar, auditar.
- **Exceções**: grants explícitos com validade e responsável.

Evitar testes espalhados apenas por nome do papel. Centralizar política: `can(action, resource, tenant, scope)`.

## Riscos atuais do Moves

- As rotas do Talk usam apenas `AuthMiddleware`; autorização é feita dentro de algumas ações, mas páginas como histórico, contatos, conversas, relatórios e configurações não demonstram guarda uniforme no roteamento.
- `canManage` não considera empresa/fila.
- As tabelas Talk não têm `tenant_id`/`organization_id`.
- Admin global do sistema pode ser confundido com administrador de uma administradora.

Antes de produção, cada consulta deve receber tenant obrigatório e cada endpoint deve declarar permissão e escopo.
