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
        $keys = ['app_name', 'site_title', 'site_description', 'contact_email', 'contact_phone', 'social_instagram', 'social_linkedin'];
        $settings = [];
        foreach ($keys as $key) { $settings[$key] = (string) Settings::get($key, ''); }
        $settings['app_name'] = $settings['app_name'] ?: 'Moves';
        echo $this->view->render('pages/settings', [
            'title' => 'Configurações',
            'settings' => $settings,
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
            Response::to('/studio/settings');
        }

        $input = static fn (string $key): string => (string) (Request::has($key) ? Request::post($key, '') : Settings::get($key, ''));
        $values = [
            'app_name' => mb_substr(trim(strip_tags($input('app_name'))), 0, 100),
            'site_title' => mb_substr(trim(strip_tags($input('site_title'))), 0, 160),
            'site_description' => mb_substr(trim(strip_tags($input('site_description'))), 0, 500),
            'contact_email' => mb_substr(trim($input('contact_email')), 0, 190),
            'contact_phone' => mb_substr(trim(strip_tags($input('contact_phone'))), 0, 30),
            'social_instagram' => mb_substr(trim($input('social_instagram')), 0, 500),
            'social_linkedin' => mb_substr(trim($input('social_linkedin')), 0, 500),
        ];
        $validator = new Validator();
        $validator
            ->required('app_name', $values['app_name'], 'Informe o nome da aplicação.')
            ->min('app_name', $values['app_name'], 2)
            ->max('app_name', $values['app_name'], 100);
        if ($values['contact_email'] !== '' && filter_var($values['contact_email'], FILTER_VALIDATE_EMAIL) === false) {
            Flash::set('error', 'Informe um e-mail de contato válido.');
            Response::to('/studio/settings');
        }
        foreach (['social_instagram', 'social_linkedin'] as $urlKey) {
            if ($values[$urlKey] !== '' && filter_var($values[$urlKey], FILTER_VALIDATE_URL) === false) {
                Flash::set('error', 'Informe URLs completas e válidas para as redes sociais.');
                Response::to('/studio/settings');
            }
        }

        if ($validator->fails()) {
            foreach ($validator->errors() as $error) {
                Flash::set('error', $error);
            }

            Response::to('/studio/settings');
        }

        foreach ($values as $name => $value) {
            if (!Settings::set($name, $value)) { Flash::set('error', 'Não foi possível salvar as configurações.'); Response::to('/studio/settings'); }
        }

        Flash::set('success', 'Configurações atualizadas com sucesso.');
        Response::to('/studio/settings');
    }
}
