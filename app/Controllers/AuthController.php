<?php

declare(strict_types=1);

namespace Moves\Controllers;

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
    public function login(): void
    {
        echo $this->view->render('pages/login', ['title' => 'Entrar']);
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

        // Credential verification is intentionally separated from session grant.
        // MFA policy/challenge is enforced at this boundary for sensitive roles.
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

        if (!Auth::establishSession($user)) {
            Logger::warning('Sessão recusada após validação de credenciais.', [
                'user_id' => (int) ($user->id ?? 0),
            ]);
            Flash::set('error', 'Não foi possível concluir o login.');
            Response::to('/login');
        }

        LoginThrottle::clear($email, $ip);
        Csrf::regenerate();
        Flash::set('success', 'Login realizado com sucesso.');
        Response::to('/app');
    }

    public function logout(): void
    {
        $token = Request::post('_token');

        if (!is_string($token) || !Csrf::validate($token)) {
            Flash::set('error', 'Token de segurança inválido.');
            Response::to('/app');
        }

        Auth::logout();
        Flash::set('success', 'Sessão encerrada com sucesso.');
        Response::to('/login');
    }
}
