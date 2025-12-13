<?php
session_start();
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../includes/security.php';

// Sécurité : Seul le manager passe
protect_page('manager');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ../views/manager/validation.php?error=invalid_id');
    exit;
}

$demandeId = (int)$_GET['id'];
$reason = isset($_GET['reason']) ? trim($_GET['reason']) : 'Aucun motif spécifié';
$managerId = $_SESSION['user_id'];
$pdo = Database::getInstance()->getConnexion();

try {
    // Vérifier que la demande appartient bien à un employé du manager
    $sqlCheck = "SELECT d.*, u.user_id, u.manager_id, u.team_id, t.manager_id as team_manager_id
                 FROM demande d
                 JOIN users u ON d.user_id = u.user_id
                 LEFT JOIN teams t ON u.team_id = t.team_id
                 WHERE d.id_dem = ? AND (u.manager_id = ? OR t.manager_id = ?) AND d.status = 'Attente_Manager'";
    
    $stmt = $pdo->prepare($sqlCheck);
    $stmt->execute([$demandeId, $managerId, $managerId]);
    $demande = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$demande) {
        header('Location: ../views/manager/validation.php?error=unauthorized');
        exit;
    }
    
    // Mettre à jour le statut de la demande à 'Rejete'
    $sqlUpdate = "UPDATE demande SET status = 'Rejete' WHERE id_dem = ?";
    $stmt = $pdo->prepare($sqlUpdate);
    $stmt->execute([$demandeId]);
    
    // Créer une notification pour l'employé avec le motif du rejet
    try {
        $sqlNotif = "INSERT INTO notifications (user_id, type, title, message, related_id) 
                     VALUES (?, 'rejet', ?, ?, ?)";
        $stmt = $pdo->prepare($sqlNotif);
        $reasonEscaped = htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');
        $stmt->execute([
            $demande['user_id'],
            'Demande rejetée',
            "Votre demande de remboursement '{$demande['titre_dem']}' a été rejetée par votre manager. Motif : {$reasonEscaped}",
            $demandeId
        ]);
    } catch (PDOException $e) {
        // Si la table notifications n'existe pas, on continue quand même
    }
    
    // Redirection avec message de succès
    header('Location: ../views/manager/validation.php?success=rejected&id=' . $demandeId);
    exit;
    
} catch (PDOException $e) {
    header('Location: ../views/manager/validation.php?error=db_error');
    exit;
}
?>

