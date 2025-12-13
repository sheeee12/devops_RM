<?php
session_start();
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../includes/security.php';

// Vérifier que l'utilisateur est connecté et est un employé
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    header('Location: ../views/auth/login.php');
    exit;
}

// S'assurer que user_role est défini dans la session
if (!isset($_SESSION['user_role']) && isset($_SESSION['user']['role'])) {
    $_SESSION['user_role'] = $_SESSION['user']['role'];
} elseif (!isset($_SESSION['user_role']) && isset($_SESSION['user_id'])) {
    // Si user_role n'est pas défini, le récupérer depuis la BDD
    try {
        $pdo = Database::getInstance()->getConnexion();
        $stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $role = $stmt->fetchColumn();
        if ($role) {
            $_SESSION['user_role'] = $role;
        }
    } catch (Exception $e) {
        // Ignorer l'erreur
    }
}

if ($_SESSION['user_role'] !== 'employee') {
    header('Location: ../views/auth/login.php');
    exit;
}

$redirect_page = '../views/employe/profil.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirect_page . '?error=password');
    exit;
}

$userId = $_SESSION['user_id'] ?? $_SESSION['user']['user_id'] ?? null;
if (!$userId) {
    header('Location: ' . $redirect_page . '?error=session');
    exit;
}

$oldPassword = $_POST['old_password'] ?? '';
$newPassword = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

if (empty($oldPassword) || empty($newPassword) || empty($confirmPassword)) {
    header('Location: ' . $redirect_page . '?error=password');
    exit;
}

// Vérifier que les nouveaux mots de passe correspondent
if ($newPassword !== $confirmPassword) {
    header('Location: ' . $redirect_page . '?error=password_mismatch');
    exit;
}

// Vérifier le format du nouveau mot de passe
$pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/';
if (!preg_match($pattern, $newPassword)) {
    header('Location: ' . $redirect_page . '?error=password_weak');
    exit;
}

$pdo = Database::getInstance()->getConnexion();

// Vérifier le mot de passe actuel
$stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user || !password_verify($oldPassword, $user['password'])) {
    header('Location: ' . $redirect_page . '?error=password_current');
    exit;
}

// Mettre à jour le mot de passe
$newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
$stmt->execute([$newPasswordHash, $userId]);

header('Location: ' . $redirect_page . '?success=password');
exit;









