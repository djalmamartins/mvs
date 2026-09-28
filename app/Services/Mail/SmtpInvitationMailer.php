<?php

declare(strict_types=1);

namespace Moves\Services\Mail;

use Moves\Contracts\InvitationMailer;
use Moves\Core\Config;
use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;

final class SmtpInvitationMailer implements InvitationMailer
{
    public function sendInvitation(
        string $email,
        string $name,
        string $tenantName,
        string $acceptUrl,
        int $expiresInHours
    ): void {
        if (Config::get('MAIL_TRANSPORT', 'smtp') !== 'smtp') {
            throw new RuntimeException('Transporte de e-mail não configurado.');
        }

        $host = trim((string) Config::get('MAIL_HOST', ''));
        $from = trim((string) Config::get('MAIL_FROM_ADDRESS', ''));
        if ($host === '' || $from === '') {
            throw new RuntimeException('SMTP incompleto.');
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->Port = max(1, (int) Config::get('MAIL_PORT', 587));
        $mail->CharSet = 'UTF-8';
        $mail->SMTPAuth = trim((string) Config::get('MAIL_USERNAME', '')) !== '';
        $mail->Username = (string) Config::get('MAIL_USERNAME', '');
        $mail->Password = (string) Config::get('MAIL_PASSWORD', '');
        $encryption = strtolower(trim((string) Config::get('MAIL_ENCRYPTION', 'tls')));
        if ($encryption !== '') {
            $mail->SMTPSecure = $encryption;
        }

        $mail->setFrom($from, (string) Config::get('MAIL_FROM_NAME', 'Moves'));
        $mail->addAddress($email, $name);
        $mail->isHTML(true);
        $mail->Subject = 'Convite para acessar a Moves';

        $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeTenant = htmlspecialchars($tenantName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeUrl = htmlspecialchars($acceptUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $mail->Body = '<p>Olá, ' . $safeName . '.</p><p>Você foi convidado para acessar <strong>'
            . $safeTenant . '</strong> na Moves.</p><p><a href="' . $safeUrl
            . '">Definir minha senha e ativar acesso</a></p><p>Este convite expira em '
            . $expiresInHours . ' horas e só pode ser usado uma vez.</p>';
        $mail->AltBody = "Você foi convidado para acessar {$tenantName} na Moves. "
            . "Defina sua senha em {$acceptUrl}. O convite expira em {$expiresInHours} horas e só pode ser usado uma vez.";
        $mail->send();
    }
}
