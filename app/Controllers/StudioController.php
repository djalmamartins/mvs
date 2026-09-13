<?php

declare(strict_types=1);

namespace Moves\Controllers;

use Moves\Core\Controller;
use Moves\Core\Diagnostics;
use Moves\Core\Config;
use Moves\Core\LogReader;
use Moves\Core\Request;
use Moves\Core\Settings;
use Moves\Models\User;

/** Provides the initial Moves Studio dashboard. */
final class StudioController extends Controller
{
    public function dashboard(): void
    {
        $checks = Diagnostics::run();
        $activity = (new LogReader())->read('', '', 1, 10);

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
        ]);
    }

    public function versions(): void
    {
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
        ]);
    }

    public function logs(): void
    {
        $search = (string) Request::get('q', '');
        $level = (string) Request::get('level', '');
        $page = max(1, (int) Request::get('page', 1));
        $result = (new LogReader())->read($search, $level, $page);

        echo $this->view->render('pages/logs', $result + [
            'title' => 'Log',
            'search' => mb_substr(trim(strip_tags($search)), 0, 120),
            'level' => in_array($level, ['info', 'warning', 'error'], true) ? $level : '',
        ]);
    }

    private function applicationVersion(): string
    {
        return '1.0.0';
    }
}
