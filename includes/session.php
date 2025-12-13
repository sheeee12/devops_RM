<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/Database.php';

// Fonction pour charger les données utilisateur dans la session
function loadUserSession() {
    if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
        return false;
    }
    
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    // Si les données utilisateur ne sont pas déjà en session, les charger depuis la BDD
    if (!isset($_SESSION['user'])) {
        try {
            $pdo = Database::getInstance()->getConnexion();
            $stmt = $pdo->prepare("SELECT user_id, nom, email, role, avatar, team_id, manager_id FROM users WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $_SESSION['user'] = $user;
            } else {
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }
    
    return true;
}

// Fonction pour vérifier le rôle et rediriger si nécessaire
function requireRole($role_requis) {
    // Charger les données utilisateur
    if (!loadUserSession()) {
        header('Location: ../../views/auth/login.php');
        exit();
    }
    
    // Vérifier le rôle
    $user_role = $_SESSION['user']['role'] ?? $_SESSION['user_role'] ?? null;
    
    if ($user_role !== $role_requis) {
        // Rediriger vers le bon dashboard selon le rôle
        switch ($user_role) {
            case 'admin':
                header('Location: ../../views/admin/dashboard.php');
                break;
            case 'manager':
                header('Location: ../../views/manager/dashboard.php');
                break;
            case 'employee':
                header('Location: ../../views/employe/dashboard.php');
                break;
            default:
                header('Location: ../../views/auth/login.php');
        }
        exit();
    }
}

