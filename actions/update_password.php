<?php
session_start();
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/Lang.php';

Lang::init();
protect_page('manager');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../views/manager/parametres.php?error=password');
    exit;
}

$userId = $_SESSION['user_id'];
$currentPassword = $_POST['current_password'] ?? '';
$newPassword = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
    header('Location: ../views/manager/parametres.php?error=password');
    exit;
}

// Vérifier que les nouveaux mots de passe correspondent
if ($newPassword !== $confirmPassword) {
    header('Location: ../views/manager/parametres.php?error=password_mismatch');
    exit;
}

// Vérifier le format du nouveau mot de passe
$pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/';
if (!preg_match($pattern, $newPassword)) {
    header('Location: ../views/manager/parametres.php?error=password_weak');
    exit;
}

$pdo = Database::getInstance()->getConnexion();

// Vérifier le mot de passe actuel
$stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user || !password_verify($currentPassword, $user['password'])) {
    header('Location: ../views/manager/parametres.php?error=password_current');
    exit;
}

// Mettre à jour le mot de passe
$newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
$stmt->execute([$newPasswordHash, $userId]);

header('Location: ../views/manager/parametres.php?success=password');
exit;

