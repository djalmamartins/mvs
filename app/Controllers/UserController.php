<?php

declare(strict_types=1);

namespace Moves\Controllers;

use Moves\Core\Auth;
use Moves\Core\Controller;
use Moves\Models\User;
use MovesCode\Pager\Pager;

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
     * Exibe a listagem paginada de usuários.
     */
    public function index(): void
    {
        $page = max(
            1,
            (int) ($_GET['page'] ?? 1)
        );

        $total = (new User())
            ->find()
            ->count();

        $pager = new Pager(
            '/admin/users?page={page}'
        );

        $pager->pager(
            $total,
            10,
            $page
        );

        $users = (new User())
            ->find()
            ->order('name ASC')
            ->limit($pager->limit())
            ->offset($pager->offset())
            ->fetch(true);

        echo $this->view->render(
            'pages/users',
            [
                'title' => 'Usuários',
                'users' => $users ?? [],
                'pagination' => $pager->render(),
            ]
        );
    }
}