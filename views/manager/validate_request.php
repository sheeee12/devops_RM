<?php
session_start();
require_once '../config/Database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = Database::getInstance()->getConnexion();
    
    // Créer la mission
    $sql = "INSERT INTO missions (manager_id, titre, lieu, date_debut, date_fin) VALUES (?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_SESSION['user_id'], $_POST['titre'], $_POST['lieu'], $_POST['date_debut'], $_POST['date_fin']]);
    $missionId = $pdo->lastInsertId();

    // Lier les participants (La boucle Pivot)
    if (isset($_POST['participants'])) {
        $sqlPivot = "INSERT INTO mission_participants (id_mission, user_id) VALUES (?, ?)";
        $stmtPivot = $pdo->prepare($sqlPivot);
        
        foreach ($_POST['participants'] as $userId) {
            $stmtPivot->execute([$missionId, $userId]);
        }
    }

    header('Location: ../views/manager/dashboard.php?msg=mission_created');
}
?>