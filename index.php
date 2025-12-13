<?php
session_start();

// Vérifier si l'utilisateur est déjà connecté
if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true) {
    // Si l'utilisateur est connecté, rediriger vers son dashboard selon son rôle
    $role = $_SESSION['user_role'] ?? null;
    
    switch ($role) {
        case 'admin':
            header('Location: views/admin/dashboard.php');
            exit();
        case 'manager':
            header('Location: views/manager/dashboard.php');
            exit();
        case 'employee':
            header('Location: views/employe/dashboard.php');
            exit();
        default:
            // Rôle inconnu, rediriger vers login
            header('Location: views/auth/login.php');
            exit();
    }
} else {
    // Utilisateur non connecté, rediriger vers la page de login
    header('Location: views/auth/login.php');
    exit();
}
?>

