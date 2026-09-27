# Fundação SaaS canônica da Moves Platform

A plataforma usa uma única hierarquia de isolamento:

`Moves Platform → talk_tenants → talk_tenant_users → papéis/permissões → produtos → módulos`

O nome histórico `talk_tenants` foi preservado para evitar uma segunda árvore
de tenants e uma migração destrutiva. `erp_administrators` é o perfil ERP com
relação 1:1 ao tenant; condomínios pertencem à administradora correspondente.

## Componentes

- `TenantContext`: valida no servidor a administradora ativa e permite troca
  somente entre vínculos ativos de um usuário ativo.
- `TenantAuthorization`: resolve permissões pelo papel do vínculo no tenant.
- `ProductEntitlement`: fonte única para Talk, ERP, Support, CMS e Studio.
- `CompanyService`, `MemberService` e `CondominiumService`: escrita com escopo,
  validação e eventos em `platform_audit_events`.
- `ProductAccessGate`: bloqueia módulos desabilitados antes do dispatch.
- `/onboarding`: quatro etapas para conta, administradora, produtos e revisão;
  a conclusão é transacional e nunca guarda a senha em texto aberto na sessão.
- `/settings`: administração central de empresa, produtos, equipe e condomínios.
- `/tenant/switch`: troca protegida por autenticação, CSRF e vínculo ativo.

## Compatibilidade de upgrade

A migration `20260927_015` cria os papéis e permissões por tenant, mapeia os
vínculos existentes e habilita todos os produtos para tenants antigos. Esse
backfill mantém o acesso que existia antes de o entitlement virar obrigatório.
Novos tenants habilitam apenas os produtos selecionados no onboarding.
A migration `20260927_016` associa as permissões legadas de Studio/CMS aos
papéis proprietário e administrador, sem dispensar ou enfraquecer o MFA global.

## Segurança e auditoria

Login, logout, troca de tenant, alterações de produtos, empresa, membros e
condomínios geram registros append-only. Consultas e atualizações de condomínio
sempre chegam ao tenant por `erp_administrators.tenant_id`. O teste permanente
`PlatformSaasFoundationTest` cria administradoras A/B e prova separação de
produtos, permissões, equipe e condomínios.

Rastreamento: [issue #172](https://github.com/djalmamartins/mvs/issues/172).
