<?php

declare(strict_types=1);

namespace Moves\Controllers;

use Moves\Core\Controller;
use Moves\Core\Csrf;
use Moves\Core\Flash;
use Moves\Core\Request;
use Moves\Core\Response;
use Moves\Core\Settings;
use Moves\Core\Validator;

/**
 * Moves | Settings Controller
 *
 * Gerencia as configurações administrativas persistidas.
 *
 * @author Djalma Martins
 * @package Moves\Controllers
 */
final class SettingsController extends Controller
{
    /**
     * Exibe o formulário de configurações.
     */
    public function index(): void
    {
        echo $this->view->render('pages/settings', [
            'title' => 'Configurações',
            'appName' => Settings::get('app_name', 'Moves'),
        ]);
    }

    /**
     * Valida e persiste as configurações editáveis.
     */
    public function update(): void
    {
        $token = Request::post('_token');

        if (!is_string($token) || !Csrf::validate($token)) {
            Flash::set('error', 'Token de segurança inválido.');
            Response::to('/admin/settings');
        }

        $appName = trim((string) Request::post('app_name', ''));
        $validator = new Validator();
        $validator
            ->required('app_name', $appName, 'Informe o nome da aplicação.')
            ->min('app_name', $appName, 2)
            ->max('app_name', $appName, 100);

        if ($validator->fails()) {
            foreach ($validator->errors() as $error) {
                Flash::set('error', $error);
            }

            Response::to('/admin/settings');
        }

        if (!Settings::set('app_name', $appName)) {
            Flash::set('error', 'Não foi possível salvar as configurações.');
            Response::to('/admin/settings');
        }

        Flash::set('success', 'Configurações atualizadas com sucesso.');
        Response::to('/admin/settings');
    }
}
