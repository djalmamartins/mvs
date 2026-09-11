<?php

declare(strict_types=1);

namespace Moves\Controllers;

use Moves\Core\Auth;
use Moves\Core\Controller;
use Moves\Core\Csrf;
use Moves\Core\Flash;
use Moves\Core\Request;
use Moves\Core\Response;
use Moves\Core\Validator;

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
    /**
     * Exibe o formulário de login.
     */
    public function login(): void
    {
        echo $this->view->render(
            'pages/login',
            [
                'title' => 'Entrar',
            ]
        );
    }

    /**
     * Processa o formulário de login.
     */
    public function authenticate(): void
    {
        $token = Request::post('_token');

        if (
            !is_string($token)
            || !Csrf::validate($token)
        ) {
            Flash::set(
                'error',
                'Token de segurança inválido.'
            );

            Response::to('/login');
        }

        $email = trim(
            (string) Request::post('email', '')
        );

        $password = (string) Request::post(
            'password',
            ''
        );

        $validator = new Validator();

        $validator
            ->required(
                'email',
                $email,
                'Informe seu e-mail.'
            )
            ->email(
                'email',
                $email
            )
            ->required(
                'password',
                $password,
                'Informe sua senha.'
            );

        if ($validator->fails()) {
            foreach ($validator->errors() as $error) {
                Flash::set(
                    'error',
                    $error
                );
            }

            Response::to('/login');
        }

        if (!Auth::attempt($email, $password)) {
            Flash::set(
                'error',
                'E-mail ou senha inválidos.'
            );

            Response::to('/login');
        }

        Flash::set(
            'success',
            'Login realizado com sucesso.'
        );

        Response::to('/');
    }

    /**
     * Encerra a sessão do usuário autenticado.
     */
    public function logout(): void
    {
        $token = Request::post('_token');

        if (
            !is_string($token)
            || !Csrf::validate($token)
        ) {
            Flash::set(
                'error',
                'Token de segurança inválido.'
            );

            Response::to('/');
        }

        Auth::logout();

        Flash::set(
            'success',
            'Sessão encerrada com sucesso.'
        );

        Response::to('/login');
    }
}
