<?php
if (session_status() === PHP_SESSION_NONE){
    session_start();
}

function protect_page($role_requis) {
    if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
        header('Location: ../../views/auth/login.php');
        exit();
    }

    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== $role_requis) {
        $role = $_SESSION['user_role'] ?? null;

        if ($role === 'admin') header('Location: ../../views/admin/dashboard.php');
        elseif ($role === 'manager') header('Location: ../../views/manager/dashboard.php');
        elseif ($role === 'employee') header('Location: ../../views/employe/dashboard.php'); 
        else header('Location: ../../views/auth/login.php');
        exit();
    }
}
?>