<?php

declare(strict_types=1);

namespace Moves\Controllers;

use Moves\Core\Auth;
use Moves\Core\Controller;
use Moves\Models\User;

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
     * Exibe a listagem de usuários.
     */
    public function index(): void
    {
        $users = (new \Moves\Models\User())
            ->find()
            ->order('name ASC')
            ->fetch(true);

        echo $this->view->render(
            'pages/users',
            [
                'title' => 'Usuários',
                'users' => $users ?? [],
            ]
        );
    }
}