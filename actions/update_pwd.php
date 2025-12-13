<?php
session_start();
require_once '../classes/User.php';
require_once '../config/Lang.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Récupérer la langue depuis le formulaire, la session ou le cookie
    $lang = $_POST['lang'] ?? $_SESSION['lang'] ?? $_COOKIE['app_lang'] ?? 'fr';
    if (in_array($lang, ['fr', 'en'])) {
        Lang::set($lang);
    } else {
        Lang::init();
    }
    
    $token = $_POST['token'];
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    
    if ($password !== $confirm) {
        $error_msg = Lang::get('reset_password.error_passwords_not_match');
        $back_text = Lang::get('common.back', 'Retour');
        die("$error_msg <a href='javascript:history.back()'>$back_text</a>");
    }

    $pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/';

    if (!preg_match($pattern, $password)) {
        $error_title = Lang::get('reset_password.error_password_weak');
        $error_detail = Lang::get('reset_password.error_password_weak_detail');
        $rules = [
            Lang::get('reset_password.error_password_weak_rules.min_length'),
            Lang::get('reset_password.error_password_weak_rules.uppercase'),
            Lang::get('reset_password.error_password_weak_rules.lowercase'),
            Lang::get('reset_password.error_password_weak_rules.number'),
            Lang::get('reset_password.error_password_weak_rules.special')
        ];
        $try_again = Lang::get('reset_password.try_again');
        
        $list_items = '';
        foreach ($rules as $rule) {
            $list_items .= "<li>$rule</li>";
        }
        
        die("
            <div style='text-align:center; margin-top:50px; font-family:sans-serif;'>
                <h2 style='color:red'>$error_title</h2>
                <p>$error_detail</p>
                <ul style='display:inline-block; text-align:left;'>
                    $list_items
                </ul>
                <br><br>
                <a href='javascript:history.back()'>$try_again</a>
            </div>
        ");
    }

    $success = User::resetPassword($token, $password);

    if ($success) {
        
        header("Location: ../views/auth/login.php?msg=password_updated");
        exit();
    } else {
        
        $error_title = Lang::get('reset_password.error_link_expired');
        $error_detail = Lang::get('reset_password.error_link_expired_detail');
        die("<h2 style='color:red;text-align:center;'>$error_title</h2><p style='text-align:center;'>$error_detail</p>");
    }
}
?>