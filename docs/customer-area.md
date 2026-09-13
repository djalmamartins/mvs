# Área do Cliente

## Visão

A Área do Cliente (`/app`) é o ambiente privado de relacionamento entre a Moves e clientes autenticados. Ela não compartilha navegação nem terminologia interna com o Moves Studio (`/studio`) e não substitui o site público (`/`).

## Antes, depois e motivo

**Antes:** `/app` exibia uma página técnica genérica e `/app/profile` mostrava dados básicos sem shell próprio.

**Depois:** `/app` possui Dashboard, sidebar, topbar, estados vazios honestos e identidade “Moves — Área do Cliente”; o perfil foi integrado ao mesmo layout.

**Motivo:** estabelecer uma base privada legível e segura antes de introduzir entidades comerciais e dados pertencentes a clientes.

## Rotas atuais

- `GET /app`: Dashboard protegido por autenticação.
- `GET /app/status`: estado JSON autenticado para sincronização do Dashboard.
- `GET /app/profile`: perfil protegido por autenticação e `profile.view`.
- `GET /profile`: redirecionamento legado permanente para `/app/profile`.
- `POST /logout`: encerramento protegido por autenticação e CSRF.

Módulos futuros aparecem desabilitados, sem links ou rotas vazias: serviços, projetos, hospedagem, domínios, chamados, faturas, documentos e notificações.

## Arquitetura e segurança

Templates ficam em `resources/themes/app` e assets em `public/themes/app`. `AuthMiddleware` protege a área e envia `Cache-Control: private, no-store` e `Pragma: no-cache`. O layout declara `noindex, nofollow`. Dados do usuário são escapados na saída; logout permanece POST com CSRF. `Auth::user()` também invalida imediatamente a autenticação quando a conta deixa de estar ativa, mesmo que o cookie de sessão ainda exista.

Ainda não existe entidade `Client`. Nesta rodada não foram criados recursos pertencentes a clientes, portanto não há IDs de negócio expostos nem risco novo de IDOR. Antes de qualquer rota como `/app/projects/{id}`, deve ser introduzida uma relação explícita `clients ↔ users` e todas as consultas devem filtrar pela propriedade, retornando 404 para recursos de terceiros.

## Dashboard

Os quatro indicadores usam zero real porque ainda não existem tabelas ou vínculos de serviços, projetos, chamados e faturas. Projetos, próximas ações e atividade exibem estados vazios explícitos. Nenhum dado demonstrativo foi inserido.

O navegador consulta `/app/status` a cada 30 segundos enquanto a página está visível e também permite atualização manual. A sincronização é pausada em abas ocultas, reage aos estados online/offline e nunca compartilha dados entre usuários. As animações são progressivas e desativadas para quem configura `prefers-reduced-motion`.

## Roadmap

1. Fundação visual e segurança — concluída.
2. Cliente e vínculo de usuários — próxima fase.
3. Serviços.
4. Projetos, etapas e timeline.
5. Chamados e comunicação.
6. Documentos com downloads autorizados.
7. Hospedagem e domínios sem credenciais.
8. Faturas sem gateway.
9. Pagamentos após definição de fornecedor.
10. Notificações e histórico consolidados.
