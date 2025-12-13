<?php
session_start();
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../includes/security.php';

// Vérifier que l'utilisateur est connecté et est un admin
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    header('Location: ../views/auth/login.php');
    exit;
}

// Vérifier que l'utilisateur est un admin
$user_role = null;
if (isset($_SESSION['user']['role'])) {
    $user_role = $_SESSION['user']['role'];
} elseif (isset($_SESSION['user_role'])) {
    $user_role = $_SESSION['user_role'];
}

// Si le rôle n'est toujours pas trouvé, le récupérer depuis la BDD
if (!$user_role && isset($_SESSION['user_id'])) {
    try {
        $pdo = Database::getInstance()->getConnexion();
        $stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_role = $stmt->fetchColumn();
        if ($user_role && isset($_SESSION['user'])) {
            $_SESSION['user']['role'] = $user_role;
        }
    } catch (Exception $e) {
        // Ignorer l'erreur
    }
}

if ($user_role !== 'admin') {
    header('Location: ../views/auth/login.php');
    exit;
}

$redirect_page = '../views/admin/profil.php';

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

