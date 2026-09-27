# Talk V1: revogação imediata de acesso

As autorizações de leitura e operação de tickets exigem simultaneamente tenant ativo, usuário global ativo e vínculo ativo em `talk_tenant_users`. A atribuição histórica do ticket não mantém acesso depois que qualquer uma dessas identidades é desativada.

Ao reativar os três níveis, as permissões normais voltam a ser avaliadas. O isolamento por tenant permanece obrigatório e membros de outra empresa não obtêm acesso por ID direto.
