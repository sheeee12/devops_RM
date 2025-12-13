<?php
require_once '../config/Database.php';
require_once '../config/Mailer.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = $_POST['nom'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $passwordClair = $_POST['password']; 

    //HACHAGE AUTOMATIQUE
    $hash = password_hash($passwordClair, PASSWORD_DEFAULT);

    //  INSERTION BDD
    $pdo = Database::getInstance()->getConnexion();
    $stmt = $pdo->prepare("INSERT INTO users (nom, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->execute([$nom, $email, $hash, $role]);

    // ENVOI DU VRAI MAIL
    $message = "
        <h1>Bonjour $nom !</h1>
        <p>Votre compte a été créé.</p>
        <p><b>Login :</b> $email<br><b>Mot de passe :</b> $passwordClair</p>
        <p>Connectez-vous pour activer la sécurité QR Code.</p>
    ";
    
    Mailer::send($email, $nom, "Bienvenue - Vos accès", $message);

    header('Location: ../views/admin/users.php?msg=created');
}
?>