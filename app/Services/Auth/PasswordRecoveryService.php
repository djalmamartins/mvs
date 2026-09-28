<?php
declare(strict_types=1);
namespace Moves\Services\Auth;
use DateTimeImmutable;use Moves\Contracts\RecoveryMailer;use PDO;
final class PasswordRecoveryService
{
    private const EXPIRY_MINUTES=15;private const MAX_REQUESTS=3;
    public function __construct(private PDO $pdo,private RecoveryMailer $mailer){}
    public function request(string $email,string $ip):void
    {
        $email=strtolower(trim($email));$emailHash=hash('sha256',$email);$ipHash=hash('sha256',$ip);$since=(new DateTimeImmutable('-15 minutes'))->format('Y-m-d H:i:s');$rate=$this->pdo->prepare('SELECT COUNT(*) FROM password_recovery_requests WHERE email_hash=:email_hash AND requested_ip_hash=:ip_hash AND created_at>=:since');$rate->execute(['email_hash'=>$emailHash,'ip_hash'=>$ipHash,'since'=>$since]);if((int)$rate->fetchColumn()>=self::MAX_REQUESTS){return;}
        $lookup=$this->pdo->prepare('SELECT id,name,email FROM users WHERE LOWER(email)=:email AND status=:status LIMIT 1');$lookup->execute(['email'=>$email,'status'=>'active']);$user=$lookup->fetch(PDO::FETCH_ASSOC);$code=(string)random_int(100000,999999);$expiresAt=(new DateTimeImmutable('+'.self::EXPIRY_MINUTES.' minutes'))->format('Y-m-d H:i:s');
        $this->pdo->beginTransaction();try{if(is_array($user)){$invalidate=$this->pdo->prepare('UPDATE password_recovery_requests SET used_at=CURRENT_TIMESTAMP WHERE user_id=:user_id AND used_at IS NULL');$invalidate->execute(['user_id'=>(int)$user['id']]);}$insert=$this->pdo->prepare('INSERT INTO password_recovery_requests (user_id,email_hash,code_hash,requested_ip_hash,expires_at) VALUES (:user_id,:email_hash,:code_hash,:ip_hash,:expires_at)');$insert->execute(['user_id'=>is_array($user)?(int)$user['id']:null,'email_hash'=>$emailHash,'code_hash'=>password_hash($code,PASSWORD_DEFAULT),'ip_hash'=>$ipHash,'expires_at'=>$expiresAt]);$this->pdo->commit();}catch(\Throwable $exception){if($this->pdo->inTransaction()){$this->pdo->rollBack();}throw $exception;}
        if(is_array($user)){$this->mailer->sendRecoveryCode((string)$user['email'],(string)$user['name'],$code,self::EXPIRY_MINUTES);}
    }
}
