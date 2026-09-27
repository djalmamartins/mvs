<?php
declare(strict_types=1);
namespace Moves\Controllers;
use Moves\Core\Controller;
final class LayoutController extends Controller
{
    public function index(): void { header('Location: /layout/login'); exit; }
    public function login(): void { $this->auth('login'); }
    public function signup(): void { $this->auth('signup'); }
    public function forgot(): void { $this->auth('forgot'); }
    public function product(array $data = []): void
    {
        $product = strtolower((string)($data['product'] ?? 'meu-dia'));
        $allowed=['meu-dia','talk','suporte','erp','operacional','cm','studio'];
        if(!in_array($product,$allowed,true)){$product='meu-dia';}
        $page = strtolower((string)($data['page'] ?? 'dashboard'));
        echo $this->view->render('pages/product',['title'=>'MVS Layout','product'=>$product,'page'=>$page]);
    }
    private function auth(string $mode): void
    {
        echo $this->view->render('pages/auth',['title'=>'MVS Layout','mode'=>$mode]);
    }
}