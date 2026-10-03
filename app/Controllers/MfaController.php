<?php

declare(strict_types=1);

namespace Moves\Controllers;

use Moves\Boot\Connection;
use Moves\Core\Auth;
use Moves\Core\Controller;
use Moves\Core\Csrf;
use Moves\Core\Flash;
use Moves\Core\Logger;
use Moves\Core\Request;
use Moves\Core\Response;
use Moves\Core\Session;
use Moves\Modules\Erp\Security\MfaEnrollmentRepository;
use Moves\Modules\Erp\Security\MfaRuntimeConfig;
use Moves\Modules\Erp\Security\TotpVerifier;
use Moves\Services\Auth\MfaSetupService;
use Throwable;

final class MfaController extends Controller
{
    public const RECOMMENDATION_KEY = 'mfa_recommendation_pending';
    private const RECOVERY_CODES_KEY = 'mfa_recovery_codes_once';

    public function index(): void
    {
        $user = Auth::user();
        if ($user === null) {
            Response::to('/login');
        }

        $setup = null;
        $enabled = false;
        $available = true;
        try {
            $service = $this->service();
            $enabled = $service->enabled((int) $user->id);
            if (!$enabled) {
                $setup = $service->pending((int) $user->id, (string) $user->email);
            }
        } catch (Throwable $exception) {
            $available = false;
            Logger::error('Configuração MFA indisponível.', ['exception' => $exception::class]);
        }

        $recoveryCodes = Session::get(self::RECOVERY_CODES_KEY, []);
        Session::remove(self::RECOVERY_CODES_KEY);
        echo $this->view->render('pages/two-factor', [
            'title' => 'Autenticação em dois fatores',
            'available' => $available,
            'enabled' => $enabled,
            'setup' => $setup,
            'recoveryCodes' => is_array($recoveryCodes) ? $recoveryCodes : [],
        ]);
    }

    public function start(): void
    {
        $this->requireCsrf();
        $user = Auth::user();
        if ($user === null) {
            Response::to('/login');
        }
        try {
            $this->service()->begin((int) $user->id, (string) $user->email);
            Flash::set('success', 'Leia o QR Code e confirme o primeiro código para ativar o 2FA.');
        } catch (Throwable $exception) {
            Logger::error('Falha ao iniciar MFA.', ['user_id' => (int) $user->id, 'exception' => $exception::class]);
            Flash::set('error', 'Não foi possível iniciar o 2FA. Verifique a configuração segura do ambiente.');
        }
        Session::remove(self::RECOMMENDATION_KEY);
        Response::to('/profile/security/2fa');
    }

    public function confirm(): void
    {
        $this->requireCsrf();
        $user = Auth::user();
        if ($user === null) {
            Response::to('/login');
        }
        $code = preg_replace('/\D+/', '', (string) Request::post('code', '')) ?? '';
        if (!preg_match('/^\d{6}$/', $code)) {
            Flash::set('error', 'Informe o código de 6 dígitos do autenticador.');
            Response::to('/profile/security/2fa');
        }
        try {
            $recoveryCodes = $this->service()->confirm((int) $user->id, $code);
            if ($recoveryCodes === null) {
                Flash::set('error', 'Código inválido ou expirado. Confira o relógio do dispositivo e tente novamente.');
                Response::to('/profile/security/2fa');
            }
            Session::set(self::RECOVERY_CODES_KEY, $recoveryCodes);
            Csrf::regenerate();
            Flash::set('success', 'Autenticação em dois fatores ativada. Guarde os códigos de recuperação agora.');
        } catch (Throwable $exception) {
            Logger::error('Falha ao confirmar MFA.', ['user_id' => (int) $user->id, 'exception' => $exception::class]);
            Flash::set('error', 'Não foi possível ativar o 2FA agora. Tente novamente.');
        }
        Session::remove(self::RECOMMENDATION_KEY);
        Response::to('/profile/security/2fa');
    }

    public function dismissRecommendation(): void
    {
        $this->requireCsrf('/day');
        Session::remove(self::RECOMMENDATION_KEY);
        Response::to('/day');
    }

    private function requireCsrf(string $redirect = '/profile/security/2fa'): void
    {
        $token = Request::post('_token');
        if (!is_string($token) || !Csrf::validate($token)) {
            Flash::set('error', 'Sua sessão expirou. Atualize a página e tente novamente.');
            Response::to($redirect);
        }
    }

    private function service(): MfaSetupService
    {
        $pdo = Connection::getInstance();
        $repository = new MfaEnrollmentRepository($pdo, MfaRuntimeConfig::fromEnvironment()->cipher());

        return new MfaSetupService($pdo, $repository, new TotpVerifier());
    }
}
