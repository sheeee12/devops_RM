<?php
session_start();
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../includes/security.php';

protect_page('employee');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
    exit;
}

$user_id = $_SESSION['user']['user_id'];

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
    $placeholders = implode(',', array_fill(0, count($notification_ids), '?'));
    $sql = "DELETE FROM notifications WHERE id IN ($placeholders) AND user_id = ?";
    $params = array_merge($notification_ids, [$user_id]);
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    $deletedCount = $stmt->rowCount();
    
    echo json_encode([
        'success' => true,
        'message' => "$deletedCount notification(s) supprimée(s) avec succès.",
        'deleted_count' => $deletedCount
    ]);
} catch (PDOException $e) {
    error_log("Erreur de suppression de notifications: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression des notifications.']);
}
?>

