<?php
session_start();
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../includes/security.php';

// Sécurité : Seul le manager peut supprimer
protect_page('manager');

if (!isset($_POST['mission_id']) || !is_numeric($_POST['mission_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID de mission invalide']);
    exit;
}

$missionId = (int)$_POST['mission_id'];
$managerId = $_SESSION['user_id'];
$pdo = Database::getInstance()->getConnexion();

try {
    // Vérifier que la mission appartient bien au manager
    $sqlCheck = "SELECT id_mission FROM missions WHERE id_mission = ? AND manager_id = ?";
    $stmt = $pdo->prepare($sqlCheck);
    $stmt->execute([$missionId, $managerId]);
    $mission = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$mission) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Mission non trouvée ou non autorisée']);
        exit;
    }
    
    //  Démarrer une transaction
    $pdo->beginTransaction();
    
    // Supprimer les participants de la mission
    $sqlDeleteParticipants = "DELETE FROM mission_participants WHERE id_mission = ?";
    $stmt = $pdo->prepare($sqlDeleteParticipants);
    $stmt->execute([$missionId]);
    
    //  Supprimer la mission
    $sqlDeleteMission = "DELETE FROM missions WHERE id_mission = ?";
    $stmt = $pdo->prepare($sqlDeleteMission);
    $stmt->execute([$missionId]);
    
    //  Valider la transaction
    $pdo->commit();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Déplacement supprimé avec succès']);
    
} catch (PDOException $e) {
    // En cas d'erreur, annuler la transaction
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression : ' . $e->getMessage()]);
}
?>

