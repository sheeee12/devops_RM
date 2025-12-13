<?php
session_start();
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../includes/security.php';

protect_page('manager');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../views/manager/parametres.php?error=profile');
    exit;
}

$userId = $_SESSION['user_id'];
$nom = trim($_POST['nom'] ?? '');
$prenom = trim($_POST['prenom'] ?? '');
$email = trim($_POST['email'] ?? '');
$tel = trim($_POST['tel'] ?? '');

if (empty($nom) || empty($prenom) || empty($email)) {
    header('Location: ../views/manager/parametres.php?error=profile');
    exit;
}

// Vérifier que l'email n'est pas déjà utilisé par un autre utilisateur
$pdo = Database::getInstance()->getConnexion();
$stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
$stmt->execute([$email, $userId]);
if ($stmt->fetch()) {
    header('Location: ../views/manager/parametres.php?error=email_exists');
    exit;
}

try {
    // Vérifier si le champ tel existe
    $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'tel'");
    $stmt->execute();
    $hasTel = $stmt->fetch();
    
    if ($hasTel) {
        $stmt = $pdo->prepare("UPDATE users SET nom = ?, prenom = ?, email = ?, tel = ? WHERE user_id = ?");
        $stmt->execute([$nom, $prenom, $email, $tel, $userId]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET nom = ?, prenom = ?, email = ? WHERE user_id = ?");
        $stmt->execute([$nom, $prenom, $email, $userId]);
    }
    
    // Mettre à jour la session
    $_SESSION['user_nom'] = $nom;
    
    header('Location: ../views/manager/parametres.php?success=profile');
} catch (PDOException $e) {
    header('Location: ../views/manager/parametres.php?error=profile');
}
exit;

