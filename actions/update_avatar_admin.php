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

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['avatar'])) {
    header('Location: ' . $redirect_page . '?error=upload');
    exit;
}

$userId = $_SESSION['user_id'] ?? $_SESSION['user']['user_id'] ?? null;
if (!$userId) {
    header('Location: ' . $redirect_page . '?error=session');
    exit;
}

$file = $_FILES['avatar'];
$uploadDir = __DIR__ . '/../assets/img/';
$maxSize = 2 * 1024 * 1024; // 2MB
$allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];

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

// Générer un nom unique pour l'admin
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$newFileName = 'avatar_admin_' . $userId . '_' . time() . '.' . $extension;
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
        $_SESSION['user']['role'] = 'admin';
    }
    if (!isset($_SESSION['user_role'])) {
        $_SESSION['user_role'] = 'admin';
    }
    
    // Rediriger vers la page de profil admin avec reload
    header('Location: ' . $redirect_page . '?success=avatar&t=' . time() . '&reload=1');
} else {
    header('Location: ' . $redirect_page . '?error=upload');
}
exit;

