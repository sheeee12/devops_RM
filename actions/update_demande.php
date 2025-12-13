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
    header('Location: ../views/employe/mes_brouillons.php');
    exit();
}

// Vérifier l'ID de la demande
if (!isset($_POST['id_dem']) || !is_numeric($_POST['id_dem'])) {
    $_SESSION['error'] = 'ID de demande invalide.';
    header('Location: ../views/employe/mes_brouillons.php');
    exit();
}

$id_dem = intval($_POST['id_dem']);

// Vérifier l'action (draft ou submit)
$action = $_POST['action'] ?? 'draft';

$pdo = Database::getInstance()->getConnexion();

// Activer le mode d'erreur PDO pour voir les erreurs SQL
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    // Vérifier que la demande appartient à l'utilisateur et est un brouillon
    $stmtCheck = $pdo->prepare("SELECT * FROM demande WHERE id_dem = ? AND user_id = ? AND status = 'Brouillon'");
    $stmtCheck->execute([$id_dem, $user_id]);
    $demande = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    
    if (!$demande) {
        $_SESSION['error'] = 'Cette demande n\'existe pas ou ne peut pas être modifiée.';
        header('Location: ../views/employe/mes_brouillons.php');
        exit();
    }
    
    $pdo->beginTransaction();
    
    // Récupérer les données du formulaire
    $titre_dem = trim($_POST['titre_dem'] ?? '');
    $date_mission = $_POST['date_mission'] ?? $demande['date_dep'];
    $date_fin = $_POST['date_fin'] ?? $date_mission;
    $avance_id = !empty($_POST['avance_id']) ? intval($_POST['avance_id']) : null;
    
    // Validation pour soumission
    if ($action === 'submit') {
        if (empty($titre_dem)) {
            $_SESSION['error'] = 'Le titre de la demande est requis.';
            header('Location: ../views/employe/modifier_brouillon.php?id=' . $id_dem);
            exit();
        }
    }
    
    // Vérifier le nom de la colonne ID dans expense_line (avant de l'utiliser)
    $id_col_name = 'id_line'; // Par défaut
    try {
        $checkCol = $pdo->query("SHOW COLUMNS FROM expense_line");
        $columns = $checkCol->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('id', $columns)) {
            $id_col_name = 'id';
        } elseif (in_array('id_line', $columns)) {
            $id_col_name = 'id_line';
        } elseif (in_array('id_expense', $columns)) {
            $id_col_name = 'id_expense';
        }
    } catch (Exception $e) {
        // Utiliser la valeur par défaut
    }
    
    // Calculer le montant total
    $montant_total = 0;
    $montant_avance = 0;
    
    // Récupérer les lignes existantes non supprimées depuis la base de données
    $delete_lines = $_POST['delete_lines'] ?? [];
    if (!empty($delete_lines) && is_array($delete_lines)) {
        $delete_lines = array_map('intval', $delete_lines);
        $placeholders = implode(',', array_fill(0, count($delete_lines), '?'));
        $sqlExisting = "SELECT montant FROM expense_line WHERE id_dem = ? AND $id_col_name NOT IN ($placeholders)";
        $paramsExisting = array_merge([$id_dem], $delete_lines);
    } else {
        $sqlExisting = "SELECT montant FROM expense_line WHERE id_dem = ?";
        $paramsExisting = [$id_dem];
    }
    
    $stmtExisting = $pdo->prepare($sqlExisting);
    $stmtExisting->execute($paramsExisting);
    $lignes_existantes = $stmtExisting->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($lignes_existantes as $ligne) {
        $montant_total += floatval($ligne['montant'] ?? 0);
    }
    
    // Ajouter les nouvelles lignes
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
    
    // Vérifier quelles colonnes existent dans la table demande
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
    
    // Construire la requête UPDATE dynamiquement
    $sqlUpdate = "UPDATE demande SET titre_dem = ?, date_dep = ?";
    $params = [$titre_dem ?: 'Brouillon sans titre', $date_mission];
    
    if ($has_date_fin) {
        $sqlUpdate .= ", date_fin = ?";
        $params[] = $date_fin;
    }
    
    $sqlUpdate .= ", montant_total = ?";
    $params[] = $montant_total;
    
    if ($has_montant_avance) {
        $sqlUpdate .= ", montant_avance = ?";
        $params[] = $montant_avance;
    }
    
    if ($has_avance_id) {
        $sqlUpdate .= ", avance_id = ?";
        $params[] = $avance_id;
    }
    
    $sqlUpdate .= ", status = ? WHERE id_dem = ?";
    $params[] = $status;
    $params[] = $id_dem;
    
    $stmtUpdate = $pdo->prepare($sqlUpdate);
    $stmtUpdate->execute($params);
    
    // Supprimer les lignes marquées pour suppression
    if (!empty($_POST['delete_lines']) && is_array($_POST['delete_lines'])) {
        foreach ($_POST['delete_lines'] as $line_id) {
            $stmtDel = $pdo->prepare("DELETE FROM expense_line WHERE $id_col_name = ? AND id_dem = ?");
            $stmtDel->execute([intval($line_id), $id_dem]);
        }
    }
    
    // Mettre à jour les lignes existantes (si le formulaire envoie ces données)
    // Note: Le formulaire modifier_brouillon.php ne permet actuellement que la suppression,
    // pas la modification des lignes existantes. Cette section est conservée pour compatibilité future.
    if (!empty($_POST['line_ids']) && is_array($_POST['line_ids'])) {
        $line_ids = $_POST['line_ids'];
        $categs_existants = $_POST['categs_existants'] ?? [];
        $dates_existants = $_POST['dates_existants'] ?? [];
        $montants_existants = $_POST['montants_existants'] ?? [];
        $descriptions_existants = $_POST['descriptions_existants'] ?? [];
        
        // Vérifier si la colonne description existe
        $has_description = false;
        try {
            $checkCol = $pdo->query("SHOW COLUMNS FROM expense_line");
            $columns = $checkCol->fetchAll(PDO::FETCH_COLUMN);
            $has_description = in_array('description', $columns);
        } catch (Exception $e) {
            // Si la vérification échoue, on continue sans description
        }
        
        // Utiliser le nom de colonne ID détecté
        if ($has_description) {
            $sqlUpdateLine = "UPDATE expense_line SET id_categ = ?, date_depense = ?, montant = ?, description = ? WHERE $id_col_name = ? AND id_dem = ?";
        } else {
            $sqlUpdateLine = "UPDATE expense_line SET id_categ = ?, date_depense = ?, montant = ? WHERE $id_col_name = ? AND id_dem = ?";
        }
        $stmtUpdateLine = $pdo->prepare($sqlUpdateLine);
        
        foreach ($line_ids as $index => $line_id) {
            if (empty($line_id)) continue;
            
            $id_categ = $categs_existants[$index] ?? null;
            $date_depense = $dates_existants[$index] ?? $date_mission;
            $montant = floatval($montants_existants[$index] ?? 0);
            $description = trim($descriptions_existants[$index] ?? '');
            
            if ($id_categ) {
                if ($has_description) {
                    $stmtUpdateLine->execute([
                        $id_categ,
                        $date_depense,
                        $montant,
                        $description,
                        intval($line_id),
                        $id_dem
                    ]);
                } else {
                    $stmtUpdateLine->execute([
                        $id_categ,
                        $date_depense,
                        $montant,
                        intval($line_id),
                        $id_dem
                    ]);
                }
            }
        }
    }
    
    // Créer le dossier d'upload s'il n'existe pas
    $uploadDir = __DIR__ . '/../uploads/proofs/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Ajouter les nouvelles lignes
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
        $_SESSION['success'] = 'Brouillon mis à jour avec succès.';
        header('Location: ../views/employe/mes_brouillons.php');
    }
    exit();
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $errorMsg = $e->getMessage();
    error_log('Erreur PDO update_demande.php: ' . $errorMsg);
    error_log('Code erreur: ' . $e->getCode());
    error_log('Trace: ' . $e->getTraceAsString());
    $_SESSION['error'] = 'Erreur lors de la mise à jour: ' . $errorMsg;
    header('Location: ../views/employe/modifier_brouillon.php?id=' . $id_dem);
    exit();
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $errorMsg = $e->getMessage();
    error_log('Erreur update_demande.php: ' . $errorMsg);
    error_log('Trace: ' . $e->getTraceAsString());
    $_SESSION['error'] = 'Une erreur est survenue: ' . $errorMsg;
    header('Location: ../views/employe/modifier_brouillon.php?id=' . $id_dem);
    exit();
}

