<?php
declare(strict_types=1);

namespace Moves\Modules\Talk;

use Moves\Boot\Modules;
use Moves\Middleware\AuthMiddleware;
use Moves\Middleware\PermissionMiddleware;
use MovesCode\Router\Router;

final class TalkModule
{
    public static function register(): void
    {
        Modules::register(
            'talk',
            static function (Router $router): void {
                $middleware = [AuthMiddleware::class, new PermissionMiddleware('talk.access')];
                $router->get('/talk', 'TalkController:index', 'talk.home', $middleware);
                $router->get('/talk/queue', 'TalkController:index', 'talk.queue', $middleware);
                $router->get('/talk/tickets/{id}', 'TalkController:ticket', 'talk.ticket', $middleware);
                $router->post('/talk/tickets/{id}', 'TalkController:ticket', 'talk.ticket.action', $middleware);
            },
            [
                'admin' => ['talk.access'],
                'user' => ['talk.access'],
            ]
        );
    }
}
