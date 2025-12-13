<?php
session_start();
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../includes/security.php';

// Sécurité : Seul le manager passe
protect_page('manager');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$managerId = $_SESSION['user_id'];
$pdo = Database::getInstance()->getConnexion();

$input = json_decode(file_get_contents('php://input'), true);
$notificationId = $input['id'] ?? null;
$notificationType = $input['type'] ?? null;

if (!$notificationId || !$notificationType) {
    echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
    exit;
}

try {
    if ($notificationType === 'reclamation') {
        // Créer une table de suivi pour les réclamations lues par les managers
        $pdo->exec("CREATE TABLE IF NOT EXISTS reclamation_views (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reclamation_id INT NOT NULL,
            manager_id INT NOT NULL,
            viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_view (reclamation_id, manager_id),
            FOREIGN KEY (manager_id) REFERENCES users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Marquer la réclamation comme vue par ce manager
        $sql = "INSERT INTO reclamation_views (reclamation_id, manager_id) 
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE viewed_at = CURRENT_TIMESTAMP";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$notificationId, $managerId]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Réclamation marquée comme vue']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erreur lors du marquage de la réclamation']);
        }
    } else {
        // Pour les notifications de type notifications
        // Vérifier que le manager a le droit de marquer cette notification comme lue
        // (soit c'est sa notification, soit c'est une notification d'un de ses employés)
        $sql = "UPDATE notifications n
                LEFT JOIN users u ON n.user_id = u.user_id
                SET n.is_read = 1 
                WHERE n.id = ? 
                AND (n.user_id = ? OR u.manager_id = ?)
                AND n.is_read = 0";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$notificationId, $managerId, $managerId]);
        
        if ($result && $stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Notification marquée comme lue']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour ou notification déjà lue']);
        }
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}

