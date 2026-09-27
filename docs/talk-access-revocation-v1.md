# Talk V1: revogação de acesso operacional

O acesso ao Talk depende de três estados ativos: usuário global (`users.status`), vínculo com a empresa (`talk_tenant_users.status`) e empresa (`talk_tenants.status`). `TalkTenantContext::forUser()` rejeita qualquer um deles inativo. `TalkService::permissions()` aplica a mesma regra quando um serviço recebe um tenant explícito.

Antes de mostrar ou operar um ticket, `canViewTicket()` e `canOperateTicket()` exigem uma permissão Talk válida. Um ticket atribuído não preserva acesso após a desativação do atendente. A reativação restaura a política normal de visualização e operação; a atribuição em si não é alterada automaticamente.

O teste de integração `TalkTenantIsolationTest::testInactiveUserMembershipOrTenantRevokesAssignedTicketAccess` cobre os três estados, a revogação imediata e a reativação em MySQL descartável. A autorização continua isolada por tenant. O E2E externo de WhatsApp depende de dispositivo, número e sessão real, indisponíveis neste ambiente.
