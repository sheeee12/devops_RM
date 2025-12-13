<?php
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/mail_credentials.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP; // Ajout nécessaire pour le debug

class Mailer {
    public static function send($toEmail, $toName, $subject, $body) {
        $mail = new PHPMailer(true);

        // $mail->SMTPDebug = 2; // debug niveau moyen 
        // $mail->Debugoutput = 'html'; //format log en php

        try {
            //$mail->SMTPDebug = SMTP::DEBUG_SERVER; 

            // Config serveur gmail
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
            $mail->Port       = SMTP_PORT;

            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';

            $mail->setFrom(SMTP_FROM, 'Gestion Frais Sécurité');
            
            // Destinataire
            $mail->addAddress($toEmail, $toName);

            // Contenu
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;

            $mail->send();
            return true;
        } catch (Exception $e) {
            echo "<h1>ERREUR D'ENVOI :</h1>";
            echo "<pre>" . $mail->ErrorInfo . "</pre>";
            exit(); 
            return false;
        }
    }
}
?>