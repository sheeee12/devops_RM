<?php
session_start();
require_once '../classes/User.php';
require_once '../config/Mailer.php'; 
require_once '../config/mail_credentials.php';
require_once '../config/Lang.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Récupérer la langue depuis le formulaire ou la session
    $lang = $_POST['lang'] ?? $_SESSION['lang'] ?? $_COOKIE['app_lang'] ?? 'fr';
    if (in_array($lang, ['fr', 'en'])) {
        Lang::set($lang);
    } else {
        Lang::init();
    }
    
    $email = $_POST['email'];
    
    $result = User::initiatePasswordReset($email);

    if ($result) {
        
        $token = $result['token'];
        $nomUser = $result['nom'];
        
        // Récupérer la langue actuelle pour l'inclure dans le lien
        $currentLang = Lang::current();
        
        // Utiliser BASE_URL depuis mail_credentials.php ou détecter automatiquement
        $baseUrl = defined('BASE_URL') ? BASE_URL : ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST']);
        $link = rtrim($baseUrl, '/') . "/views/auth/reset_pwd.php?token=" . $token . "&lang=" . $currentLang;
        $safeLink = htmlspecialchars($link, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // Utiliser les traductions pour l'email
        $sujet = Lang::get('email.password_reset.subject');
        $greeting = Lang::get('email.password_reset.greeting');
        $message_text = Lang::get('email.password_reset.message');
        $instruction = Lang::get('email.password_reset.instruction');
        $button_text = Lang::get('email.password_reset.button');
        $expiry = Lang::get('email.password_reset.expiry');
        $backup_link = Lang::get('email.password_reset.backup_link');
        
        $message = "
            <div style='font-family: Arial, sans-serif; padding: 20px; background-color: #f4f4f4;'>
                <div style='background-color: white; padding: 30px; border-radius: 10px; max-width: 500px; margin: auto;'>
                    <h2 style='color: #333;'>$greeting $nomUser,</h2>
                    <p>$message_text</p>
                    <p>$instruction</p>
                    <br>
                    <div style='text-align: center;'>
                        <a href='$safeLink' style='
                            background-color: #0d6efd; 
                            color: white; 
                            padding: 12px 24px; 
                            text-decoration: none; 
                            border-radius: 5px; 
                            font-weight: bold; 
                            display: inline-block;'>
                            $button_text
                        </a>
                    </div>
                    <br>
                    <p style='color: #777; font-size: 12px;'>$expiry</p>
                    <p style='color: #777; font-size: 12px;'>$backup_link $safeLink</p>
                </div>
            </div>
        ";

        
        if (Mailer::send($email, $nomUser, $sujet, $message)) {

            header("Location: ../views/auth/login.php?msg=mail_sent");
            exit();
        } 
        else {
            die(Lang::get('common.error_sending', 'Erreur technique d\'envoi.'));
        }

    } else {
        header("Location: ../views/auth/forgot_pwd.php?error=email_not_found");
        exit();
    }
}
?>