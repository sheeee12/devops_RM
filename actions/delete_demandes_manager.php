<?php
session_start();
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../includes/security.php';

// Sécurité : Seul le manager passe
protect_page('manager');

// Récupérer les IDs depuis POST (peut être JSON string ou array)
$ids = [];
if (isset($_POST['ids'])) {
    if (is_string($_POST['ids'])) {
        $ids = json_decode($_POST['ids'], true);
    } elseif (is_array($_POST['ids'])) {
        $ids = $_POST['ids'];
    }
}

if (empty($ids) || !is_array($ids)) {
    echo json_encode(['success' => false, 'message' => 'Aucune demande sélectionnée.']);
    exit;
}

$managerId = $_SESSION['user_id'];
$pdo = Database::getInstance()->getConnexion();
$ids = array_map('intval', $ids);

try {
    $pdo->beginTransaction();
    
    $deletedCount = 0;
    $errors = [];
    
    foreach ($ids as $id_dem) {
        // Vérifier que la demande appartient à un employé du manager
        $stmtCheck = $pdo->prepare("SELECT d.*, u.user_id, u.manager_id 
                                   FROM demande d
                                   JOIN users u ON d.user_id = u.user_id
                                   WHERE d.id_dem = ? AND u.manager_id = ?");
        $stmtCheck->execute([$id_dem, $managerId]);
        $demande = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        
        if (!$demande) {
            $errors[] = "Demande #$id_dem : non autorisée ou n'appartient pas à votre équipe.";
            continue;
        }
        
        // Supprimer les lignes de frais associées
        $stmtDeleteLines = $pdo->prepare("DELETE FROM expense_line WHERE id_dem = ?");
        $stmtDeleteLines->execute([$id_dem]);
        
        // Supprimer les notifications associées
        try {
            $stmtDeleteNotif = $pdo->prepare("DELETE FROM notifications WHERE related_id = ?");
            $stmtDeleteNotif->execute([$id_dem]);
        } catch (PDOException $e) {
            // Ignorer si la table notifications n'existe pas
        }
        
        // Supprimer les réclamations associées
        try {
            $stmtDeleteReclam = $pdo->prepare("DELETE FROM reclamations WHERE id_dem = ?");
            $stmtDeleteReclam->execute([$id_dem]);
        } catch (PDOException $e) {
            // Ignorer si la table reclamations n'existe pas
        }
        
        // Supprimer la demande
        $stmtDelete = $pdo->prepare("DELETE FROM demande WHERE id_dem = ?");
        $stmtDelete->execute([$id_dem]);
        
        $deletedCount++;
    }
    
    $pdo->commit();
    
    $message = "$deletedCount demande(s) supprimée(s) avec succès.";
    if (!empty($errors)) {
        $message .= " " . implode(" ", $errors);
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'deleted' => $deletedCount,
        'errors' => $errors
    ]);
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la suppression : ' . $e->getMessage()
    ]);
    error_log('Erreur suppression demandes manager: ' . $e->getMessage());
}
?>

