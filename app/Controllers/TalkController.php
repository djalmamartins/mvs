<?php
declare(strict_types=1);

namespace Moves\Controllers;

use Moves\Core\Auth;
use Moves\Core\Controller;
use Moves\Services\Talk\TalkService;

final class TalkController extends Controller
{
    public function index(): void
    {
        $user = Auth::user();
        if ($user === null) {
            return;
        }

        $talk = new TalkService();
        $userId = (int) $user->id;
        $talk->heartbeat($userId);
        $talk->autoAssign();

        $queue = $talk->queue();
        $mine = $talk->myTickets($userId);

        echo $this->view->render('pages/talk', [
            'title' => 'Talk',
            'user' => $user,
            'conversations' => array_merge($mine, $queue),
            'selectedConversation' => null,
            'queueCount' => count($queue),
            'mineCount' => count($mine),
        ]);
    }
}
