<?php

declare(strict_types=1);

namespace Moves\Controllers;

use Moves\Core\Controller;
use Moves\Core\Auth;
use Moves\Core\Csrf;
use Moves\Core\Diagnostics;
use Moves\Core\Flash;
use Moves\Core\Config;
use Moves\Core\LogReader;
use Moves\Core\Logger;
use Moves\Core\Request;
use Moves\Core\Response;
use Moves\Core\Settings;
use Moves\Models\User;
use Moves\Boot\Connection;

/** Provides the initial Moves Studio dashboard. */
final class StudioController extends Controller
{
    public function dashboard(): void
    {
        $checks = Diagnostics::run();
        $activity = (new LogReader())->read('', '', 1, 10);
        $contentCounts = [];
        foreach (['page', 'project', 'article', 'media', 'highlight', 'testimonial', 'faq'] as $type) {
            if ($type === 'media') {
                $contentCounts[$type] = (int) Connection::getInstance()->query('SELECT COUNT(*) FROM studio_media')->fetchColumn();
                continue;
            }
            $statement = Connection::getInstance()->prepare('SELECT COUNT(*) FROM studio_content WHERE type=? AND deleted_at IS NULL');
            $statement->execute([$type]);
            $contentCounts[$type] = (int) $statement->fetchColumn();
        }
        $publishedStatement = Connection::getInstance()->prepare(
            'SELECT COUNT(*) FROM studio_content WHERE type = ? AND status = ? AND deleted_at IS NULL'
        );
        $publishedStatement->execute(['article', 'published']);
        $publishedArticles = (int) $publishedStatement->fetchColumn();
        $proposalCount = (int) Connection::getInstance()
            ->query('SELECT COUNT(*) FROM proposals')
            ->fetchColumn();

        echo $this->view->render('pages/dashboard', [
            'title' => 'Dashboard',
            'appName' => (string) Settings::get('app_name', 'Moves'),
            'userCount' => (new User())->find()->count(),
            'checks' => $checks,
            'healthyChecks' => count(array_filter(
                $checks,
                static fn (array $check): bool => $check['ok']
            )),
            'environment' => Config::environment(),
            'version' => $this->applicationVersion(),
            'activity' => array_slice($activity['entries'], 0, 5),
            'contentCounts' => $contentCounts,
            'publishedArticles' => $publishedArticles,
            'proposalCount' => $proposalCount,
        ]);
    }

    public function create(): void
    {
        echo $this->view->render('pages/create', [
            'title' => 'Criar',
        ]);
    }

    public function versions(): void
    {
        $pdo = Connection::getInstance();
        if (Request::isMethod('POST')) {
            $this->validateCsrf('/studio/versions');
            $version = trim((string) Request::post('version', ''));
            $name = mb_substr(trim(strip_tags((string) Request::post('name', ''))), 0, 120);
            $notes = mb_substr(trim(strip_tags((string) Request::post('notes', ''))), 0, 4000);
            if (preg_match('/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-[0-9A-Za-z.-]+)?$/', $version) !== 1 || mb_strlen($name) < 3 || mb_strlen($notes) < 10) {
                Flash::set('error', 'Informe uma versão semântica, um nome e notas com pelo menos 10 caracteres.');
                Response::to('/studio/versions');
            }
            try {
                $pdo->beginTransaction();
                $pdo->exec("UPDATE studio_versions SET status='archived' WHERE product='studio' AND status='current'");
                $statement = $pdo->prepare("INSERT INTO studio_versions(product,version,name,notes,status,created_by) VALUES('studio',?,?,?,'current',?)");
                $statement->execute([$version, $name, $notes, Auth::user()?->id]);
                $pdo->commit();
                Logger::info('Versão do Studio registrada.', ['version' => $version, 'actor_id' => Auth::user()?->id]);
                Flash::set('success', 'Versão registrada no histórico. Nenhum deploy foi executado.');
            } catch (\Throwable $exception) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                Logger::exception($exception);
                Flash::set('error', 'A versão já existe ou não pôde ser registrada.');
            }
            Response::to('/studio/versions');
        }
        $checks = Diagnostics::run();
        $migrations = glob(dirname(__DIR__, 2) . '/database/migrations/*.sql') ?: [];
        sort($migrations);

        echo $this->view->render('pages/versions', [
            'title' => 'Versões',
            'version' => $this->applicationVersion(),
            'phpVersion' => PHP_VERSION,
            'environment' => Config::environment(),
            'database' => ($checks['database']['ok'] ?? false) ? 'Acessível' : 'Indisponível',
            'migrations' => array_map('basename', $migrations),
            'themeName' => 'admin',
            'composer' => is_file(dirname(__DIR__, 2) . '/composer.lock')
                ? 'Dependências bloqueadas por composer.lock'
                : 'composer.lock ausente',
            'versions' => $pdo->query("SELECT v.*,u.name author_name FROM studio_versions v LEFT JOIN users u ON u.id=v.created_by WHERE v.product='studio' ORDER BY v.id DESC LIMIT 50")->fetchAll(\PDO::FETCH_ASSOC),
        ]);
    }

    public function logs(): void
    {
        $pdo = Connection::getInstance();
        if (Request::isMethod('POST')) {
            $this->validateCsrf('/studio/logs');
            $fingerprint = (string) Request::post('fingerprint', '');
            $action = (string) Request::post('action', '');
            if (preg_match('/^[a-f0-9]{64}$/', $fingerprint) !== 1 || !in_array($action, ['open', 'resolved', 'ignored'], true)) {
                Flash::set('error', 'Evento ou ação inválida.');
                Response::to('/studio/logs');
            }
            $knownEntries = (new LogReader())->read('', '', 1, 2000)['entries'];
            $knownFingerprints = array_column($knownEntries, 'fingerprint');
            if (!in_array($fingerprint, $knownFingerprints, true)) {
                Flash::set('error', 'O evento não está mais disponível no histórico atual.');
                Response::to('/studio/logs');
            }
            $statement = $pdo->prepare('INSERT INTO studio_log_states(fingerprint,status,updated_by) VALUES(?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status),updated_by=VALUES(updated_by)');
            $statement->execute([$fingerprint, $action, Auth::user()?->id]);
            Logger::info('Estado de evento técnico atualizado.', ['event_fingerprint' => $fingerprint, 'status' => $action, 'actor_id' => Auth::user()?->id]);
            Flash::set('success', 'Estado do evento atualizado.');
            Response::to('/studio/logs');
        }
        $search = (string) Request::get('q', '');
        $level = (string) Request::get('level', '');
        $status = in_array(Request::get('status'), ['open', 'resolved', 'ignored'], true) ? (string) Request::get('status') : '';
        $page = max(1, (int) Request::get('page', 1));
        $all = (new LogReader())->read($search, $level, 1, 2000)['entries'];
        $states = $pdo->query('SELECT fingerprint,status FROM studio_log_states')->fetchAll(\PDO::FETCH_KEY_PAIR);
        foreach ($all as &$entry) { $entry['status'] = (string) ($states[$entry['fingerprint']] ?? 'open'); }
        unset($entry);
        if ($status !== '') { $all = array_values(array_filter($all, static fn (array $entry): bool => $entry['status'] === $status)); }
        $total = count($all); $pages = max(1, (int) ceil($total / 20)); $page = min($page, $pages);
        $result = ['entries' => array_slice($all, ($page - 1) * 20, 20), 'total' => $total, 'page' => $page, 'pages' => $pages, 'perPage' => 20];

        echo $this->view->render('pages/logs', $result + [
            'title' => 'Log',
            'search' => mb_substr(trim(strip_tags($search)), 0, 120),
            'level' => in_array($level, ['info', 'warning', 'error'], true) ? $level : '',
            'status' => $status,
        ]);
    }

    private function validateCsrf(string $redirect): void
    {
        $token = Request::post('_token');
        if (!is_string($token) || !Csrf::validate($token)) { Flash::set('error', 'Token de segurança inválido.'); Response::to($redirect); }
    }

    private function applicationVersion(): string
    {
        return '1.0.0';
    }
}
