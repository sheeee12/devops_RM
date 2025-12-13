<?php
session_start();
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../includes/security.php';

// Vérifier que l'utilisateur est connecté et est un employé
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    header('Location: ../views/auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'] ?? $_SESSION['user']['user_id'] ?? null;
$user_role = $_SESSION['user_role'] ?? $_SESSION['user']['role'] ?? null;

if (!$user_id || $user_role !== 'employee') {
    $_SESSION['error'] = 'Accès refusé';
    header('Location: ../views/employe/mes_avances.php');
    exit();
}

// Récupérer les données du formulaire
$montant = !empty($_POST['montant']) ? floatval($_POST['montant']) : 0;
$date_besoin = !empty($_POST['date_besoin']) ? $_POST['date_besoin'] : null;
$motif = trim($_POST['motif'] ?? '');

// Validation
if ($montant <= 0) {
    $_SESSION['error'] = 'Le montant doit être supérieur à 0.';
    header('Location: ../views/employe/mes_avances.php');
    exit();
}

if (empty($date_besoin)) {
    $_SESSION['error'] = 'La date du besoin est requise.';
    header('Location: ../views/employe/mes_avances.php');
    exit();
}

if (empty($motif)) {
    $_SESSION['error'] = 'Le motif de la demande est requis.';
    header('Location: ../views/employe/mes_avances.php');
    exit();
}

$pdo = Database::getInstance()->getConnexion();

// Activer le mode d'erreur PDO pour voir les erreurs SQL
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    // Vérifier quelles colonnes existent dans la table avances
    $has_montant_demande = false;
    $has_montant = false;
    
    try {
        $checkCol = $pdo->query("SHOW COLUMNS FROM avances");
        $columns = $checkCol->fetchAll(PDO::FETCH_COLUMN);
        $has_montant_demande = in_array('montant_demande', $columns);
        $has_montant = in_array('montant', $columns);
    } catch (Exception $e) {
        // Si la vérification échoue, on continue avec les colonnes de base
    }
    
    // Construire la requête SQL dynamiquement
    // Par défaut, utiliser 'montant' car c'est ce qui est utilisé dans l'affichage
    $montant_col = $has_montant ? 'montant' : ($has_montant_demande ? 'montant_demande' : 'montant');
    
    $sql = "INSERT INTO avances (user_id, $montant_col, date_besoin, motif, status) VALUES (?, ?, ?, ?, 'En_Attente')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $montant, $date_besoin, $motif]);
    
    $_SESSION['success'] = 'Votre demande d\'avance a été soumise avec succès.';
    header('Location: ../views/employe/mes_avances.php');
    exit();
    
} catch (PDOException $e) {
    error_log("Erreur lors de la soumission de l'avance: " . $e->getMessage());
    $_SESSION['error'] = 'Une erreur est survenue lors de la soumission de votre demande. Veuillez réessayer.';
    header('Location: ../views/employe/mes_avances.php');
    exit();
}

