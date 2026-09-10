<?php

declare(strict_types=1);

namespace Moves\Controllers;

use Moves\Core\Controller;
use Moves\Core\Csrf;
use Moves\Core\Flash;
use Moves\Core\Request;
use Moves\Core\Response;
use Moves\Core\Validator;

/**
 * Moves | Form Test Controller
 *
 * Valida o fluxo completo de formulário da aplicação.
 *
 * @author Djalma Martins
 * @package Moves\Controllers
 */
final class FormTest extends Controller
{
    /**
     * Exibe o formulário de teste.
     */
    public function index(): void
    {
        echo $this->view->render(
            'pages/form-test',
            [
                'title' => 'Teste de Formulário',
            ]
        );
    }

    /**
     * Processa o formulário de teste.
     */
    public function store(): void
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

            Response::to('/form-test');
        }

        $name = Request::post(
            'name',
            ''
        );

        $email = Request::post(
            'email',
            ''
        );

        $validator = new Validator();

        $validator
            ->required(
                'name',
                $name,
                'Informe seu nome.'
            )
            ->required(
                'email',
                $email,
                'Informe seu e-mail.'
            )
            ->email(
                'email',
                $email
            );

        if ($validator->fails()) {
            foreach ($validator->errors() as $error) {
                Flash::set(
                    'error',
                    $error
                );
            }

            Response::to('/form-test');
        }

        Flash::set(
            'success',
            'Formulário enviado com sucesso.'
        );

        Response::to('/form-test');
    }
}
