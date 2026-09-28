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
use Moves\Core\Validator;
use Moves\Models\User;
use Moves\Modules\Erp\Security\MfaChallengeService;
use Moves\Modules\Erp\Security\MfaEnrollmentRepository;
use Moves\Modules\Erp\Security\MfaLoginGate;
use Moves\Modules\Erp\Security\MfaRequirementPolicy;
use Moves\Modules\Erp\Security\MfaRuntimeConfig;
use Moves\Modules\Erp\Security\TotpVerifier;
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
    public function entry(): void
    {
        Response::to(Auth::check() ? '/app' : '/login');
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

        if (!$this->mfaAllowsSession($user)) {
            LoginThrottle::recordFailure($email, $ip);
            Logger::warning('MFA recusou concessão de sessão.', [
                'user_id' => (int) ($user->id ?? 0),
            ]);
            Flash::set('error', 'Não foi possível concluir o login.');
            Response::to('/login');
        }

        if (!Auth::establishSession($user)) {
            Logger::warning('Sessão recusada após validação de credenciais.', [
                'user_id' => (int) ($user->id ?? 0),
            ]);
            Flash::set('error', 'Não foi possível concluir o login.');
            Response::to('/login');
        }

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
        Response::to('/app');
    }

    private function mfaAllowsSession(User $user): bool
    {
        $policy = new MfaRequirementPolicy();
        $role = isset($user->role) ? (string) $user->role : null;

        if (!$policy->requiresMfa($role)) {
            return true;
        }

        try {
            $config = MfaRuntimeConfig::fromEnvironment();
            $repository = new MfaEnrollmentRepository(Connection::getInstance(), $config->cipher());
            $gate = new MfaLoginGate(
                $policy,
                new MfaChallengeService($repository, new TotpVerifier())
            );
            $totp = Request::post('totp_code');

            return $gate->canEstablishSession(
                (int) ($user->id ?? 0),
                $role,
                is_string($totp) ? $totp : null
            );
        } catch (\Throwable $exception) {
            Logger::error('Falha fechada na validação MFA.', [
                'user_id' => (int) ($user->id ?? 0),
                'exception' => $exception::class,
            ]);

            return false;
        }
    }

    public function logout(): void
    {
        $token = Request::post('_token');

        if (!is_string($token) || !Csrf::validate($token)) {
            Flash::set('error', 'Token de segurança inválido.');
            Response::to('/app');
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
