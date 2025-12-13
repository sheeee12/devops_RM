<?php
session_start();
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../includes/security.php';

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

// Vérifier que l'utilisateur est connecté et est un manager
protect_page('manager');

$redirect_page = '../views/manager/parametres.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['avatar'])) {
    header('Location: ' . $redirect_page . '?error=upload');
    exit;
}

$userId = $_SESSION['user_id'] ?? $_SESSION['user']['user_id'] ?? null;
if (!$userId) {
    header('Location: ' . $redirect_page . '?error=session');
    exit;
}

$uploadDir = __DIR__ . '/../assets/img/';
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
$maxSize = 2 * 1024 * 1024; // 2MB

$file = $_FILES['avatar'];

// Vérifier le type
if (!in_array($file['type'], $allowedTypes)) {
    header('Location: ' . $redirect_page . '?error=format');
    exit;
}

// Vérifier la taille
if ($file['size'] > $maxSize) {
    header('Location: ' . $redirect_page . '?error=size');
    exit;
}

// Générer un nom unique pour les managers
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$newFileName = 'avatar_manager_' . $userId . '_' . time() . '.' . $extension;
$targetPath = $uploadDir . $newFileName;

// Upload du fichier
if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    // Vérifier que le fichier existe bien après l'upload
    if (!file_exists($targetPath)) {
        header('Location: ' . $redirect_page . '?error=upload');
        exit;
    }
    
    // Supprimer l'ancien avatar s'il existe et n'est pas default.png
    $pdo = Database::getInstance()->getConnexion();
    $stmt = $pdo->prepare("SELECT avatar FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $oldAvatar = $stmt->fetchColumn();
    
    if ($oldAvatar && $oldAvatar !== 'default.png' && file_exists($uploadDir . $oldAvatar)) {
        @unlink($uploadDir . $oldAvatar);
    }
    
    // Mettre à jour la base de données
    $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE user_id = ?");
    $stmt->execute([$newFileName, $userId]);
    
    // Vérifier que le fichier existe toujours après la mise à jour de la BDD
    if (!file_exists($targetPath)) {
        header('Location: ' . $redirect_page . '?error=upload');
        exit;
    }
    
    // Mettre à jour la session
    if (isset($_SESSION['user'])) {
        $_SESSION['user']['avatar'] = $newFileName;
        $_SESSION['user']['role'] = 'manager';
    }
    if (!isset($_SESSION['user_role'])) {
        $_SESSION['user_role'] = 'manager';
    }
    
    // Rediriger vers la page de paramètres du manager
    header('Location: ' . $redirect_page . '?success=avatar&t=' . time());
} else {
    header('Location: ' . $redirect_page . '?error=upload');
}
exit;
