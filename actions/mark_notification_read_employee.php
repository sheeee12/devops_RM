<?php
session_start();
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../includes/security.php';

// Vérifier que l'utilisateur est connecté et est un employé
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

// S'assurer que user_role est défini dans la session
if (!isset($_SESSION['user_role']) && isset($_SESSION['user']['role'])) {
    $_SESSION['user_role'] = $_SESSION['user']['role'];
} elseif (!isset($_SESSION['user_role']) && isset($_SESSION['user_id'])) {
    try {
        $pdo = Database::getInstance()->getConnexion();
        $stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $role = $stmt->fetchColumn();
        if ($role) {
            $_SESSION['user_role'] = $role;
        }
    } catch (Exception $e) {
        // Ignorer l'erreur
    }
}

if ($_SESSION['user_role'] !== 'employee') {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$employeeId = $_SESSION['user_id'] ?? $_SESSION['user']['user_id'] ?? null;
if (!$employeeId) {
    echo json_encode(['success' => false, 'message' => 'Session invalide']);
    exit;
}

$pdo = Database::getInstance()->getConnexion();

$input = json_decode(file_get_contents('php://input'), true);
$notificationId = $input['id'] ?? null;
$notificationType = $input['type'] ?? null;

if (!$notificationId || !$notificationType) {
    echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
    exit;
}

try {
    // Vérifier que la notification appartient bien à l'employé
    $sql = "UPDATE notifications 
            SET is_read = 1 
            WHERE id = ? 
            AND user_id = ?
            AND is_read = 0";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$notificationId, $employeeId]);
    
    if ($result && $stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Notification marquée comme lue']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour ou notification déjà lue']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}















