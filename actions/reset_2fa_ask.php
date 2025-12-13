<?php
session_start();
require_once '../classes/User.php';
require_once '../config/Mailer.php';
require_once '../config/mail_credentials.php';
require_once '../config/Database.php';
require_once '../config/Lang.php';

// Récupérer la langue depuis la session ou le cookie
$lang = $_SESSION['lang'] ?? $_COOKIE['app_lang'] ?? 'fr';
if (in_array($lang, ['fr', 'en'])) {
    Lang::set($lang);
} else {
    Lang::init();
}

if (!isset($_SESSION['temp_user_id'])) {
     header('Location: ../views/auth/login.php'); 
     exit(); 
}

$userId = $_SESSION['temp_user_id'];
$email = $_SESSION['temp_user_email'];


$pdo = Database::getInstance()->getConnexion();
$token = bin2hex(random_bytes(16));
$tokenHash = hash("sha256", $token);


$stmt = $pdo->prepare("UPDATE users SET reset_token_hash = ?, reset_expires_at =  DATE_ADD(NOW(), INTERVAL 30 MINUTE)  WHERE user_id = ?");
$stmt->execute([$tokenHash, $userId]);


$baseUrl = defined('BASE_URL') ? BASE_URL : ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST']);
$link = rtrim($baseUrl, '/') . "/actions/reset_2fa_confirm.php?token=" . $token . "&lang=" . $lang;
$safeLink = htmlspecialchars($link, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');


// Utiliser les traductions pour l'email
$sujet = Lang::get('email.2fa_reset.subject');
$title = Lang::get('email.2fa_reset.title');
$message_text = Lang::get('email.2fa_reset.message');
$instruction = Lang::get('email.2fa_reset.instruction');
$button_text = Lang::get('email.2fa_reset.button');

$msg = "
    <div style='font-family: Arial, sans-serif; padding: 20px; background-color: #f4f4f4;'>
        <div style='background-color: white; padding: 30px; border-radius: 10px; max-width: 500px; margin: auto;'>
            <h3>$title</h3>
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
        </div>
    </div>
";

Mailer::send($email, "Utilisateur", $sujet, $msg);


header('Location: ../views/auth/login.php?msg=2fa_reset_sent');
exit();
?>