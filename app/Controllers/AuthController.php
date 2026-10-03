<?php

declare(strict_types=1);

namespace Moves\Controllers;

use Moves\Boot\Connection;
use Moves\Core\Auth;
use Moves\Core\Controller;
use Moves\Core\Csrf;
use Moves\Core\Flash;
use Moves\Core\Logger;
use Moves\Core\LoginThrottle;
use Moves\Core\Request;
use Moves\Core\Response;
use Moves\Core\Session;
use Moves\Core\Validator;
use Moves\Models\User;
use Moves\Modules\Erp\Security\MfaChallengeService;
use Moves\Modules\Erp\Security\MfaEnrollmentRepository;
use Moves\Modules\Erp\Security\MfaRuntimeConfig;
use Moves\Modules\Erp\Security\TotpVerifier;
use Moves\Services\Auth\MfaRecoveryCodeService;
use Moves\Services\Platform\PlatformAudit;
use Moves\Services\Platform\TenantContext;

/**
 * Moves | Authentication Controller
 *
 * Gerencia login e logout dos usuários.
 *
 * @author Djalma Martins
 * @package Moves\Controllers
 */
final class AuthController extends Controller
{
    private const MFA_PENDING_KEY = 'auth_mfa_pending';
    private const MFA_PENDING_TTL = 300;
    public function entry(): void
    {
        Response::to(Auth::check() ? '/day' : '/login');
    }

    public function login(): void
    {
        echo $this->view->render('pages/login', [
            'title' => 'Entrar na Moves',
            'version' => '0.0.1',
        ]);
    }

    public function authenticate(): void
    {
        $token = Request::post('_token');

        if (!is_string($token) || !Csrf::validate($token)) {
            Flash::set('error', 'Token de segurança inválido.');
            Response::to('/login');
        }

        $email = trim((string) Request::post('email', ''));
        $password = (string) Request::post('password', '');
        $validator = new Validator();

        $validator
            ->required('email', $email, 'Informe seu e-mail.')
            ->email('email', $email)
            ->required('password', $password, 'Informe sua senha.');

        if ($validator->fails()) {
            foreach ($validator->errors() as $error) {
                Flash::set('error', $error);
            }
            Response::to('/login');
        }

        $ip = Request::ip();

        if (LoginThrottle::blocked($email, $ip)) {
            Flash::set('error', 'E-mail ou senha inválidos. Tente novamente mais tarde.');
            Response::to('/login');
        }

        $user = Auth::verifyCredentials($email, $password);
        if (!$user instanceof User) {
            LoginThrottle::recordFailure($email, $ip);
            Logger::warning('Falha de autenticação.', [
                'login_key' => hash('sha256', strtolower($email) . '|' . $ip),
            ]);
            usleep(random_int(100000, 250000));
            Flash::set('error', 'E-mail ou senha inválidos.');
            Response::to('/login');
        }

        try {
            $pdo = Connection::getInstance();
            $userId = (int) ($user->id ?? 0);
            if (MfaEnrollmentRepository::hasEnabledTotp($pdo, $userId)) {
                $repository = new MfaEnrollmentRepository($pdo, MfaRuntimeConfig::fromEnvironment()->cipher());
                if (!$repository->hasActiveTotp($userId)) {
                    throw new \RuntimeException('Active MFA enrollment is not readable.');
                }
                Session::set(self::MFA_PENDING_KEY, [
                    'user_id' => $userId,
                    'email' => strtolower($email),
                    'ip' => $ip,
                    'issued_at' => time(),
                ]);
                Csrf::regenerate();
                Response::to('/login/2fa');
            }
        } catch (\Throwable $exception) {
            Logger::error('Falha ao consultar estado MFA durante o login.', [
                'user_id' => (int) ($user->id ?? 0),
                'exception' => $exception::class,
            ]);
            Flash::set('error', 'Não foi possível validar a segurança da conta. Tente novamente.');
            Response::to('/login');
        }

        if (!Auth::establishSession($user)) {
            Logger::warning('Sessão recusada após validação de credenciais.', [
                'user_id' => (int) ($user->id ?? 0),
            ]);
            Flash::set('error', 'Não foi possível concluir o login.');
            Response::to('/login');
        }

        Session::set(MfaController::RECOMMENDATION_KEY, true);
        LoginThrottle::clear($email, $ip);
        Csrf::regenerate();
        try {
            $pdo = Connection::getInstance();
            $tenantId = (new TenantContext($pdo))->currentId((int) $user->id);
            (new PlatformAudit($pdo))->record($tenantId, (int) $user->id, 'auth.login', 'user', (int) $user->id, ['ip_hash' => hash('sha256', $ip)]);
        } catch (\Throwable) {
            // Authentication also supports platform operators without a tenant.
        }
        Flash::set('success', 'Login realizado com sucesso.');
        Response::to('/day');
    }

    public function mfaChallenge(): void
    {
        $pending = $this->pendingMfa();
        if ($pending === null) {
            Flash::set('error', 'Sua verificação expirou. Entre novamente.');
            Response::to('/login');
        }

        echo $this->view->render('pages/mfa-challenge', [
            'title' => 'Verificação em duas etapas',
            'version' => '0.0.1',
            'setup' => null,
        ]);
    }

    public function verifyMfaChallenge(): void
    {
        $token = Request::post('_token');
        if (!is_string($token) || !Csrf::validate($token)) {
            Flash::set('error', 'Token de segurança inválido.');
            Response::to('/login/2fa');
        }

        $pending = $this->pendingMfa();
        if ($pending === null) {
            Session::remove(self::MFA_PENDING_KEY);
            Flash::set('error', 'Sua verificação expirou. Entre novamente.');
            Response::to('/login');
        }

        $user = (new User())->findById((int) $pending['user_id']);
        if (!$user instanceof User || (string) ($user->status ?? '') !== 'active') {
            Session::remove(self::MFA_PENDING_KEY);
            Flash::set('error', 'Não foi possível concluir o login.');
            Response::to('/login');
        }

        $code = trim((string) Request::post('code', ''));
        $valid = false;
        try {
            $pdo = Connection::getInstance();
            if (preg_match('/^\\d{6}$/D', $code) === 1) {
                $config = MfaRuntimeConfig::fromEnvironment();
                $repository = new MfaEnrollmentRepository($pdo, $config->cipher());
                if ($repository->hasActiveTotp((int) $user->id)) {
                    $valid = (new MfaChallengeService($repository, new TotpVerifier()))
                        ->verifyTotp((int) $user->id, $code);
                }
            } else {
                $valid = (new MfaRecoveryCodeService($pdo))->consume((int) $user->id, $code);
            }
        } catch (\Throwable $exception) {
            Logger::error('Falha fechada no challenge MFA.', [
                'user_id' => (int) ($user->id ?? 0),
                'exception' => $exception::class,
            ]);
        }

        if (!$valid) {
            Logger::warning('Challenge MFA inválido.', ['user_id' => (int) $user->id]);
            Flash::set('error', 'Código de verificação inválido.');
            Response::to('/login/2fa');
        }

        if (!Auth::establishSession($user)) {
            Session::remove(self::MFA_PENDING_KEY);
            Flash::set('error', 'Não foi possível concluir o login.');
            Response::to('/login');
        }

        Session::remove(self::MFA_PENDING_KEY);
        Session::remove(MfaController::RECOMMENDATION_KEY);
        LoginThrottle::clear((string) $pending['email'], (string) $pending['ip']);
        Csrf::regenerate();
        $this->recordLoginAudit($user, (string) $pending['ip']);
        Flash::set('success', 'Login realizado com sucesso.');
        Response::to('/day');
    }

    /** @return array{user_id:int,email:string,ip:string,issued_at:int}|null */
    private function pendingMfa(): ?array
    {
        $pending = Session::get(self::MFA_PENDING_KEY);
        if (!is_array($pending)
            || !isset($pending['user_id'], $pending['email'], $pending['ip'], $pending['issued_at'])
            || (int) $pending['user_id'] <= 0
            || (time() - (int) $pending['issued_at']) > self::MFA_PENDING_TTL
        ) {
            return null;
        }

        return [
            'user_id' => (int) $pending['user_id'],
            'email' => (string) $pending['email'],
            'ip' => (string) $pending['ip'],
            'issued_at' => (int) $pending['issued_at'],
        ];
    }

    private function recordLoginAudit(User $user, string $ip): void
    {
        try {
            $pdo = Connection::getInstance();
            $tenantId = (new TenantContext($pdo))->currentId((int) $user->id);
            (new PlatformAudit($pdo))->record($tenantId, (int) $user->id, 'auth.login', 'user', (int) $user->id, ['ip_hash' => hash('sha256', $ip)]);
        } catch (\Throwable) {
            // Authentication also supports platform operators without a tenant.
        }
    }

    public function logout(): void
    {
        $token = Request::post('_token');

        if (!is_string($token) || !Csrf::validate($token)) {
            Flash::set('error', 'Token de segurança inválido.');
            Response::to('/day');
        }

        $user = Auth::user();
        if ($user !== null) {
            try {
                $pdo = Connection::getInstance();
                $tenantId = (new TenantContext($pdo))->currentId((int) $user->id);
                (new PlatformAudit($pdo))->record($tenantId, (int) $user->id, 'auth.logout', 'user', (int) $user->id);
            } catch (\Throwable) {
                // Logout must remain available during migrations or support access.
            }
        }
        Auth::logout();
        Flash::set('success', 'Sessão encerrada com sucesso.');
        Response::to('/login');
    }
}
