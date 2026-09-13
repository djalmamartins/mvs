<?php

declare(strict_types=1);

namespace Moves\Controllers;

use Moves\Core\Controller;
use Moves\Core\Diagnostics;
use Moves\Core\Settings;
use Moves\Models\User;

/** Provides the initial Moves Studio dashboard. */
final class StudioController extends Controller
{
    public function dashboard(): void
    {
        $checks = Diagnostics::run();

        echo $this->view->render('pages/dashboard', [
            'title' => 'Dashboard',
            'appName' => (string) Settings::get('app_name', 'Moves'),
            'userCount' => (new User())->find()->count(),
            'checks' => $checks,
            'healthyChecks' => count(array_filter(
                $checks,
                static fn (array $check): bool => $check['ok']
            )),
        ]);
    }
}
