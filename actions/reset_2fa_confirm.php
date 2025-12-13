<?php
session_start();
require_once '../config/Database.php';
require_once '../config/Lang.php';

// Initialiser le système de langue
Lang::init();

$token = $_GET['token'] ?? '';

if ($token) {
    $pdo = Database::getInstance()->getConnexion();
    $tokenHash = hash("sha256", $token);

    
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE reset_token_hash = ? AND reset_expires_at > NOW()");
    $stmt->execute([$tokenHash]);
    $user = $stmt->fetch();

    if ($user) {

        $sql = "UPDATE users SET google_secret = NULL, reset_token_hash = NULL WHERE user_id = ?";
        $pdo->prepare($sql)->execute([$user['user_id']]);

        
        header('Location: ../views/auth/login.php?msg=2fa_reset_success');
        exit();
    }
}

$error_msg = Lang::get('common.invalid_link', 'Lien invalide ou expiré.');
die($error_msg);
?>