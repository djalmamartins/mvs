<?php

declare(strict_types=1);

namespace Moves\Controllers;

use Moves\Core\Controller;
use Moves\Core\Csrf;
use Moves\Core\Flash;
use Moves\Core\Logger;
use Moves\Core\Request;
use Moves\Core\Response;
use Moves\Services\Auth\PasswordPolicy;
use Moves\Services\Auth\UserInvitationService;
use Throwable;

final class InvitationController extends Controller
{
    public function acceptForm(): void
    {
        $token = strtolower(trim((string) Request::get('token', '')));
        $invitation = (new UserInvitationService())->valid($token);

        if ($invitation === null) {
            echo $this->view->render('pages/invitation-invalid', [
                'title' => 'Convite indisponível',
                'version' => '0.0.1',
            ]);
            return;
        }

        echo $this->view->render('pages/invitation-accept', [
            'title' => 'Ativar acesso',
            'version' => '0.0.1',
            'token' => $token,
            'invitation' => $invitation,
            'minLength' => PasswordPolicy::MIN_LENGTH,
            'maxLength' => PasswordPolicy::MAX_LENGTH,
        ]);
    }

    public function accept(): never
    {
        $token = strtolower(trim((string) Request::post('token', '')));
        $csrf = Request::post('_token');
        if (!is_string($csrf) || !Csrf::validate($csrf)) {
            Flash::set('error', 'Sua sessão expirou. Abra novamente o link do convite.');
            Response::to('/login');
        }

        $service = new UserInvitationService();
        if ($service->valid($token) === null) {
            Flash::set('error', 'Este convite expirou, foi revogado ou já foi utilizado.');
            Response::to('/login');
        }

        $password = (string) Request::post('password', '');
        $confirmation = (string) Request::post('password_confirmation', '');
        $errors = PasswordPolicy::errors($password);
        if (!hash_equals($password, $confirmation)) {
            $errors[] = 'A confirmação deve ser igual à senha.';
        }
        if ($errors !== []) {
            foreach ($errors as $error) {
                Flash::set('error', $error);
            }
            Response::to('/first-access?token=' . rawurlencode($token));
        }

        try {
            if (!$service->accept($token, password_hash($password, PASSWORD_DEFAULT))) {
                Flash::set('error', 'Este convite não está mais disponível.');
                Response::to('/login');
            }
        } catch (Throwable $exception) {
            Logger::error('Falha ao aceitar convite de usuário.', ['exception' => $exception::class]);
            Flash::set('error', 'Não foi possível ativar seu acesso agora. Tente novamente.');
            Response::to('/first-access?token=' . rawurlencode($token));
        }

        Csrf::regenerate();
        Flash::set('success', 'Acesso ativado. Entre com sua nova senha.');
        Response::to('/login');
    }
}
