<?php
declare(strict_types=1);
namespace Moves\Controllers;
use Moves\Boot\Connection;use Moves\Core\Controller;use Moves\Core\Csrf;use Moves\Core\Flash;use Moves\Core\Logger;use Moves\Core\Request;use Moves\Core\Response;use Moves\Core\Validator;use Moves\Services\Auth\PasswordRecoveryService;use Moves\Services\Mail\SmtpRecoveryMailer;
final class PasswordRecoveryController extends Controller
{
    public function requestForm():void{echo $this->view->render('pages/forgot-password',['title'=>'Recuperar acesso','version'=>'0.0.1']);}
    public function requestCode():void
    {
        if(!Csrf::validate(is_string(Request::post('_token'))?Request::post('_token'):null)){Flash::set('error','Sua sessão expirou. Atualize a página e tente novamente.');Response::to('/forgot-password');}
        $email=trim((string)Request::post('email',''));$validator=(new Validator())->required('email',$email,'Informe seu e-mail.')->email('email',$email);if($validator->fails()){foreach($validator->errors() as $error){Flash::set('error',$error);}Response::to('/forgot-password');}
        try{(new PasswordRecoveryService(Connection::getInstance(),new SmtpRecoveryMailer()))->request($email,Request::ip());}catch(\Throwable $exception){Logger::error('Falha ao processar recuperação de conta.',['exception'=>$exception::class]);}
        Flash::set('success','Se existir uma conta ativa para este e-mail, enviaremos um código de recuperação.');Response::to('/forgot-password?sent=1');
    }
}
