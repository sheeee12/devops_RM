<?php
session_start();
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../includes/security.php';

// Accepter les managers et les employés
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    header('Location: ../views/auth/login.php');
    exit;
}

$user_role = $_SESSION['user_role'] ?? null;
if (!in_array($user_role, ['manager', 'employee'])) {
    header('Location: ../views/auth/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['theme'])) {
    // Rediriger vers la bonne page selon le rôle
    if ($user_role === 'manager') {
        header('Location: ../views/manager/parametres.php');
    } else {
        header('Location: ../views/employe/profil.php');
    }
    exit;
}

$theme = $_POST['theme'];
if (!in_array($theme, ['light', 'dark'])) {
    if ($user_role === 'manager') {
        header('Location: ../views/manager/parametres.php');
    } else {
        header('Location: ../views/employe/profil.php');
    }
    exit;
}

// Sauvegarder le thème dans la session et le cookie
$_SESSION['theme'] = $theme;
setcookie('app_theme', $theme, time() + (365 * 24 * 60 * 60), '/');

// Optionnel : sauvegarder dans la base de données
try {
    $pdo = Database::getInstance()->getConnexion();
    $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'preferred_theme'");
    $stmt->execute();
    if ($stmt->fetch()) {
        $user_id = $_SESSION['user_id'] ?? $_SESSION['user']['user_id'] ?? null;
        if ($user_id) {
            $stmt = $pdo->prepare("UPDATE users SET preferred_theme = ? WHERE user_id = ?");
            $stmt->execute([$theme, $user_id]);
        }
    }
} catch (PDOException $e) {
    // Ignorer si la colonne n'existe pas
}

// Rediriger vers la bonne page selon le rôle
if ($user_role === 'manager') {
    header('Location: ../views/manager/parametres.php?success=theme');
} else {
    header('Location: ../views/employe/profil.php?success=theme');
}
exit;

