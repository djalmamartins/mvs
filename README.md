# Moves

Moves é uma plataforma PHP reutilizável para aplicações web. O projeto mantém um core único, simples e modular, sem depender de um framework completo ou de camadas arquiteturais desnecessárias.

## Requisitos

- PHP 8.2 ou superior com PDO e o driver do banco utilizado
- MySQL ou MariaDB
- Composer
- document root configurado para `public/`

O ambiente de referência é o PHP 8.2.4 do XAMPP.

## Instalação

```bash
composer install
cp .env.example .env
/Applications/XAMPP/xamppfiles/bin/php scripts/check.php
/Applications/XAMPP/xamppfiles/bin/php scripts/migrate.php
/Applications/XAMPP/xamppfiles/bin/php scripts/create-user.php
```

Configure o servidor web para servir apenas `public/`. O arquivo `.env`, o código e `storage/` devem permanecer fora do document root.

## Ambiente, banco e Settings

O `.env` contém somente infraestrutura e segredos locais. Ele não é versionado. As chaves principais são `APP_ENV`, `APP_DEBUG`, `APP_URL`, `APP_TIMEZONE` e as opções `DB_*`.

As configurações editáveis ficam na tabela `settings` e são acessadas por `Settings::get()`, `Settings::set()` ou pelo helper `setting()`. Nunca grave credenciais em Settings. Administradores podem editar o nome da aplicação em `/admin/settings`.

As migrations SQL ficam em `database/migrations/`. O runner cria o histórico, executa apenas arquivos pendentes em ordem e interrompe na primeira falha:

```bash
/Applications/XAMPP/xamppfiles/bin/php scripts/migrate.php
```

Não altere uma migration aplicada; crie outra com prefixo cronológico.

## Fluxo e estrutura

```text
public/index.php → Environment → Application → Router → Middleware → Controller → View
```

- `app/Boot`: ambiente, conexão, rotas e registro modular
- `app/Core`: infraestrutura compartilhada
- `app/Controllers`: controladores HTTP
- `app/Middleware`: autenticação, convidados e permissões
- `app/Models`: models baseados em `movescode/model`
- `app/Support`: helpers globais pequenos
- `resources/themes`: templates PHP
- `public/themes`: CSS, JS, imagens e fontes públicas
- `database/migrations`: evolução do banco
- `scripts`: comandos e testes de integração
- `storage`: logs e uploads gerados em execução

## Temas

O contexto é resolvido pela URL, sem `APP_THEME` global:

- `/admin` e `/admin/*`: `admin`
- `/app` e `/app/*`: `app`
- demais rotas: `site`

Cada contexto possui layouts, componentes e páginas em `resources/themes/<contexto>` e assets em `public/themes/<contexto>`. O helper de view `asset()` acrescenta a data de modificação para evitar cache obsoleto.

## Autenticação e autorização

`Session` usa cookie HTTP-only, SameSite Lax e Secure quando `APP_URL` é HTTPS. `Auth` responde quem é o usuário; `Access` e `PermissionMiddleware` controlam o que ele pode fazer. Formulários mutáveis devem incluir `<?= $this->csrf() ?>` e validar o token no controller.

Rotas protegidas usam `AuthMiddleware`. Acesso web sem permissão cria Flash e redireciona para `/app`; exceções HTTP 403 continuam disponíveis para integrações técnicas.

## Criando rota, controller, model, página e middleware

Registre a rota em `app/Boot/Routes.php`:

```php
$router->get('/example', 'ExampleController:index', 'example.index');
```

Crie o controller em `app/Controllers` estendendo `Controller` e renderize uma página do contexto da URL:

```php
echo $this->view->render('pages/example', ['title' => 'Exemplo']);
```

Crie models em `app/Models` estendendo `MovesCode\Model\Model`. Crie middleware em `app/Middleware` implementando `MovesCode\Middleware\MiddlewareInterface` e associe-o à rota. Todos os novos arquivos PHP devem usar strict types, namespace PSR-4 e o cabeçalho Moves.

## Aplicações futuras

`Moves\Boot\Modules` é o ponto mínimo de extensão. Uma aplicação pode registrar um callback de rotas e permissões adicionais, mantendo controllers, models e middleware nas pastas existentes. Views entram no contexto adequado, Settings usa a API central e migrations continuam no runner existente. Não há providers, event bus ou sistema complexo de plugins.

## Desenvolvimento e testes

```bash
/Applications/XAMPP/xamppfiles/bin/php vendor/bin/phpunit
/Applications/XAMPP/xamppfiles/bin/php vendor/bin/phpstan analyse --no-progress

for test in scripts/test-*.php; do
  /Applications/XAMPP/xamppfiles/bin/php "$test" || exit 1
done

composer validate --strict
composer audit
```

Os scripts HTTP e de banco usam o ambiente local configurado. O diagnóstico administrativo fica em `/admin/diagnostics` e nunca apresenta credenciais.

## Produção

- use `APP_ENV=production` e `APP_DEBUG=false`
- use HTTPS e uma `APP_URL` correta
- execute `composer install --no-dev --optimize-autoloader`
- execute migrations antes de publicar o novo código
- garanta escrita apenas onde necessário em `storage/`; nunca use `chmod 777`
- aponte o servidor para `public/`
- mantenha `.env` fora do controle de versão e do diretório público
- confirme que logs não exibem segredos e que erros não mostram stack trace

Antes do deploy, execute `scripts/check.php`, os testes, o PHPStan, `composer validate --strict`, `composer audit` e `git diff --check`.
