<?php

declare(strict_types=1);

namespace Moves\Controllers;

use Moves\Boot\Connection;
use Moves\Core\Controller;
use Moves\Core\Csrf;
use Moves\Core\Flash;
use Moves\Core\Logger;
use Moves\Core\Request;
use Moves\Core\Response;
use Moves\Core\Session;
use Moves\Core\Validator;
use Moves\Services\Auth\PasswordRecoveryService;
use Moves\Services\Mail\SmtpRecoveryMailer;
use Throwable;

final class PasswordRecoveryController extends Controller
{
    private const CONTEXT_KEY = 'password_recovery_context';

    public function requestForm(): void
    {
        echo $this->view->render('pages/forgot-password', [
            'title' => 'Recuperar acesso',
            'version' => '0.0.1',
        ]);
    }

    public function requestCode(): void
    {
        $this->requireCsrf('/forgot-password');

        $email = trim((string) Request::post('email', ''));
        $validator = (new Validator())
            ->required('email', $email, 'Informe seu e-mail.')
            ->email('email', $email);
        if ($validator->fails()) {
            foreach ($validator->errors() as $error) {
                Flash::set('error', $error);
            }
            Response::to('/forgot-password');
        }

        try {
            $result = $this->service()->request($email, Request::ip());
            $this->storeContext($result);
            Flash::set('success', 'Se existir uma conta ativa para este e-mail, enviaremos um código de recuperação.');
            Response::to('/password-recovery/code');
        } catch (Throwable $exception) {
            Logger::error('Falha ao processar recuperação de conta.', ['exception' => $exception::class]);
            Flash::set('error', 'Não foi possível enviar o código agora. Tente novamente em alguns instantes.');
            Response::to('/forgot-password');
        }
    }

    public function codeForm(): void
    {
        $context = $this->context();
        if ($context === null) {
            Flash::set('error', 'Solicite um novo código para continuar.');
            Response::to('/forgot-password');
        }

        echo $this->view->render('pages/recovery-code', [
            'title' => !empty($context['verified']) ? 'Código confirmado' : 'Digite o código',
            'version' => '0.0.1',
            'maskedEmail' => (string) $context['masked_email'],
            'verified' => !empty($context['verified']),
            'maxAttempts' => PasswordRecoveryService::MAX_ATTEMPTS,
        ]);
    }

    public function verifyCode(): void
    {
        $this->requireCsrf('/password-recovery/code');
        $context = $this->context();
        if ($context === null) {
            Flash::set('error', 'Solicite um novo código para continuar.');
            Response::to('/forgot-password');
        }

        $code = preg_replace('/\D+/', '', (string) Request::post('code', '')) ?? '';
        if (!preg_match('/^\d{6}$/', $code)) {
            Flash::set('error', 'Informe o código de 6 dígitos.');
            Response::to('/password-recovery/code');
        }

        try {
            $result = $this->service()->verify(
                (int) $context['request_id'],
                (string) $context['email'],
                $code
            );
        } catch (Throwable $exception) {
            Logger::error('Falha ao validar código de recuperação.', ['exception' => $exception::class]);
            Flash::set('error', 'Não foi possível validar o código agora. Tente novamente.');
            Response::to('/password-recovery/code');
        }

        if ($result['status'] === 'verified') {
            $context['verified'] = true;
            Session::set(self::CONTEXT_KEY, $context);
            Csrf::regenerate();
            Flash::set('success', 'Código confirmado com segurança.');
            Response::to('/password-recovery/code?verified=1');
        }

        if ($result['status'] === 'expired') {
            Flash::set('error', 'Este código expirou. Solicite um novo código.');
        } elseif ($result['status'] === 'locked') {
            Flash::set('error', 'Limite de tentativas atingido. Solicite um novo código.');
        } else {
            $remaining = (int) $result['remaining_attempts'];
            Flash::set('error', "Código inválido. Você ainda tem {$remaining} tentativa(s).");
        }
        Response::to('/password-recovery/code');
    }

    public function resendCode(): void
    {
        $this->requireCsrf('/password-recovery/code');
        $context = $this->context();
        if ($context === null) {
            Flash::set('error', 'Informe seu e-mail para solicitar um código.');
            Response::to('/forgot-password');
        }

        try {
            $result = $this->service()->request((string) $context['email'], Request::ip());
            $this->storeContext($result);
            if ($result['rate_limited']) {
                $wait = max(1, (int) $result['retry_after']);
                Flash::set('error', "Aguarde {$wait} segundo(s) antes de solicitar outro código.");
            } else {
                Flash::set('success', 'Se existir uma conta ativa para este e-mail, enviaremos um novo código.');
            }
        } catch (Throwable $exception) {
            Logger::error('Falha ao reenviar código de recuperação.', ['exception' => $exception::class]);
            Flash::set('error', 'Não foi possível reenviar o código agora. Tente novamente.');
        }
        Response::to('/password-recovery/code');
    }

    private function requireCsrf(string $redirect): void
    {
        $token = Request::post('_token');
        if (!is_string($token) || !Csrf::validate($token)) {
            Flash::set('error', 'Sua sessão expirou. Atualize a página e tente novamente.');
            Response::to($redirect);
        }
    }

    private function service(): PasswordRecoveryService
    {
        return new PasswordRecoveryService(Connection::getInstance(), new SmtpRecoveryMailer());
    }

    /** @param array{request_id:int,email:string,masked_email:string,expires_in_minutes:int,rate_limited:bool,retry_after:int} $result */
    private function storeContext(array $result): void
    {
        Session::set(self::CONTEXT_KEY, [
            'request_id' => $result['request_id'],
            'email' => $result['email'],
            'masked_email' => $result['masked_email'],
            'verified' => false,
        ]);
    }

    /** @return array<string,mixed>|null */
    private function context(): ?array
    {
        $context = Session::get(self::CONTEXT_KEY);

        return is_array($context)
            && isset($context['request_id'], $context['email'], $context['masked_email'])
            ? $context
            : null;
    }
}
