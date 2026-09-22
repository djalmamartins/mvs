<?php
declare(strict_types=1);

namespace Moves\Controllers;

use Moves\Core\Auth;
use Moves\Core\Controller;
use Moves\Core\Csrf;
use Moves\Core\Request;
use Moves\Core\Response;
use Moves\Services\Talk\TalkNotificationService;
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

        $queue = $talk->queueForUser($userId);
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
    public function sync(): never
    {
        $user = Auth::user();
        if ($user === null) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'unauthorized']);
            exit;
        }

        $talk = new TalkService();
        $userId = (int)$user->id;
        $talk->heartbeat($userId);
        $talk->autoAssign();
        $state = $talk->syncState($userId);
        $state['notifications'] = (new TalkNotificationService())->unreadCount($userId);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($state, JSON_THROW_ON_ERROR);
        exit;
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
            $action = (string)Request::post('action', '');
            try {
                if ($action === 'send') {
                    (new TalkOutboundService())->sendText($id, (int)$user->id, (string)Request::post('body', ''));
                    $_SESSION['talk_outbound_status'] = 'sent';
                } elseif ($action === 'claim') {
                    if (!$talk->claim($id, (int)$user->id)) {
                        throw new \RuntimeException('Não foi possível assumir este atendimento.');
                    }
                    $_SESSION['talk_action_status'] = 'Atendimento assumido.';
                } elseif ($action === 'return') {
                    $talk->returnToQueue($id, (int)$user->id);
                    $_SESSION['talk_action_status'] = 'Atendimento devolvido à fila.';
                } elseif ($action === 'close') {
                    $talk->close($id, (int)$user->id);
                    $_SESSION['talk_action_status'] = 'Atendimento finalizado.';
                } elseif ($action === 'reopen') {
                    $talk->reopen($id, (int)$user->id);
                    $_SESSION['talk_action_status'] = 'Atendimento reaberto.';
                }
            } catch (\RuntimeException $e) {
                $_SESSION['talk_outbound_error'] = 'A ação não pôde ser concluída. Verifique o estado do atendimento e tente novamente.';
            }
            Response::to('/talk/tickets/'.$id);
        }

        $ticket = $talk->ticket($id);
        if ($ticket === null) {
            Response::to('/talk');
        }
        $ticket['outbound_error'] = $_SESSION['talk_outbound_error'] ?? null;
        $ticket['outbound_status'] = $_SESSION['talk_outbound_status'] ?? null;
        $ticket['action_status'] = $_SESSION['talk_action_status'] ?? null;
        unset($_SESSION['talk_outbound_error'], $_SESSION['talk_outbound_status'], $_SESSION['talk_action_status']);

        $mine = $talk->myTickets((int)$user->id);
        $queue = $talk->queueForUser((int)$user->id);
        echo $this->view->render('pages/talk', [
            'title' => 'Talk',
            'user' => $user,
            'conversations' => array_merge($mine, $queue),
            'selectedConversation' => $ticket,
            'canOperateTicket' => $talk->canOperateTicket($id, (int)$user->id),
            'canManageTalk' => $talk->canManage((int)$user->id),
            'queueCount' => count($queue),
            'mineCount' => count($mine),
        ]);
    }
}
