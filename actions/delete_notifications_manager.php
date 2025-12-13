<?php
session_start();
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../includes/security.php';

protect_page('manager');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
    exit;
}

$managerId = $_SESSION['user_id'];

if (!isset($_POST['notification_ids']) || !is_array($_POST['notification_ids'])) {
    echo json_encode(['success' => false, 'message' => 'Aucune notification sélectionnée.']);
    exit;
}

$notification_ids = array_map('intval', $_POST['notification_ids']);
$notification_ids = array_filter($notification_ids, function($id) { return $id > 0; });

if (empty($notification_ids)) {
    echo json_encode(['success' => false, 'message' => 'IDs de notifications invalides.']);
    exit;
}

$pdo = Database::getInstance()->getConnexion();

try {
    $pdo->beginTransaction();
    
    $deletedCount = 0;
    
    $placeholders = implode(',', array_fill(0, count($notification_ids), '?'));
    
    // Vérifier que les notifications appartiennent bien aux membres de l'équipe du manager

    $sql = "DELETE n FROM notifications n
            JOIN users u ON n.user_id = u.user_id
            WHERE n.id IN ($placeholders)
            AND u.manager_id = ?
            AND n.user_id != ?
            AND n.type NOT IN ('validation', 'rejet')";
    
    $params = array_merge($notification_ids, [$managerId, $managerId]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $deletedCount = $stmt->rowCount();
    
    
    
    $pdo->commit();
    
    if ($deletedCount > 0) {
        echo json_encode([
            'success' => true,
            'message' => "$deletedCount notification(s) supprimée(s) avec succès.",
            'deleted_count' => $deletedCount
        ]);
    } else {
        // Aucune notification supprimée (peut-être que ce sont des réclamations)
        echo json_encode([
            'success' => false,
            'message' => 'Aucune notification supprimée. Les réclamations ne peuvent pas être supprimées depuis cette page.'
        ]);
    }
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Erreur de suppression de notifications manager: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression des notifications.']);
}
?>

