<?php
session_start();
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../includes/security.php';

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    header('Location: ../views/auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'] ?? $_SESSION['user']['user_id'] ?? null;
$user_role = $_SESSION['user_role'] ?? $_SESSION['user']['role'] ?? null;

// Vérifier l'ID de la demande
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'ID de demande invalide.';
    // Rediriger vers la page source ou mes_brouillons par défaut
    $source = $_GET['source'] ?? 'mes_brouillons';
    if ($source === 'dashboard') {
        header('Location: ../views/employe/dashboard.php');
    } elseif ($source === 'mes_frais') {
        header('Location: ../views/employe/mes_frais.php');
    } else {
        header('Location: ../views/employe/mes_brouillons.php');
    }
    exit();
}

$id_dem = intval($_GET['id']);
$source = $_GET['source'] ?? 'mes_brouillons';

$pdo = Database::getInstance()->getConnexion();

try {
    // Vérifier que la demande appartient à l'utilisateur
    // Permettre la suppression si c'est un brouillon ou une demande rejetée
    $stmtCheck = $pdo->prepare("SELECT * FROM demande WHERE id_dem = ? AND user_id = ? AND (status = 'Brouillon' OR status = 'Rejete')");
    $stmtCheck->execute([$id_dem, $user_id]);
    $demande = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    
    if (!$demande) {
        $_SESSION['error'] = 'Cette demande n\'existe pas, ne vous appartient pas ou ne peut pas être supprimée.';
        if ($source === 'dashboard') {
            header('Location: ../views/employe/dashboard.php');
        } elseif ($source === 'mes_frais') {
            header('Location: ../views/employe/mes_frais.php');
        } else {
            header('Location: ../views/employe/mes_brouillons.php');
        }
        exit();
    }
    
    $pdo->beginTransaction();
    
    // Supprimer les lignes de frais associées
    $stmtDeleteLines = $pdo->prepare("DELETE FROM expense_line WHERE id_dem = ?");
    $stmtDeleteLines->execute([$id_dem]);
    
    // Supprimer les notifications associées
    try {
        $stmtDeleteNotif = $pdo->prepare("DELETE FROM notifications WHERE related_id = ? AND user_id = ?");
        $stmtDeleteNotif->execute([$id_dem, $user_id]);
    } catch (PDOException $e) {
        // Ignorer si la table notifications n'existe pas ou s'il y a une erreur
    }
    
    // Supprimer la demande
    $stmtDelete = $pdo->prepare("DELETE FROM demande WHERE id_dem = ? AND user_id = ?");
    $stmtDelete->execute([$id_dem, $user_id]);
    
    $pdo->commit();
    
    $_SESSION['success'] = 'La demande a été supprimée avec succès.';
    
    // Rediriger vers la page source
    if ($source === 'dashboard') {
        header('Location: ../views/employe/dashboard.php');
    } elseif ($source === 'mes_frais') {
        header('Location: ../views/employe/mes_frais.php');
    } else {
        header('Location: ../views/employe/mes_brouillons.php');
    }
    exit();
    
} catch (PDOException $e) {
    $pdo->rollBack();
    $_SESSION['error'] = 'Erreur lors de la suppression : ' . $e->getMessage();
    
    // Rediriger vers la page source
    if ($source === 'dashboard') {
        header('Location: ../views/employe/dashboard.php');
    } elseif ($source === 'mes_frais') {
        header('Location: ../views/employe/mes_frais.php');
    } else {
        header('Location: ../views/employe/mes_brouillons.php');
    }
    exit();
}


