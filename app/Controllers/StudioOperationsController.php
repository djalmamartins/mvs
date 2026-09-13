<?php
declare(strict_types=1);
namespace Moves\Controllers;
use Moves\Boot\Connection;
use Moves\Core\Auth;
use Moves\Core\Controller;
use Moves\Core\Csrf;
use Moves\Core\Flash;
use Moves\Core\Logger;
use Moves\Core\Request;
use Moves\Core\Response;
use PDO;
final class StudioOperationsController extends Controller
{
    public function proposals(): void
    {
        $pdo=Connection::getInstance();
        if(Request::isMethod('POST')){$this->validateCsrf();$id=max(0,(int)Request::post('id',0));$action=(string)Request::post('action','status');$exists=$pdo->prepare('SELECT 1 FROM proposals WHERE id=?');$exists->execute([$id]);if(!$exists->fetchColumn()){Flash::set('error','Proposta não encontrada.');Response::to('/admin/proposals');}$note=mb_substr(trim(strip_tags((string)Request::post('note',''))),0,5000);
            if($action==='status'){$status=in_array(Request::post('status'),['new','contacted','qualified','won','lost','archived'],true)?(string)Request::post('status'):'new';$pdo->prepare('UPDATE proposals SET status=?,converted_at=? WHERE id=?')->execute([$status,$status==='won'?date('Y-m-d H:i:s'):null,$id]);$note='Status alterado para '.$status.($note?': '.$note:'');}
            elseif($action==='note'){if(mb_strlen($note)<2){Flash::set('error','Escreva uma observação.');Response::to('/admin/proposals?view='.$id);}}
            elseif($action==='respond'){if(mb_strlen($note)<10){Flash::set('error','A resposta deve ter ao menos 10 caracteres.');Response::to('/admin/proposals?view='.$id);}$pdo->prepare("UPDATE proposals SET response=?,responded_at=NOW(),status=IF(status='new','contacted',status),assigned_to=? WHERE id=?")->execute([$note,Auth::user()?->id,$id]);}
            elseif($action==='convert'){$pdo->prepare("UPDATE proposals SET status='won',converted_at=NOW(),assigned_to=? WHERE id=?")->execute([Auth::user()?->id,$id]);$note=$note?:'Proposta convertida em oportunidade ganha.';}
            else{Flash::set('error','Ação inválida.');Response::to('/admin/proposals');}
            $pdo->prepare('INSERT INTO proposal_history(proposal_id,action,note,created_by) VALUES(?,?,?,?)')->execute([$id,$action,$note,Auth::user()?->id]);Logger::info('Proposta atualizada no Studio.',['record_id'=>$id,'action'=>$action]);Flash::set('success','Proposta atualizada.');Response::to('/admin/proposals?view='.$id);
        }
        $status=in_array(Request::get('status'),['new','contacted','qualified','won','lost','archived'],true)?(string)Request::get('status'):'';$search=mb_substr(trim(strip_tags((string)Request::get('q',''))),0,100);$where=[];$params=[];if($status){$where[]='p.status=?';$params[]=$status;}if($search){$where[]='(p.name LIKE ? OR p.email LIKE ? OR p.company LIKE ?)';array_push($params,"%{$search}%","%{$search}%","%{$search}%");}
        $statement=$pdo->prepare('SELECT p.*,u.name assignee_name,(SELECT COUNT(*) FROM proposal_history h WHERE h.proposal_id=p.id) history_count FROM proposals p LEFT JOIN users u ON u.id=p.assigned_to'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY p.id DESC LIMIT 200');$statement->execute($params);$selected=null;$history=[];$viewId=max(0,(int)Request::get('view',0));if($viewId){$detail=$pdo->prepare('SELECT p.*,u.name assignee_name FROM proposals p LEFT JOIN users u ON u.id=p.assigned_to WHERE p.id=?');$detail->execute([$viewId]);$selected=$detail->fetch(PDO::FETCH_ASSOC)?:null;$hist=$pdo->prepare('SELECT h.*,u.name author_name FROM proposal_history h LEFT JOIN users u ON u.id=h.created_by WHERE h.proposal_id=? ORDER BY h.id DESC');$hist->execute([$viewId]);$history=$hist->fetchAll(PDO::FETCH_ASSOC);}echo $this->view->render('pages/proposals',['title'=>'Propostas','records'=>$statement->fetchAll(PDO::FETCH_ASSOC),'status'=>$status,'search'=>$search,'selected'=>$selected,'history'=>$history]);
    }
    public function notifications(): void
    {
        $pdo = Connection::getInstance();
        $actorId = (int) Auth::user()?->id;
        if (Request::isMethod('POST')) {
            $this->validateCsrf();
            $action = (string) Request::post('action', 'read');
            $id = max(0, (int) Request::post('id', 0));
            if ($action === 'create') {
                $title = mb_substr(trim(strip_tags((string) Request::post('title', ''))), 0, 180);
                $message = mb_substr(trim(strip_tags((string) Request::post('message', ''))), 0, 5000);
                $recipientId = max(0, (int) Request::post('recipient_id', 0)) ?: null;
                $actionUrl = mb_substr(trim((string) Request::post('action_url', '')), 0, 500);
                if (mb_strlen($title) < 3 || mb_strlen($message) < 5) { Flash::set('error', 'Informe um título e uma mensagem válidos.'); Response::to('/admin/notifications'); }
                if ($actionUrl !== '' && !str_starts_with($actionUrl, '/')) { Flash::set('error', 'O link da notificação deve ser interno.'); Response::to('/admin/notifications'); }
                if ($recipientId !== null) {
                    $recipient = $pdo->prepare('SELECT 1 FROM users WHERE id=? AND status=1');
                    $recipient->execute([$recipientId]);
                    if (!$recipient->fetchColumn()) { $recipientId = null; }
                }
                $pdo->prepare('INSERT INTO notifications(title,message,recipient_id,source_type,action_url,link) VALUES(?,?,?,?,?,?)')->execute([$title, $message, $recipientId, 'studio', $actionUrl ?: null, $actionUrl ?: null]);
                Logger::info('Comunicação criada no Studio.', ['recipient_id' => $recipientId, 'author_id' => $actorId]);
                Flash::set('success', 'Comunicação enviada.');
            } elseif ($action === 'read_all') {
                $pdo->prepare('UPDATE notifications SET read_at=NOW() WHERE recipient_id IS NULL OR recipient_id=?')->execute([$actorId]);
            } elseif (in_array($action, ['read', 'unread', 'delete'], true)) {
                $sql = $action === 'delete' ? 'DELETE FROM notifications WHERE id=? AND (recipient_id IS NULL OR recipient_id=?)' : 'UPDATE notifications SET read_at='.($action === 'read' ? 'NOW()' : 'NULL').' WHERE id=? AND (recipient_id IS NULL OR recipient_id=?)';
                $pdo->prepare($sql)->execute([$id, $actorId]);
            } else { Flash::set('error', 'Ação inválida.'); }
            Response::to('/admin/notifications');
        }
        $statement = $pdo->prepare('SELECT * FROM notifications WHERE recipient_id IS NULL OR recipient_id=? ORDER BY id DESC LIMIT 200');
        $statement->execute([$actorId]);
        $records = $statement->fetchAll(PDO::FETCH_ASSOC);
        $users = $pdo->query('SELECT id,name FROM users WHERE status=1 ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
        echo $this->view->render('pages/notifications', ['title'=>'Notificações', 'records'=>$records, 'users'=>$users, 'unread'=>count(array_filter($records, static fn(array $row): bool => $row['read_at'] === null))]);
    }
    public function reports(): void
    {
        $pdo=Connection::getInstance();$counts=[];foreach(['page','article','highlight','testimonial','faq'] as $type){$statement=$pdo->prepare("SELECT COUNT(*) total,SUM(status='published') published FROM studio_content WHERE type=?");$statement->execute([$type]);$counts[$type]=$statement->fetch(PDO::FETCH_ASSOC);}$counts['media']=['total'=>(int)$pdo->query('SELECT COUNT(*) FROM studio_media')->fetchColumn(),'published'=>null];$counts['proposal']=['total'=>(int)$pdo->query('SELECT COUNT(*) FROM proposals')->fetchColumn(),'published'=>null];$proposalStates=$pdo->query('SELECT status,COUNT(*) total FROM proposals GROUP BY status ORDER BY total DESC')->fetchAll(PDO::FETCH_ASSOC);$monthly=$pdo->query("SELECT DATE_FORMAT(created_at,'%Y-%m') period,COUNT(*) total FROM proposals WHERE created_at>=DATE_SUB(CURDATE(),INTERVAL 11 MONTH) GROUP BY period ORDER BY period")->fetchAll(PDO::FETCH_ASSOC);if(Request::get('format')==='csv'){header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="moves-studio-report.csv"');$out=fopen('php://output','wb');fputcsv($out,['Módulo','Total','Publicados'],separator:';');foreach($counts as $key=>$count){fputcsv($out,[$key,$count['total'],$count['published']??''],separator:';');}fclose($out);exit;}echo $this->view->render('pages/reports',['title'=>'Relatórios','counts'=>$counts,'proposalStates'=>$proposalStates,'monthly'=>$monthly]);
    }
    private function validateCsrf(): void{if(!Csrf::validate(is_string(Request::post('_token'))?Request::post('_token'):null)){Flash::set('error','Sessão expirada.');Response::to('/admin');}}
}
