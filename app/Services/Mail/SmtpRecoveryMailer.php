<?php
declare(strict_types=1);
namespace Moves\Services\Mail;
use Moves\Contracts\RecoveryMailer;
use Moves\Core\Config;
use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;
final class SmtpRecoveryMailer implements RecoveryMailer
{
    public function sendRecoveryCode(string $email,string $name,string $code,int $expiresInMinutes):void
    {
        if(Config::get('MAIL_TRANSPORT','smtp')!=='smtp'){throw new RuntimeException('Transporte de e-mail não configurado.');}
        $host=trim((string)Config::get('MAIL_HOST',''));$from=trim((string)Config::get('MAIL_FROM_ADDRESS',''));if($host===''||$from===''){throw new RuntimeException('SMTP incompleto.');}
        $mail=new PHPMailer(true);$mail->isSMTP();$mail->Host=$host;$mail->Port=max(1,(int)Config::get('MAIL_PORT',587));$mail->CharSet='UTF-8';$mail->SMTPAuth=trim((string)Config::get('MAIL_USERNAME',''))!=='';$mail->Username=(string)Config::get('MAIL_USERNAME','');$mail->Password=(string)Config::get('MAIL_PASSWORD','');
        $encryption=strtolower(trim((string)Config::get('MAIL_ENCRYPTION','tls')));if($encryption!==''){$mail->SMTPSecure=$encryption;}
        $mail->setFrom($from,(string)Config::get('MAIL_FROM_NAME','Moves'));$mail->addAddress($email,$name);$mail->isHTML(true);$mail->Subject='Código para recuperar sua conta Moves';$safeName=htmlspecialchars($name,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$safeCode=htmlspecialchars($code,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $mail->Body='<p>Olá, '.$safeName.'.</p><p>Seu código de recuperação da Moves é:</p><p style="font-size:24px;font-weight:700;letter-spacing:6px">'.$safeCode.'</p><p>Ele expira em '.$expiresInMinutes.' minutos. Se você não solicitou, ignore este e-mail.</p>';$mail->AltBody="Seu código de recuperação da Moves é {$code}. Ele expira em {$expiresInMinutes} minutos.";$mail->send();
    }
}
