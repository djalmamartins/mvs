# Access & Security QA — SEC-010

Data: 2026-09-28
Branch: `feat/mvs-access-security`
HEAD validado: `229dbfa96d49d6cc424aae146c5fe372f82fac85`

## Quality Gate automatizado

- GitHub Actions run 36426143323: sucesso.
- PHP 8.2: sucesso.
- JavaScript/WhatsApp bridge: sucesso.
- Composer validate/audit: sucesso.
- Migrations + migration safety: sucesso.
- PHPUnit: 207 testes, 874 assertions.
- PHPStan: sem erros.
- PHP syntax: sucesso.
- Authentication HTTP E2E: 48 verificações reais, todas aprovadas.

## Cobertura do ciclo

- Login: credenciais válidas/inválidas, CSRF e rotação de sessão.
- Recuperação: código hash, expiração, limite de tentativas, cooldown, reset e anti-replay.
- Nova senha: política forte, rejeição de reutilização e consumo do recovery.
- 2FA: segredo cifrado, TOTP, recovery codes hash e uso único.
- Convite/primeiro acesso: token hash, expiração, revogação, anti-replay e isolamento tenant.
- Sessão/logout: timeout fail-closed, conta inativa, destruição da sessão e CSRF.
- Throttle: integração MySQL, bloqueio na quinta falha, clear e isolamento e-mail/IP.
- Permissões: usuário sem tenant recebe 403 em rotas administrativas.
- Segurança HTTP: CSP, nosniff, frame deny, referrer policy, permissions policy e proteção de arquivos internos.

## Responsividade

O auth shell usa duas colunas no desktop e breakpoint em 800px que converte para coluna única, oculta o painel visual e reduz paddings/tipografia para tablet/mobile. A inspeção estrutural não substitui screenshot/browser visual manual; nenhuma evidência visual automatizada existia no repositório no fechamento deste QA.

## Observação

O warning MySQL sobre `--skip-host-cache` é depreciação do runner e não falha da aplicação.
