<?php
declare(strict_types=1);

namespace Moves\Controllers;

use Moves\Core\Auth;
use Moves\Core\Controller;
use Moves\Core\Csrf;
use Moves\Core\Request;
use Moves\Core\Response;
use Moves\Services\Talk\TalkOutboundService;
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
    /** @param array<string,string> $data */
    public function ticket(array $data = []): void
    {
        $user = Auth::user();
        $id = max(0, (int)($data['id'] ?? 0));
        $talk = new TalkService();
        if ($user === null || $id === 0 || !$talk->canViewTicket($id, (int)$user->id)) {
            Response::to('/talk?error=forbidden');
        }

        if (Request::isMethod('POST')) {
            if (!Csrf::validate((string)Request::post('_token', ''))) {
                Response::to('/talk/tickets/'.$id.'?error=csrf');
            }
            if ((string)Request::post('action', '') === 'send') {
                try {
                    (new TalkOutboundService())->sendText($id, (int)$user->id, (string)Request::post('body', ''));
                    $_SESSION['talk_outbound_status'] = 'sent';
                } catch (\RuntimeException $e) {
                    $_SESSION['talk_outbound_error'] = $e->getMessage();
                }
            }
            Response::to('/talk/tickets/'.$id);
        }

        $ticket = $talk->ticket($id);
        if ($ticket === null) {
            Response::to('/talk');
        }
        $ticket['outbound_error'] = $_SESSION['talk_outbound_error'] ?? null;
        $ticket['outbound_status'] = $_SESSION['talk_outbound_status'] ?? null;
        unset($_SESSION['talk_outbound_error'], $_SESSION['talk_outbound_status']);

        echo $this->view->render('pages/talk', [
            'title' => 'Talk',
            'user' => $user,
            'conversations' => array_merge($talk->myTickets((int)$user->id), $talk->queue()),
            'selectedConversation' => $ticket,
        ]);
    }
}
