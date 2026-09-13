<?php
declare(strict_types=1);
namespace Moves\Controllers;
use Moves\Boot\Connection;
use Moves\Core\Controller;
use Moves\Core\Request;
use PDO;
final class StudioSearchController extends Controller
{
    public function index(): void
    {
        $query=mb_substr(trim(strip_tags((string)Request::get('q',''))),0,100);$results=[];
        if(mb_strlen($query)>=2){$pdo=Connection::getInstance();$like='%'.$query.'%';
            $content=$pdo->prepare("SELECT id,type,title label,slug detail FROM studio_content WHERE title LIKE ? OR slug LIKE ? ORDER BY updated_at DESC LIMIT 30");$content->execute([$like,$like]);
            foreach($content->fetchAll(PDO::FETCH_ASSOC) as $row){$module=['page'=>'pages','article'=>'articles','highlight'=>'highlights','testimonial'=>'testimonials','faq'=>'faq'][$row['type']]??null;if($module){$results[]=['group'=>'Conteúdo','label'=>$row['label'],'detail'=>$row['type'].' · /'.$row['detail'],'url'=>'/admin/'.$module.'?edit='.$row['id'].'#editor'];}}
            $users=$pdo->prepare('SELECT id,name label,email detail FROM users WHERE name LIKE ? OR email LIKE ? ORDER BY name LIMIT 10');$users->execute([$like,$like]);foreach($users->fetchAll(PDO::FETCH_ASSOC) as $row){$results[]=['group'=>'Usuários','label'=>$row['label'],'detail'=>$row['detail'],'url'=>'/admin/users/edit/'.$row['id']];}
            $proposals=$pdo->prepare('SELECT id,name label,CONCAT(email,\' · \',service) detail FROM proposals WHERE name LIKE ? OR email LIKE ? OR company LIKE ? ORDER BY id DESC LIMIT 10');$proposals->execute([$like,$like,$like]);foreach($proposals->fetchAll(PDO::FETCH_ASSOC) as $row){$results[]=['group'=>'Propostas','label'=>$row['label'],'detail'=>$row['detail'],'url'=>'/admin/proposals?view='.$row['id']];}
        }
        echo $this->view->render('pages/search',['title'=>'Busca','query'=>$query,'results'=>$results]);
    }
}
