<?php
declare(strict_types=1);
namespace Moves\Controllers;
use Moves\Core\Auth;
use Moves\Core\Controller;
use Moves\Core\Request;
use Moves\Core\Response;
use MovesCode\Router\Router;
use Moves\Services\Day\DayService;

final class DayController extends Controller
{
    private DayService $day;
    public function __construct(Router $router){parent::__construct($router);$this->day=new DayService();}
    public function index(): void
    {
        $user=Auth::user();if($user===null){Response::to('/login');}
        echo $this->view->render('pages/day',['title'=>'Hoje','productName'=>'Meu Dia','activeProduct'=>'day','currentPage'=>'today','day'=>$this->day->dashboard((int)$user->id)]);
    }
    /** @param array<string,string> $route */
    public function task(array $route=[]): void
    {
        $user=Auth::user();if($user===null){Response::to('/login');}
        if(!Request::isMethod('POST')){Response::to('/day');}
        $this->requireCsrf('/day');
        $this->day->setTaskStatus(max(0,(int)($route['id']??0)),(int)$user->id,(string)Request::post('status','pending'));
        Response::to('/day');
    }
}