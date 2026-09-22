<?php
declare(strict_types=1);

namespace Moves\Controllers;

use Moves\Core\Auth;
use Moves\Core\Controller;

final class TalkController extends Controller
{
    public function index(): void
    {
        echo $this->view->render('pages/talk', [
            'title' => 'Talk',
            'user' => Auth::user(),
            'conversations' => [],
            'selectedConversation' => null,
        ]);
    }
}
