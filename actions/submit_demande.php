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
    header('Location: ../views/employe/nouvelle_demande.php');
    exit();
}

// Vérifier l'action (draft ou submit)
$action = $_POST['action'] ?? 'draft';

// Récupérer les données du formulaire
$titre_dem = trim($_POST['titre_dem'] ?? '');
// Le formulaire envoie date_mission pour la date de début
$date_mission = $_POST['date_mission'] ?? date('Y-m-d');
// Si date_fin n'est pas fournie, utiliser date_mission (mission d'un jour)
$date_fin = !empty($_POST['date_fin']) ? $_POST['date_fin'] : $date_mission;
$avance_id = !empty($_POST['avance_id']) ? intval($_POST['avance_id']) : null;

// Validation pour soumission (pas pour brouillon)
if ($action === 'submit') {
    if (empty($titre_dem)) {
        $_SESSION['error'] = 'Le titre de la demande est requis.';
        header('Location: ../views/employe/nouvelle_demande.php');
        exit();
    }
    
    if (empty($_POST['categs']) || !is_array($_POST['categs'])) {
        $_SESSION['error'] = 'Au moins une ligne de dépense est requise.';
        header('Location: ../views/employe/nouvelle_demande.php');
        exit();
    }
}

$pdo = Database::getInstance()->getConnexion();

// Activer le mode d'erreur PDO pour voir les erreurs SQL
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    $pdo->beginTransaction();
    
    // Calculer le montant total
    $montant_total = 0;
    $montant_avance = 0;
    
    if (!empty($_POST['montants']) && is_array($_POST['montants'])) {
        foreach ($_POST['montants'] as $montant) {
            $montant_total += floatval($montant);
        }
    }
    
    // Récupérer le montant de l'avance si sélectionnée
    if ($avance_id) {
        $stmtAv = $pdo->prepare("SELECT montant_demande FROM avances WHERE id_avance = ? AND user_id = ? AND status = 'Paye'");
        $stmtAv->execute([$avance_id, $user_id]);
        $avance = $stmtAv->fetch(PDO::FETCH_ASSOC);
        if ($avance) {
            $montant_avance = floatval($avance['montant_demande']);
        }
    }
    
    // Déterminer le statut
    $status = ($action === 'submit') ? 'Attente_Manager' : 'Brouillon';
    
    // Insérer la demande
    // Vérifier quelles colonnes existent dans la table
    $has_date_fin = false;
    $has_montant_avance = false;
    $has_avance_id = false;
    
    try {
        $checkCol = $pdo->query("SHOW COLUMNS FROM demande");
        $columns = $checkCol->fetchAll(PDO::FETCH_COLUMN);
        $has_date_fin = in_array('date_fin', $columns);
        $has_montant_avance = in_array('montant_avance', $columns);
        $has_avance_id = in_array('avance_id', $columns);
    } catch (Exception $e) {
        // Si la vérification échoue, on continue avec les colonnes de base
    }
    
    // Construire la requête SQL dynamiquement
    $sqlCols = "user_id, titre_dem, date_dep";
    $sqlVals = "?, ?, ?";
    $params = [$user_id, $titre_dem ?: 'Brouillon sans titre', $date_mission];
    
    if ($has_date_fin) {
        $sqlCols .= ", date_fin";
        $sqlVals .= ", ?";
        $params[] = $date_fin;
    }
    
    $sqlCols .= ", montant_total";
    $sqlVals .= ", ?";
    $params[] = $montant_total;
    
    if ($has_montant_avance) {
        $sqlCols .= ", montant_avance";
        $sqlVals .= ", ?";
        $params[] = $montant_avance;
    }
    
    if ($has_avance_id) {
        $sqlCols .= ", avance_id";
        $sqlVals .= ", ?";
        $params[] = $avance_id;
    }
    
    $sqlCols .= ", status, created_at";
    $sqlVals .= ", ?, NOW()";
    $params[] = $status;
    
    $sqlDemande = "INSERT INTO demande ($sqlCols) VALUES ($sqlVals)";
    $stmtDemande = $pdo->prepare($sqlDemande);
    $stmtDemande->execute($params);
    
    $id_dem = $pdo->lastInsertId();
    
    // Créer le dossier d'upload s'il n'existe pas
    $uploadDir = __DIR__ . '/../uploads/proofs/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Insérer les lignes de dépenses
    if (!empty($_POST['categs']) && is_array($_POST['categs'])) {
        $categs = $_POST['categs'];
        $dates = $_POST['dates'] ?? [];
        $montants = $_POST['montants'] ?? [];
        $descriptions = $_POST['descriptions'] ?? [];
        $justificatifs = $_FILES['justificatifs'] ?? [];
        
        // Vérifier quelles colonnes existent dans expense_line
        $has_description = false;
        try {
            $checkCol = $pdo->query("SHOW COLUMNS FROM expense_line");
            $columns = $checkCol->fetchAll(PDO::FETCH_COLUMN);
            $has_description = in_array('description', $columns);
        } catch (Exception $e) {
            // Si la vérification échoue, on continue sans description
        }
        
        // Construire la requête SQL dynamiquement
        $sqlCols = "id_dem, id_categ, date_depense, montant";
        $sqlVals = "?, ?, ?, ?";
        
        if ($has_description) {
            $sqlCols .= ", description";
            $sqlVals .= ", ?";
        }
        
        $sqlCols .= ", justificatif_path, details_specifiques";
        $sqlVals .= ", ?, ?";
        
        $sqlLine = "INSERT INTO expense_line ($sqlCols) VALUES ($sqlVals)";
        $stmtLine = $pdo->prepare($sqlLine);
        
        foreach ($categs as $index => $id_categ) {
            if (empty($id_categ)) continue;
            
            $date_depense = $dates[$index] ?? $date_mission;
            $montant = floatval($montants[$index] ?? 0);
            $description = trim($descriptions[$index] ?? '');
            
            // Gérer l'upload du justificatif
            $justificatif_path = null;
            if (isset($justificatifs['name'][$index]) && $justificatifs['error'][$index] === UPLOAD_ERR_OK) {
                $file = $justificatifs['tmp_name'][$index];
                $fileName = $justificatifs['name'][$index];
                $ext = pathinfo($fileName, PATHINFO_EXTENSION);
                $newFileName = 'proof_' . $id_dem . '_' . $index . '_' . time() . '.' . $ext;
                $targetPath = $uploadDir . $newFileName;
                
                if (move_uploaded_file($file, $targetPath)) {
                    // Sauvegarder seulement le nom du fichier, pas le chemin complet
                    $justificatif_path = $newFileName;
                }
            }
            
            // Récupérer le plafond de la catégorie
            $stmtCat = $pdo->prepare("SELECT plafond_max FROM categories WHERE id_categ = ?");
            $stmtCat->execute([$id_categ]);
            $categorie = $stmtCat->fetch(PDO::FETCH_ASSOC);
            $plafond_max = $categorie ? floatval($categorie['plafond_max']) : 0;
            
            // Créer les détails spécifiques (JSON)
            $details_specifiques = json_encode([
                'plafond_max' => $plafond_max,
                'description' => $description
            ]);
            
            // Construire les paramètres selon les colonnes disponibles
            $params = [
                $id_dem,
                $id_categ,
                $date_depense,
                $montant
            ];
            
            if ($has_description) {
                $params[] = $description;
            }
            
            $params[] = $justificatif_path;
            $params[] = $details_specifiques;
            
            $stmtLine->execute($params);
        }
    }
    
    $pdo->commit();
    
    // Redirection selon l'action
    if ($action === 'submit') {
        $_SESSION['success'] = 'Votre demande a été soumise avec succès.';
        header('Location: ../views/employe/mes_frais.php');
    } else {
        $_SESSION['success'] = 'Brouillon enregistré avec succès.';
        header('Location: ../views/employe/mes_brouillons.php');
    }
    exit();
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $errorMsg = $e->getMessage();
    error_log('Erreur PDO submit_demande.php: ' . $errorMsg);
    error_log('Code erreur: ' . $e->getCode());
    error_log('Trace: ' . $e->getTraceAsString());
    
    // Message d'erreur plus détaillé pour le débogage (en développement)
    // En production, vous pouvez masquer les détails techniques
    $_SESSION['error'] = 'Erreur lors de l\'enregistrement: ' . $errorMsg;
    header('Location: ../views/employe/nouvelle_demande.php');
    exit();
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $errorMsg = $e->getMessage();
    error_log('Erreur submit_demande.php: ' . $errorMsg);
    error_log('Trace: ' . $e->getTraceAsString());
    $_SESSION['error'] = 'Une erreur est survenue: ' . $errorMsg;
    header('Location: ../views/employe/nouvelle_demande.php');
    exit();
}

