<?php
session_start();
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/Lang.php';

protect_page('manager');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['lang'])) {
    header('Location: ../views/manager/parametres.php');
    exit;
}

$lang = $_POST['lang'];
if (!in_array($lang, ['fr', 'en'])) {
    header('Location: ../views/manager/parametres.php');
    exit;
}

// Sauvegarder la langue
Lang::set($lang);

// Optionnel : sauvegarder dans la base de données pour l'utilisateur
try {
    $pdo = Database::getInstance()->getConnexion();
    $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'preferred_lang'");
    $stmt->execute();
    if ($stmt->fetch()) {
        $stmt = $pdo->prepare("UPDATE users SET preferred_lang = ? WHERE user_id = ?");
        $stmt->execute([$lang, $_SESSION['user_id']]);
    }
} catch (PDOException $e) {
    // Ignorer si la colonne n'existe pas
}

header('Location: ../views/manager/parametres.php?success=language');
exit;

