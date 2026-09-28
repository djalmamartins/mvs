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
use Moves\Services\Mail\SmtpInvitationMailer;
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
    public function legacyProfile(): void
    {
        Response::to('/app/profile', 301);
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
            ]
        );
    }

    /**
     * Exibe a listagem paginada de usuários.
     *
     * @param array<string, string> $data
     */
    public function index(array $data = []): void
    {
        $page = max(
            1,
            (int) ($data['page'] ?? 1)
        );

        $search = mb_substr(trim(strip_tags((string) Request::get('q', ''))), 0, 100);
        $status = in_array(Request::get('status'), ['active', 'inactive'], true) ? (string) Request::get('status') : '';
        $role = in_array(Request::get('role'), ['admin', 'user'], true) ? (string) Request::get('role') : '';
        $where = [];
        $params = [];
        if ($search !== '') { $where[] = '(name LIKE :search OR email LIKE :search)'; $params['search'] = '%' . $search . '%'; }
        if ($status !== '') { $where[] = 'status = :status'; $params['status'] = $status; }
        if ($role !== '') { $where[] = 'role = :role'; $params['role'] = $role; }
        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $pdo = Connection::getInstance();
        $count = $pdo->prepare('SELECT COUNT(*) FROM users' . $whereSql);
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

        $statement = $pdo->prepare('SELECT id,name,email,role,status,created_at FROM users' . $whereSql . ' ORDER BY name ASC LIMIT ' . $pager->limit() . ' OFFSET ' . $pager->offset());
        $statement->execute($params);
        $users = $statement->fetchAll(PDO::FETCH_ASSOC);
        $stats = $pdo->query("SELECT COUNT(*) total,SUM(status='active') active,SUM(status<>'active') inactive,SUM(role='admin') admins FROM users")->fetch(PDO::FETCH_ASSOC) ?: [];

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
        $id = max(0, (int) ($data['id'] ?? 0));
        $user = null;
        if ($id > 0) {
            $statement = Connection::getInstance()->prepare('SELECT id,name,email,role,status,created_at FROM users WHERE id=?');
            $statement->execute([$id]);
            $user = $statement->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($user === null) { Response::to('/studio/users'); }
        }
        $roles = [];
        if ($id === 0) {
            $actor = Auth::user();
            if ($actor === null) { Response::to('/login'); }
            $tenantId = (new TenantContext(Connection::getInstance()))->currentId((int) $actor->id);
            $statement = Connection::getInstance()->prepare('SELECT id,slug,name FROM platform_roles WHERE tenant_id=? ORDER BY id');
            $statement->execute([$tenantId]);
            $roles = $statement->fetchAll(PDO::FETCH_ASSOC);
        }
        echo $this->view->render('pages/user-form', ['title' => $id ? 'Editar usuário' : 'Convidar usuário', 'user' => $user, 'roles' => $roles]);
    }

    public function save(): never
    {
        $this->validateCsrf();
        $id = max(0, (int) Request::post('id', 0));
        $name = mb_substr(trim(strip_tags((string) Request::post('name', ''))), 0, 120);
        $email = mb_strtolower(mb_substr(trim((string) Request::post('email', '')), 0, 190));

        if ($id === 0) {
            $this->inviteUser($name, $email, max(0, (int) Request::post('role_id', 0)));
        }

        $role = in_array(Request::post('role'), ['admin', 'user'], true) ? (string) Request::post('role') : 'user';
        $status = in_array(Request::post('status'), ['active', 'inactive'], true) ? (string) Request::post('status') : 'inactive';
        $password = (string) Request::post('password', '');
        if (mb_strlen($name) < 2 || filter_var($email, FILTER_VALIDATE_EMAIL) === false || ($password !== '' && strlen($password) < 8)) {
            Flash::set('error', 'Revise nome, e-mail e senha.');
            Response::to('/studio/users/edit/' . $id);
        }
        if ($id === 1) { $role = 'admin'; $status = 'active'; }
        try {
            $pdo = Connection::getInstance();
            $sql = 'UPDATE users SET name=?,email=?,role=?,status=?' . ($password !== '' ? ',password=?' : '') . ' WHERE id=?';
            $values = [$name, $email, $role, $status];
            if ($password !== '') { $values[] = password_hash($password, PASSWORD_DEFAULT); }
            $values[] = $id;
            $pdo->prepare($sql)->execute($values);
            Logger::info('Usuário administrativo salvo.', ['record_id' => $id, 'actor_id' => Auth::user()?->id]);
            Flash::set('success', 'Usuário salvo com sucesso.');
        } catch (Throwable $exception) {
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
        $actorId = (int) Auth::user()?->id;
        if ($id < 1 || !in_array($action, ['activate', 'deactivate', 'delete'], true) || (($id === 1 || $id === $actorId) && $action !== 'activate')) {
            Flash::set('error', 'Esta conta não pode receber a ação solicitada.');
            Response::to('/studio/users');
        }
        try {
            $pdo = Connection::getInstance();
            if ($action === 'delete') { $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]); }
            else { $pdo->prepare('UPDATE users SET status=? WHERE id=?')->execute([$action === 'activate' ? 'active' : 'inactive', $id]); }
            Logger::info('Ação administrativa em usuário.', ['record_id' => $id, 'action' => $action, 'actor_id' => $actorId]);
            Flash::set('success', 'Usuário atualizado com sucesso.');
        } catch (Throwable $exception) {
            Logger::exception($exception);
            Flash::set('error', 'A conta possui vínculos e não pode ser excluída. Desative o acesso.');
        }
        Response::to('/studio/users');
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
