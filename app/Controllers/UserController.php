<?php

declare(strict_types=1);

namespace Moves\Controllers;

use Moves\Core\Auth;
use Moves\Boot\Connection;
use Moves\Core\Controller;
use Moves\Core\Config;
use Moves\Core\Csrf;
use Moves\Core\Flash;
use Moves\Core\Logger;
use Moves\Core\Request;
use Moves\Core\Response;
use Moves\Models\User;
use Moves\Services\Auth\UserInvitationService;
use Moves\Services\Auth\PasswordPolicy;
use Moves\Services\Mail\SmtpInvitationMailer;
use Moves\Services\Platform\PlatformAudit;
use Moves\Services\Platform\TenantContext;
use MovesCode\Pager\Pager;
use PDO;
use Throwable;

/**
 * Moves | User Controller
 *
 * Gerencia as páginas relacionadas
 * ao usuário autenticado.
 *
 * @author Djalma Martins
 * @package Moves\Controllers
 */
final class UserController extends Controller
{
    /**
     * Preserva a URL antiga do perfil.
     */
    public function appProfileRedirect(): void
    {
        Response::to('/profile', 301);
    }

    public function appSecurityRedirect(): void
    {
        Response::to('/profile/security/2fa', 301);
    }

    /**
     * Exibe o perfil do usuário autenticado.
     */
    public function profile(): void
    {
        $user = Auth::user();

        echo $this->view->render(
            'pages/profile',
            [
                'title' => 'Meu perfil',
                'user' => $user,
                'preferences' => $this->profilePreferences((int) $user->id),
            ]
        );
    }

    public function updateProfile(): never
    {
        $this->validateCsrf();
        $user = Auth::user();
        if ($user === null) { Response::to('/login'); }

        $name = mb_substr(trim(strip_tags((string) Request::post('name', ''))), 0, 120);
        $theme = (string) Request::post('theme', 'system');
        $locale = (string) Request::post('locale', 'pt-BR');
        $notifications = Request::post('email_notifications') === '1' ? 1 : 0;
        if (mb_strlen($name) < 2 || !in_array($theme, ['light', 'dark', 'system'], true) || !in_array($locale, ['pt-BR', 'en-US'], true)) {
            Flash::set('error', 'Revise os dados e preferências do perfil.');
            Response::to('/profile');
        }

        $pdo = Connection::getInstance();
        try {
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE users SET name=? WHERE id=?')->execute([$name, (int) $user->id]);
            $pdo->prepare(
                'INSERT INTO user_preferences(user_id,locale,theme,email_notifications) VALUES(?,?,?,?) '
                . 'ON DUPLICATE KEY UPDATE locale=VALUES(locale),theme=VALUES(theme),email_notifications=VALUES(email_notifications)'
            )->execute([(int) $user->id, $locale, $theme, $notifications]);
            $pdo->commit();
            Logger::info('Perfil atualizado.', ['actor_id' => $user->id]);
            Flash::set('success', 'Perfil atualizado com sucesso.');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            Logger::exception($exception);
            Flash::set('error', 'Não foi possível atualizar o perfil.');
        }
        Response::to('/profile');
    }

    public function updatePassword(): never
    {
        $this->validateCsrf();
        $user = Auth::user();
        if ($user === null) { Response::to('/login'); }

        $current = (string) Request::post('current_password', '');
        $password = (string) Request::post('password', '');
        $confirmation = (string) Request::post('password_confirmation', '');
        $errors = PasswordPolicy::errors($password);
        if (!password_verify($current, (string) $user->password)) {
            $errors[] = 'A senha atual está incorreta.';
        }
        if (!hash_equals($password, $confirmation)) {
            $errors[] = 'A confirmação deve ser igual à nova senha.';
        }
        if ($current !== '' && password_verify($password, (string) $user->password)) {
            $errors[] = 'A nova senha deve ser diferente da senha atual.';
        }
        if ($errors !== []) {
            foreach ($errors as $error) { Flash::set('error', $error); }
            Response::to('/profile');
        }

        Connection::getInstance()->prepare('UPDATE users SET password=? WHERE id=?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), (int) $user->id]);
        Logger::info('Senha alterada pelo perfil.', ['actor_id' => $user->id]);
        Auth::logout();
        Flash::set('success', 'Senha alterada. Entre novamente com a nova senha.');
        Response::to('/login');
    }

    /** @return array{locale:string,theme:string,email_notifications:int} */
    private function profilePreferences(int $userId): array
    {
        $statement = Connection::getInstance()->prepare(
            'SELECT locale,theme,email_notifications FROM user_preferences WHERE user_id=?'
        );
        $statement->execute([$userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? [
            'locale' => (string) $row['locale'],
            'theme' => (string) $row['theme'],
            'email_notifications' => (int) $row['email_notifications'],
        ] : ['locale' => 'pt-BR', 'theme' => 'system', 'email_notifications' => 1];
    }

    /**
     * Exibe a listagem paginada de usuários.
     *
     * @param array<string, string> $data
     */
    public function index(array $data = []): void
    {
        $tenantId = $this->currentTenantId();
        $page = max(
            1,
            (int) ($data['page'] ?? 1)
        );

        $search = mb_substr(trim(strip_tags((string) Request::get('q', ''))), 0, 100);
        $status = in_array(Request::get('status'), ['active', 'inactive'], true) ? (string) Request::get('status') : '';
        $role = in_array(Request::get('role'), ['owner', 'administrator', 'supervisor', 'agent', 'operator'], true) ? (string) Request::get('role') : '';
        $where = ['membership.tenant_id = :tenant_id'];
        $params = ['tenant_id' => $tenantId];
        if ($search !== '') { $where[] = '(account.name LIKE :search OR account.email LIKE :search)'; $params['search'] = '%' . $search . '%'; }
        if ($status !== '') { $where[] = 'membership.status = :status'; $params['status'] = $status; }
        if ($role !== '') { $where[] = 'membership.role = :role'; $params['role'] = $role; }
        $whereSql = ' WHERE ' . implode(' AND ', $where);
        $fromSql = ' FROM talk_tenant_users membership
                     INNER JOIN users account ON account.id = membership.user_id
                     INNER JOIN platform_roles platform_role ON platform_role.id = membership.role_id AND platform_role.tenant_id = membership.tenant_id';
        $pdo = Connection::getInstance();
        $count = $pdo->prepare('SELECT COUNT(*)' . $fromSql . $whereSql);
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $filterQuery = http_build_query(array_filter(
            ['q' => $search, 'status' => $status, 'role' => $role],
            static fn (string $value): bool => $value !== ''
        ));
        $pager = new Pager('/studio/users/page/{page}' . ($filterQuery === '' ? '' : '?' . $filterQuery));

        $pager->pager(
            $total,
            10,
            $page
        );

        $statement = $pdo->prepare('SELECT account.id,account.name,account.email,platform_role.slug AS role_slug,platform_role.name AS role,
                                           membership.status,account.created_at' . $fromSql . $whereSql
            . ' ORDER BY account.name ASC,account.id ASC LIMIT ' . $pager->limit() . ' OFFSET ' . $pager->offset());
        $statement->execute($params);
        $users = $statement->fetchAll(PDO::FETCH_ASSOC);
        $statsStatement = $pdo->prepare("SELECT COUNT(*) total,
                                                SUM(membership.status='active' AND account.status='active') active,
                                                SUM(membership.status<>'active' OR account.status<>'active') inactive,
                                                SUM(platform_role.slug IN ('owner','administrator')) admins" . $fromSql
            . ' WHERE membership.tenant_id=:tenant_id');
        $statsStatement->execute(['tenant_id' => $tenantId]);
        $stats = $statsStatement->fetch(PDO::FETCH_ASSOC) ?: [];

        echo $this->view->render(
            'pages/users',
            [
                'title' => 'Usuários',
                'users' => $users,
                'pagination' => $pager->render(),
                'search' => $search,
                'status' => $status,
                'role' => $role,
                'stats' => $stats,
            ]
        );
    }

    /** @param array<string, string> $data */
    public function form(array $data = []): void
    {
        $tenantId = $this->currentTenantId();
        $id = max(0, (int) ($data['id'] ?? 0));
        $user = null;
        if ($id > 0) {
            $statement = Connection::getInstance()->prepare(
                'SELECT account.id,account.name,account.email,membership.role_id,platform_role.slug AS role,
                        membership.status,account.created_at
                   FROM users account
                   INNER JOIN talk_tenant_users membership ON membership.user_id=account.id AND membership.tenant_id=?
                   INNER JOIN platform_roles platform_role ON platform_role.id=membership.role_id AND platform_role.tenant_id=membership.tenant_id
                  WHERE account.id=?'
            );
            $statement->execute([$tenantId, $id]);
            $user = $statement->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($user === null) { Response::to('/studio/users'); }
        }
        $statement = Connection::getInstance()->prepare('SELECT id,slug,name FROM platform_roles WHERE tenant_id=? ORDER BY id');
        $statement->execute([$tenantId]);
        $roles = $statement->fetchAll(PDO::FETCH_ASSOC);
        echo $this->view->render('pages/user-form', ['title' => $id ? 'Editar usuário' : 'Convidar usuário', 'user' => $user, 'roles' => $roles]);
    }

    public function save(): never
    {
        $this->validateCsrf();
        $id = max(0, (int) Request::post('id', 0));
        $tenantId = $this->currentTenantId();
        $name = mb_substr(trim(strip_tags((string) Request::post('name', ''))), 0, 120);
        $email = mb_strtolower(mb_substr(trim((string) Request::post('email', '')), 0, 190));

        if ($id === 0) {
            $this->inviteUser($name, $email, max(0, (int) Request::post('role_id', 0)));
        }
        if ($id === 1) {
            Flash::set('error', 'O administrador principal não pode ser alterado nesta tela.');
            Response::to('/studio/users');
        }

        $roleId = max(0, (int) Request::post('role_id', 0));
        $status = in_array(Request::post('status'), ['active', 'inactive'], true) ? (string) Request::post('status') : '';
        $actor = Auth::user();
        if ($actor === null) { Response::to('/login'); }
        $roleStatement = Connection::getInstance()->prepare('SELECT slug FROM platform_roles WHERE id=? AND tenant_id=?');
        $roleStatement->execute([$roleId, $tenantId]);
        $roleSlug = $roleStatement->fetchColumn();
        if (!is_string($roleSlug) || $status === '') {
            Flash::set('error', 'Selecione um perfil e uma situação válidos para esta administradora.');
            Response::to('/studio/users/edit/' . $id);
        }

        $pdo = Connection::getInstance();
        try {
            $pdo->beginTransaction();
            $membership = $pdo->prepare('SELECT role_id,status FROM talk_tenant_users WHERE tenant_id=? AND user_id=? FOR UPDATE');
            $membership->execute([$tenantId, $id]);
            $before = $membership->fetch(PDO::FETCH_ASSOC);
            if (!is_array($before)) {
                $pdo->rollBack();
                Flash::set('error', 'Usuário não encontrado nesta administradora.');
                Response::to('/studio/users');
            }
            $pdo->prepare('UPDATE talk_tenant_users SET role=?,role_id=?,status=? WHERE tenant_id=? AND user_id=?')
                ->execute([$roleSlug, $roleId, $status, $tenantId, $id]);
            (new PlatformAudit($pdo))->record($tenantId, (int) $actor->id, 'member.role_status_changed', 'user', $id, [
                'role' => $roleSlug,
                'status' => $status,
            ]);
            $pdo->commit();
            Logger::info('Acesso de usuário da administradora atualizado.', ['record_id' => $id, 'tenant_id' => $tenantId, 'actor_id' => $actor->id]);
            Flash::set('success', 'Usuário salvo com sucesso.');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            Logger::exception($exception);
            Flash::set('error', 'Não foi possível salvar. Verifique se o e-mail já está em uso.');
        }
        Response::to('/studio/users');
    }

    private function inviteUser(string $name, string $email, int $roleId): never
    {
        if (mb_strlen($name) < 2 || filter_var($email, FILTER_VALIDATE_EMAIL) === false || $roleId < 1) {
            Flash::set('error', 'Informe nome, e-mail e perfil do convite.');
            Response::to('/studio/users/create');
        }

        $actor = Auth::user();
        if ($actor === null) { Response::to('/login'); }
        $pdo = Connection::getInstance();
        $tenantId = (new TenantContext($pdo))->currentId((int) $actor->id);
        $role = $pdo->prepare('SELECT slug FROM platform_roles WHERE id=? AND tenant_id=?');
        $role->execute([$roleId, $tenantId]);
        $roleSlug = $role->fetchColumn();
        if (!is_string($roleSlug)) {
            Flash::set('error', 'O perfil selecionado não pertence à administradora ativa.');
            Response::to('/studio/users/create');
        }

        try {
            $pdo->beginTransaction();
            $lookup = $pdo->prepare('SELECT id FROM users WHERE LOWER(email)=? LIMIT 1');
            $lookup->execute([$email]);
            $userId = (int) $lookup->fetchColumn();
            if ($userId < 1) {
                $placeholder = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO users(name,email,password,role,status) VALUES(?,?,?,'user','inactive')")
                    ->execute([$name, $email, $placeholder]);
                $userId = (int) $pdo->lastInsertId();
            }
            $pdo->prepare(
                "INSERT INTO talk_tenant_users(tenant_id,user_id,role,role_id,status,is_default) VALUES(?,?,?,?, 'inactive',0) "
                . "ON DUPLICATE KEY UPDATE role=VALUES(role),role_id=VALUES(role_id),status='inactive'"
            )->execute([$tenantId, $userId, $roleSlug, $roleId]);
            $pdo->commit();

            $invite = (new UserInvitationService($pdo))->create($tenantId, $userId, $roleId, (int) $actor->id);
            $tenant = $pdo->prepare('SELECT name FROM talk_tenants WHERE id=?');
            $tenant->execute([$tenantId]);
            $tenantName = (string) $tenant->fetchColumn();
            $baseUrl = rtrim((string) Config::get('APP_URL', ''), '/');
            if ($baseUrl === '') { throw new \RuntimeException('APP_URL não configurada.'); }
            (new SmtpInvitationMailer())->sendInvitation(
                $email,
                $name,
                $tenantName,
                $baseUrl . '/first-access?token=' . rawurlencode($invite['token']),
                UserInvitationService::DEFAULT_TTL_HOURS
            );
            Logger::info('Convite de usuário enviado.', ['record_id' => $userId, 'tenant_id' => $tenantId, 'actor_id' => $actor->id]);
            Flash::set('success', 'Convite enviado. O usuário definirá a própria senha no primeiro acesso.');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            Logger::exception($exception);
            Flash::set('error', 'Não foi possível criar e enviar o convite.');
        }
        Response::to('/studio/users');
    }

    public function action(): never
    {
        $this->validateCsrf();
        $id = max(0, (int) Request::post('id', 0));
        $action = (string) Request::post('action', '');
        $actor = Auth::user();
        if ($actor === null) { Response::to('/login'); }
        $actorId = (int) $actor->id;
        $tenantId = $this->currentTenantId();
        if ($id < 1 || !in_array($action, ['activate', 'deactivate', 'delete'], true) || (($id === 1 || $id === $actorId) && $action !== 'activate')) {
            Flash::set('error', 'Esta conta não pode receber a ação solicitada.');
            Response::to('/studio/users');
        }
        try {
            $pdo = Connection::getInstance();
            $pdo->beginTransaction();
            $membership = $pdo->prepare('SELECT status FROM talk_tenant_users WHERE tenant_id=? AND user_id=? FOR UPDATE');
            $membership->execute([$tenantId, $id]);
            if ($membership->fetchColumn() === false) {
                $pdo->rollBack();
                Flash::set('error', 'Usuário não encontrado nesta administradora.');
                Response::to('/studio/users');
            }
            $status = $action === 'activate' ? 'active' : 'inactive';
            $pdo->prepare('UPDATE talk_tenant_users SET status=? WHERE tenant_id=? AND user_id=?')
                ->execute([$status, $tenantId, $id]);
            (new PlatformAudit($pdo))->record($tenantId, $actorId, 'member.status_changed', 'user', $id, ['status' => $status]);
            $pdo->commit();
            Logger::info('Ação de acesso na administradora.', ['record_id' => $id, 'action' => $action, 'tenant_id' => $tenantId, 'actor_id' => $actorId]);
            Flash::set('success', 'Usuário atualizado com sucesso.');
        } catch (Throwable $exception) {
            if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
            Logger::exception($exception);
            Flash::set('error', 'Não foi possível atualizar o acesso nesta administradora.');
        }
        Response::to('/studio/users');
    }

    private function currentTenantId(): int
    {
        $actor = Auth::user();
        if ($actor === null) {
            Response::to('/login');
        }
        return (new TenantContext(Connection::getInstance()))->currentId((int) $actor->id);
    }

    private function validateCsrf(): void
    {
        $token = Request::post('_token');
        if (!is_string($token) || !Csrf::validate($token)) {
            Flash::set('error', 'Token de segurança inválido.');
            Response::to('/studio/users');
        }
    }
}
