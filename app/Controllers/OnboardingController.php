<?php

declare(strict_types=1);

namespace Moves\Controllers;

use Moves\Boot\Connection;
use Moves\Core\Auth;
use Moves\Core\Controller;
use Moves\Core\Csrf;
use Moves\Core\Flash;
use Moves\Core\Request;
use Moves\Core\Response;
use Moves\Core\Session;
use Moves\Models\User;
use Moves\Services\Platform\CompanyService;
use Moves\Services\Platform\TenantContext;
use Throwable;

final class OnboardingController extends Controller
{
    private const KEY = 'platform_onboarding';

    public function index(): void
    {
        $step = max(1, min(4, (int) Request::get('step', 1)));
        echo $this->view->render('pages/onboarding', [
            'title' => 'Começar no Moves',
            'step' => $step,
            'data' => Session::get(self::KEY, []),
        ]);
    }

    public function save(): void
    {
        if (!Csrf::validate((string) Request::post('_token', ''))) {
            Flash::set('error', 'Token de segurança inválido.');
            Response::to('/onboarding');
        }
        $step = max(1, min(4, (int) Request::post('step', 1)));
        $data = Session::get(self::KEY, []);
        $data = is_array($data) ? $data : [];
        if ($step === 1) {
            $email = strtolower(trim((string) Request::post('email', '')));
            $password = (string) Request::post('password', '');
            if (trim((string) Request::post('name', '')) === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10) {
                Flash::set('error', 'Informe nome, e-mail válido e senha com pelo menos 10 caracteres.');
                Response::to('/onboarding?step=1');
            }
            $data['account'] = ['name' => trim((string) Request::post('name')), 'email' => $email, 'password_hash' => password_hash($password, PASSWORD_DEFAULT)];
        } elseif ($step === 2) {
            $data['company'] = ['name' => trim((string) Request::post('company_name', '')), 'legal_name' => trim((string) Request::post('legal_name', '')), 'tax_id' => (string) Request::post('tax_id', ''), 'email' => trim((string) Request::post('company_email', ''))];
            if ($data['company']['name'] === '' || $data['company']['legal_name'] === '') {
                Flash::set('error', 'Informe a administradora.'); Response::to('/onboarding?step=2');
            }
        } elseif ($step === 3) {
            $products = Request::post('products', []);
            $data['products'] = is_array($products) ? array_values(array_intersect($products, ['talk','erp','support','cms','studio'])) : [];
            if ($data['products'] === []) {
                Flash::set('error', 'Selecione ao menos um produto.'); Response::to('/onboarding?step=3');
            }
        } else {
            $this->complete($data);
        }
        Session::set(self::KEY, $data);
        Response::to('/onboarding?step=' . min(4, $step + 1));
    }

    /** @param array<string,mixed> $data */
    private function complete(array $data): never
    {
        if (!isset($data['account'], $data['company'], $data['products']) || !is_array($data['account']) || !is_array($data['company']) || !is_array($data['products'])) {
            Flash::set('error', 'Complete as etapas anteriores.'); Response::to('/onboarding');
        }
        $pdo = Connection::getInstance();
        $pdo->beginTransaction();
        try {
            $existing = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email=?');
            $existing->execute([(string) $data['account']['email']]);
            if ((int) $existing->fetchColumn() > 0) {
                throw new \InvalidArgumentException('Este e-mail já está cadastrado.');
            }
            $insert = $pdo->prepare("INSERT INTO users(name,email,password,status,role) VALUES(?,?,?,'active','admin')");
            $insert->execute([(string) $data['account']['name'], (string) $data['account']['email'], (string) $data['account']['password_hash']]);
            $userId = (int) $pdo->lastInsertId();
            $tenantId = (new CompanyService($pdo))->create($data['company'], $userId, $data['products']);
            $pdo->prepare("INSERT INTO platform_tenant_settings(tenant_id,setting_key,setting_value,updated_by) VALUES(?,'onboarding.completed_at',NOW(),?)")
                ->execute([$tenantId, $userId]);
            $pdo->commit();
            $user = (new User())->findById($userId);
            if (!$user instanceof User || !Auth::establishSession($user)) {
                throw new \RuntimeException('Conta criada, mas a sessão não pôde ser iniciada.');
            }
            (new TenantContext($pdo))->switch($userId, $tenantId);
            Session::remove(self::KEY);
            Flash::set('success', 'Sua administradora está pronta.');
            Response::to('/day');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            Flash::set('error', $exception instanceof \InvalidArgumentException ? $exception->getMessage() : 'Não foi possível concluir o cadastro.');
            Response::to('/onboarding?step=4');
        }
    }
}
