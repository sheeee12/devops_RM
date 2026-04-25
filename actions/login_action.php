<?php
session_start();

require_once '../classes/user.php';
require_once '../classes/user.php';


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../views/auth/login.php');
    exit();
}

if (isset($_POST['email']) && isset($_POST['password'])) {

    $email = htmlspecialchars($_POST['email']);
    $password = $_POST['password'];

    $user = User::verifyLogin($email, $password);

    if ($user) {

        if (isset($_POST['remember'])) {
            setcookie('user_email', $email, time() + (86400 * 30), "/");
        } else {

            setcookie('user_email', "", time() - 3600, "/");
        }

        $_SESSION['temp_user_id'] = $user['user_id'];
        $_SESSION['temp_user_email'] = $user['email'];
        $_SESSION['temp_user_role'] = $user['role'];


        header('Location: ../views/auth/2fa_scan.php');
        exit();
    } else {

        header('Location: ../views/auth/login.php?error=bad_credentials');
        exit();
    }
}


// alidation du Code Google Auth

elseif (isset($_POST['code'])) {

    $inputCode = $_POST['code'];
    $userId = $_SESSION['temp_user_id'] ?? null;

    // Si on a perdu la session temporaire, on renvoie au début
    if (!$userId) {
        header('Location: ../views/auth/login.php');
        exit();
    }

    // On récupère le "Secret" de cet utilisateur en BDD
    $data = User::get2FASecret($userId);
    $secret = $data['secret'];

    // On vérifie le code avec la librairie Google Authenticator
    $check = User::checkCode($secret, $inputCode);

    if ($check) {

        session_regenerate_id(true);

        // Récupérer toutes les données utilisateur depuis la BDD
        require_once '../config/Database.php';
        $pdo = Database::getInstance()->getConnexion();
        $stmt = $pdo->prepare("SELECT user_id, nom, email, role, avatar, team_id, manager_id FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$userData) {
            // Si l'utilisateur n'existe plus, rediriger vers login
            header('Location: ../views/auth/login.php?error=user_not_found');
            exit();
        }

        // Création de la session finale avec toutes les données utilisateur
        $_SESSION['is_logged_in'] = true;
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_role'] = $userData['role'];
        $_SESSION['user'] = $userData; // ⭐ Important : créer le tableau $_SESSION['user'] attendu par includes/session.php

        // Nettoyage des variables temporaires
        unset($_SESSION['temp_user_id']);
        unset($_SESSION['temp_user_email']);
        unset($_SESSION['temp_user_role']);

        // Routage vers le bon Dashboard
        $role = $_SESSION['user_role'];
        switch ($role) {
            case 'admin':
                header('Location: ../views/admin/dashboard.php');
                break;
            case 'manager':
                header('Location: ../views/manager/dashboard.php');
                break;
            case 'employee':
                header('Location: ../views/employe/dashboard.php');
                break;
            default:
                header('Location: ../views/auth/login.php');
        }
        exit();
    } else {
        // Code Google Auth incorrect
        header('Location: ../views/auth/2fa_scan.php?error=invalid_code');
        exit();
    }
}

// Si aucune condition n'est remplie
header('Location: ../views/auth/login.php');
exit();
