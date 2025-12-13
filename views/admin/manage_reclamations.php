<?php
// -------------------------------------------------------------------------
// VUE ADMIN : HELPDESK (LISTE + MODALE + FILTRES AVANCÉS)
// -------------------------------------------------------------------------
require_once __DIR__ . '/../../includes/session.php';
requireRole('admin');
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../includes/security.php';

$pdo = Database::getInstance()->getConnexion();

// Récupérer les infos utilisateur
$user_id = $_SESSION['user_id'] ?? $_SESSION['user']['user_id'] ?? null;
if (!$user_id) {
    header('Location: ../../views/auth/login.php');
    exit;
}

$stmt = $pdo->prepare("SELECT user_id, nom, prenom, email, avatar FROM users WHERE user_id = ? AND role = 'admin'");
$stmt->execute([$user_id]);
$userInfo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$userInfo) {
    header('Location: ../../views/auth/login.php');
    exit;
}

$user_name = $userInfo['nom'] . ' ' . ($userInfo['prenom'] ?? '');
$avatar_bdd = $userInfo['avatar'] ?? 'default.png';
$chemin_physique = __DIR__ . '/../../assets/img/' . $avatar_bdd;
$avatar = (file_exists($chemin_physique) && !empty($avatar_bdd)) ? '../../assets/img/' . $avatar_bdd : '../../assets/img/default.png';

$db = $pdo;

// Vérifier si la colonne priorite existe dans la table reclamations
$has_priorite_column = false;
try {
    $checkCol = $db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                           WHERE TABLE_SCHEMA = DATABASE() 
                           AND TABLE_NAME = 'reclamations' 
                           AND COLUMN_NAME = 'priorite'");
    $has_priorite_column = ($checkCol->fetchColumn() > 0);
} catch (PDOException $e) {
    $has_priorite_column = false;
}

// Vérifier quelle colonne de statut existe (statut ou status)
$statut_column_name = 'statut'; // Par défaut
try {
    $checkStatut = $db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                              WHERE TABLE_SCHEMA = DATABASE() 
                              AND TABLE_NAME = 'reclamations' 
                              AND COLUMN_NAME = 'statut'");
    if ($checkStatut->fetchColumn() == 0) {
        // Si statut n'existe pas, vérifier status
        $checkStatus = $db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                                  WHERE TABLE_SCHEMA = DATABASE() 
                                  AND TABLE_NAME = 'reclamations' 
                                  AND COLUMN_NAME = 'status'");
        if ($checkStatus->fetchColumn() > 0) {
            $statut_column_name = 'status';
        }
    }
} catch (PDOException $e) {
    // Par défaut, utiliser 'statut'
}

// Vérifier si la colonne type_reclamation existe dans la table reclamations
$has_type_reclamation_column = false;
try {
    $checkCol = $db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                           WHERE TABLE_SCHEMA = DATABASE() 
                           AND TABLE_NAME = 'reclamations' 
                           AND COLUMN_NAME = 'type_reclamation'");
    $has_type_reclamation_column = ($checkCol->fetchColumn() > 0);
} catch (PDOException $e) {
    $has_type_reclamation_column = false;
}

// Vérifier si la colonne id_reclamation existe dans la table reclamations
$has_id_reclamation_column = false;
try {
    $checkCol = $db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                           WHERE TABLE_SCHEMA = DATABASE() 
                           AND TABLE_NAME = 'reclamations' 
                           AND COLUMN_NAME = 'id_reclamation'");
    $has_id_reclamation_column = ($checkCol->fetchColumn() > 0);
} catch (PDOException $e) {
    $has_id_reclamation_column = false;
}

// La table reclamation_messages utilise id_message selon le schéma réel
$reclamation_messages_id_column = 'id_message';

// ID du ticket sélectionné (via URL pour ouvrir la modale)
$selected_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// --- TRAITEMENT DES ACTIONS (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ENVOYER UNE RÉPONSE OU NOTE INTERNE
    if (isset($_POST['action']) && $_POST['action'] === 'reply') {
        $rec_id = intval($_POST['reclamation_id']);
        $msg = trim($_POST['message']);
        $is_internal = isset($_POST['is_internal']) ? 1 : 0;

        // La table reclamations utilise id_reclam selon le schéma réel
        $reclamation_id_for_messages = $rec_id;

        $pj = null;
        if (!empty($_FILES['attachment']['name'])) {
            $uploadDir = __DIR__ . '/../../uploads/reclamations/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
            $pj = uniqid('reply_', true) . '.' . $ext;
            move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir . $pj);
        }

        if (!empty($msg) || $pj) {
            $stmt = $db->prepare("INSERT INTO reclamation_messages (reclamation_id, user_id, message, is_internal, piece_jointe) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$reclamation_id_for_messages, $user_id, $msg, $is_internal, $pj]);

            // Si c'est une réponse publique, on passe en "En Cours" si c'était "Ouvert"
            if (!$is_internal) {
                try {
                    $db->prepare("UPDATE reclamations SET {$statut_column_name} = 'En Cours' WHERE id_reclam = ? AND {$statut_column_name} = 'Ouvert'")->execute([$rec_id]);
                } catch (PDOException $e) {
                    error_log("Erreur mise à jour statut lors de la réponse: " . $e->getMessage());
                }
                
                // Créer une notification pour l'employé concerné
                try {
                    // Récupérer l'ID de l'employé via la réclamation et la demande
                    $stmtUser = $db->prepare("SELECT d.user_id, r.id_reclam 
                                             FROM reclamations r 
                                             LEFT JOIN demande d ON r.id_dem = d.id_dem 
                                             WHERE r.id_reclam = ?");
                    $stmtUser->execute([$rec_id]);
                    $reclamationData = $stmtUser->fetch(PDO::FETCH_ASSOC);
                    
                    if ($reclamationData && $reclamationData['user_id']) {
                        // Créer la table notifications si elle n'existe pas
                        $db->exec("CREATE TABLE IF NOT EXISTS notifications (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            user_id INT NOT NULL,
                            type ENUM('clarification', 'validation', 'rejet', 'payment', 'reclamation_reply') NOT NULL,
                            title VARCHAR(255) NOT NULL,
                            message TEXT NOT NULL,
                            related_id INT NULL,
                            is_read TINYINT(1) DEFAULT 0,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
                        )");
                        
                        // Insérer la notification
                        $notificationTitle = "Nouvelle réponse à votre réclamation";
                        $notificationMessage = "Vous avez reçu une nouvelle réponse concernant votre réclamation #" . $rec_id;
                        $stmtNotif = $db->prepare("INSERT INTO notifications (user_id, type, title, message, related_id) VALUES (?, 'reclamation_reply', ?, ?, ?)");
                        $stmtNotif->execute([$reclamationData['user_id'], $notificationTitle, $notificationMessage, $rec_id]);
                    }
                } catch (PDOException $e) {
                    // Erreur lors de la création de la notification, continuer quand même
                    error_log("Erreur création notification réclamation: " . $e->getMessage());
                }
            }
        }

        // Reconstruction de l'URL pour garder les filtres après post
        $query_string = $_SERVER['QUERY_STRING'];
        header("Location: manage_reclamations.php?" . $query_string);
        exit();
    }

    // MISE À JOUR ÉTAT / PRIORITÉ
    if (isset($_POST['action']) && $_POST['action'] === 'update_meta') {
        $rec_id = intval($_POST['reclamation_id']);
        $status = $_POST['status'];
        $priorite = $_POST['priorite'] ?? 'Moyenne'; // Valeur par défaut si pas de priorité

        // Récupérer les valeurs ENUM réelles de la colonne statut
        $enum_values = [];
        try {
            $checkEnum = $db->query("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
                                    WHERE TABLE_SCHEMA = DATABASE() 
                                    AND TABLE_NAME = 'reclamations' 
                                    AND COLUMN_NAME = '{$statut_column_name}'");
            $enumStr = $checkEnum->fetchColumn();
            if ($enumStr) {
                // Extraire les valeurs ENUM : ENUM('valeur1','valeur2',...)
                preg_match_all("/'([^']+)'/", $enumStr, $matches);
                if (!empty($matches[1])) {
                    $enum_values = $matches[1];
                }
            }
        } catch (PDOException $e) {
            error_log("Erreur récupération valeurs ENUM: " . $e->getMessage());
        }
        
        // Normaliser le statut selon les valeurs ENUM réelles
        $normalized_status = $status;
        if (!empty($enum_values)) {
            // Mapper les valeurs du formulaire vers les valeurs ENUM de la BDD
            // Interface et BDD utilisent maintenant les mêmes valeurs : "Ouvert", "En Cours", "Résolu", "Fermé"
            $status_map = [
                'Ouvert' => 'Ouvert',
                'Ouverte' => 'Ouvert',
                'En_Cours' => 'En Cours',
                'En Cours' => 'En Cours',
                'Resolu' => 'Résolu',
                'Résolu' => 'Résolu',
                'Ferme' => 'Fermé',
                'Fermé' => 'Fermé'
            ];
            
            // Chercher la valeur correspondante dans l'ENUM
            $found = false;
            foreach ($enum_values as $enum_val) {
                if (strcasecmp($status, $enum_val) == 0 || 
                    (isset($status_map[$status]) && strcasecmp($status_map[$status], $enum_val) == 0)) {
                    $normalized_status = $enum_val;
                    $found = true;
                    break;
                }
            }
            
            // Si pas trouvé, essayer de trouver une correspondance partielle
            if (!$found) {
                foreach ($enum_values as $enum_val) {
                    if (stripos($enum_val, $status) !== false || stripos($status, $enum_val) !== false) {
                        $normalized_status = $enum_val;
                        $found = true;
                        break;
                    }
                }
            }
            
            // Si toujours pas trouvé, utiliser la première valeur ENUM disponible
            if (!$found && !empty($enum_values)) {
                $normalized_status = $enum_values[0];
                error_log("ATTENTION: Statut '$status' non trouvé dans ENUM, utilisation de '$normalized_status'");
            }
        }

        // Mettre à jour statut et priorité
        try {
            if ($has_priorite_column) {
                $sql = "UPDATE reclamations SET {$statut_column_name} = ?, priorite = ? WHERE id_reclam = ?";
                $stmt = $db->prepare($sql);
                $result = $stmt->execute([$normalized_status, $priorite, $rec_id]);
            } else {
                $sql = "UPDATE reclamations SET {$statut_column_name} = ? WHERE id_reclam = ?";
                $stmt = $db->prepare($sql);
                $result = $stmt->execute([$normalized_status, $rec_id]);
            }
            
            // Vérifier si la mise à jour a réussi
            $rowsAffected = $stmt->rowCount();
            if ($rowsAffected == 0) {
                error_log("ATTENTION: Aucune ligne mise à jour pour la réclamation #$rec_id");
                error_log("SQL exécuté: $sql");
                error_log("Valeurs: status=$normalized_status (original=$status), priorite=$priorite, rec_id=$rec_id");
                error_log("Valeurs ENUM disponibles: " . implode(', ', $enum_values));
            } else {
                error_log("SUCCÈS: $rowsAffected ligne(s) mise(s) à jour pour la réclamation #$rec_id");
                error_log("Statut changé de '{$status}' vers '{$normalized_status}'");
            }
        } catch (PDOException $e) {
            error_log("ERREUR SQL mise à jour statut réclamation: " . $e->getMessage());
            error_log("Code erreur: " . $e->getCode());
            error_log("SQL: UPDATE reclamations SET {$statut_column_name} = ? WHERE id_reclam = ?");
            error_log("Valeurs: status=$normalized_status (original=$status), rec_id=$rec_id");
            error_log("Valeurs ENUM disponibles: " . implode(', ', $enum_values));
            // Ne pas arrêter l'exécution, continuer pour créer le message système
        }

        // Message système
        $sysMsg = "<em>[Système] Ticket mis à jour : $status" . ($has_priorite_column ? " / Priorité $priorite" : "") . "</em>";
        $db->prepare("INSERT INTO reclamation_messages (reclamation_id, user_id, message, is_internal) VALUES (?, ?, ?, 1)")
            ->execute([$rec_id, $user_id, $sysMsg]);

        // Rediriger en gardant les filtres mais en fermant la modale (enlever l'ID)
        $redirectParams = $_GET;
        unset($redirectParams['id']); // Fermer la modale
        $redirectUrl = 'manage_reclamations.php';
        if (!empty($redirectParams)) {
            $redirectUrl .= '?' . http_build_query($redirectParams);
        }
        header("Location: " . $redirectUrl);
        exit();
    }
}

// --- RECUPERATION DONNEES AVEC FILTRES ---

$sqlList = "SELECT r.id_reclam, r.id_dem, r.message, 
            COALESCE(r.{$statut_column_name}, 'Ouvert') as statut, r.created_at,
            " . ($has_priorite_column ? "r.priorite, " : "") . "
            u.nom as user_nom, u.avatar as user_avatar, d.user_id as demande_user_id
            FROM reclamations r 
            LEFT JOIN demande d ON r.id_dem = d.id_dem
            LEFT JOIN users u ON d.user_id = u.user_id 
            WHERE 1=1";
$params = [];

// Recherche (ID, Message, Nom demandeur)
if (!empty($_GET['search'])) {
    $search = "%" . trim($_GET['search']) . "%";
    $sqlList .= " AND (r.id_reclam LIKE ? OR r.message LIKE ? OR u.nom LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

// Filtre Statut
if (!empty($_GET['f_status'])) {
    $sqlList .= " AND r.{$statut_column_name} = ?";
    $params[] = $_GET['f_status'];
}

// Filtre Priorité
if (!empty($_GET['f_prio']) && $has_priorite_column) {
    $sqlList .= " AND r.priorite = ?";
    $params[] = $_GET['f_prio'];
}

// Tri
$sort = $_GET['sort'] ?? 'date_desc';
switch ($sort) {
    case 'date_asc':
        $sqlList .= " ORDER BY r.created_at ASC";
        break;
    case 'prio_high':
        if ($has_priorite_column) {
        $sqlList .= " ORDER BY FIELD(r.priorite, 'Haute', 'Moyenne', 'Basse'), r.created_at DESC";
        } else {
            $sqlList .= " ORDER BY r.created_at DESC";
        }
        break;
    case 'status':
        $sqlList .= " ORDER BY r.{$statut_column_name} ASC, r.created_at DESC";
        break;
    default:
        $sqlList .= " ORDER BY r.created_at DESC";
        break; // date_desc
}

$stmt = $db->prepare($sqlList);
$stmt->execute($params);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);


// --- DÉTAILS DU TICKET SÉLECTIONNÉ (POUR LA MODALE) ---
$current_ticket = null;
$messages = [];

if ($selected_id > 0) {
    // La table reclamations utilise id_reclam selon le schéma réel
    $stmt = $db->prepare("SELECT r.*, 
                          COALESCE(r.{$statut_column_name}, 'Ouvert') as statut,
                          u.nom as user_nom, u.email as user_email, u.avatar as user_avatar, u.role as user_role, 
                          d.titre_dem, d.id_dem, d.user_id as demande_user_id
                          FROM reclamations r 
                          LEFT JOIN demande d ON r.id_dem = d.id_dem
                          LEFT JOIN users u ON d.user_id = u.user_id 
                          WHERE r.id_reclam = ?");
    $stmt->execute([$selected_id]);
    $current_ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($current_ticket) {
        // Utiliser id_reclam directement
        $reclamation_id_for_messages = $selected_id;
        
        $initial_msg = [
            'id_msg' => 0, // Clé fixe pour compatibilité
            'user_id' => $current_ticket['demande_user_id'] ?? null,
            'nom_user' => $current_ticket['user_nom'],
            'avatar_user' => $current_ticket['user_avatar'],
            'message' => $current_ticket['message'],
            'piece_jointe' => $current_ticket['piece_jointe'] ?? null,
            'created_at' => $current_ticket['created_at'],
            'is_internal' => 0,
            'role_user' => $current_ticket['user_role']
        ];

        // La table reclamation_messages utilise id_message selon le schéma réel
        $stmtMsg = $db->prepare("
            SELECT m.id_message as id_msg, m.reclamation_id, m.user_id, m.message, m.piece_jointe, m.is_internal, m.created_at,
                   u.nom as nom_user, u.avatar as avatar_user, u.role as role_user 
            FROM reclamation_messages m 
            JOIN users u ON m.user_id = u.user_id 
            WHERE m.reclamation_id = ? 
            ORDER BY m.created_at ASC
        ");
        $stmtMsg->execute([$reclamation_id_for_messages]);
        $replies = $stmtMsg->fetchAll(PDO::FETCH_ASSOC);
        $messages = array_merge([$initial_msg], $replies);
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Helpdesk | Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <style>
        :root {
            --app-bg: #f8fafc;
            --header-bg: #ffffff;
            --header-border: #e2e8f0;
            --primary: #059669;
            --primary-dark: #047857;
            --primary-light: #10b981;
            --text-main: #1e293b;
            --text-light: #64748b;
            --card-border: #e2e8f0;
        }

        [data-theme="dark"] {
            --app-bg: #0f172a;
            --header-bg: #1e293b;
            --header-border: #334155;
            --text-main: #f1f5f9;
            --text-light: #94a3b8;
            --card-border: #334155;
        }

        /* Styles pour le mode sombre - Cartes et Tableaux */
        [data-theme="dark"] .card-widget,
        [data-theme="dark"] .card,
        [data-theme="dark"] .table-custom td,
        [data-theme="dark"] .ticket-card {
            background: #1e293b !important;
            color: var(--text-main) !important;
            border-color: var(--card-border) !important;
        }

        [data-theme="dark"] .table-custom tr:hover td {
            background-color: #334155 !important;
            border-color: #475569 !important;
        }

        [data-theme="dark"] .table-custom th {
            color: var(--text-light) !important;
        }

        [data-theme="dark"] .table-custom td:first-child,
        [data-theme="dark"] .table-custom td:last-child {
            border-color: var(--card-border) !important;
        }

        /* Autres éléments en mode sombre */
        [data-theme="dark"] .modal-content,
        [data-theme="dark"] .dropdown-menu {
            background: #1e293b !important;
            border-color: var(--card-border) !important;
            color: var(--text-main) !important;
        }

        [data-theme="dark"] .form-control,
        [data-theme="dark"] .form-select {
            background: #1e293b !important;
            border-color: var(--card-border) !important;
            color: var(--text-main) !important;
        }

        [data-theme="dark"] .form-control:focus,
        [data-theme="dark"] .form-select:focus {
            background: #1e293b !important;
            border-color: var(--primary) !important;
            color: var(--text-main) !important;
        }

        /* Tous les éléments avec background white */
        [data-theme="dark"] *[style*="background: white"],
        [data-theme="dark"] *[style*="background-color: white"],
        [data-theme="dark"] *[style*="background:#fff"],
        [data-theme="dark"] *[style*="background-color:#fff"],
        [data-theme="dark"] .bg-white,
        [data-theme="dark"] .input-group-text,
        [data-theme="dark"] .modal-header,
        [data-theme="dark"] .modal-footer,
        [data-theme="dark"] .list-group-item,
        [data-theme="dark"] .badge,
        [data-theme="dark"] .chat-message,
        [data-theme="dark"] .chat-container {
            background: #1e293b !important;
            background-color: #1e293b !important;
            color: var(--text-main) !important;
            border-color: var(--card-border) !important;
        }

        [data-theme="dark"] .input-group-text {
            border-color: var(--card-border) !important;
        }

        [data-theme="dark"] .chat-message.user {
            background: #334155 !important;
        }

        [data-theme="dark"] .chat-message.admin {
            background: var(--primary) !important;
            color: white !important;
        }

        /* Éléments spécifiques manage_reclamations */
        [data-theme="dark"] .card-list,
        [data-theme="dark"] .list-header,
        [data-theme="dark"] .ticket-row,
        [data-theme="dark"] .filter-bar,
        [data-theme="dark"] .modal-chat-body {
            background: #1e293b !important;
            background-color: #1e293b !important;
            color: var(--text-main) !important;
            border-color: var(--card-border) !important;
        }

        [data-theme="dark"] .ticket-row:hover {
            background: #334155 !important;
        }

        [data-theme="dark"] .modal-chat-body {
            background: #0f172a !important;
        }

        [data-theme="dark"] .msg-bubble {
            background: #334155 !important;
            color: var(--text-main) !important;
        }

        [data-theme="dark"] .msg-bubble.admin {
            background: var(--primary) !important;
            color: white !important;
        }

        /* Textes */
        [data-theme="dark"] .text-muted,
        [data-theme="dark"] .text-secondary,
        [data-theme="dark"] small {
            color: var(--text-light) !important;
        }

        [data-theme="dark"] .text-dark {
            color: var(--text-main) !important;
        }

        [data-theme="dark"] h1,
        [data-theme="dark"] h2,
        [data-theme="dark"] h3,
        [data-theme="dark"] h4,
        [data-theme="dark"] h5,
        [data-theme="dark"] h6 {
            color: var(--text-main) !important;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--app-bg);
            color: var(--text-main);
            padding-top: 70px;
            font-size: 0.875rem;
            overflow-x: hidden;
            transition: background-color 0.3s, color 0.3s;
        }

        /* HEADER */
        .app-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: var(--header-bg);
            border-bottom: 1px solid var(--header-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            z-index: 1000;
            transition: background-color 0.3s, border-color 0.3s;
        }

        /* LOGO ANIMATION */
        @keyframes logo-bounce {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.1);
                box-shadow: 0 0 15px rgba(5, 150, 105, 0.4);
            }
        }

        .brand-logo {
            width: 32px;
            height: 32px;
            background: var(--primary);
            color: white;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .brand-logo:hover {
            animation: logo-bounce 1s infinite;
            background-color: #047857;
        }

        .app-header .d-flex span {
            font-weight: 700 !important;
        }

        .app-nav {
            display: flex;
            gap: 6px;
            height: 100%;
            margin-left: 20px;
        }
        
        .nav-item-link {
            color: var(--text-light);
            text-decoration: none;
            padding: 0 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            height: 100%;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
            font-size: 0.9rem;
        }

        .nav-item-link:hover {
            background-color: #f1f5f9;
            color: var(--primary);
        }

        .nav-item-link.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
            background-color: transparent;
        }

        .avatar-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid #ecfdf5;
            object-fit: cover;
        }

        /* LISTE CARD & FILTERS */
        .card-list {
            background: white;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .filter-bar {
            background-color: #f8fafc;
            padding: 15px 20px;
            border-bottom: 1px solid #f1f5f9;
        }

        .list-header {
            padding: 15px 20px;
            border-bottom: 1px solid #f1f5f9;
            background: #fff;
        }

        .ticket-row {
            padding: 15px 20px;
            border-bottom: 1px solid #f1f5f9;
            cursor: pointer;
            transition: 0.2s;
            display: block;
            text-decoration: none;
            color: inherit;
        }

        .ticket-row:hover {
            background: #f8fafc;
        }

        .ticket-row:last-child {
            border-bottom: none;
        }

        /* MODALE CHAT */
        .modal-chat-body {
            background: #f1f5f9;
            height: 500px;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* BULLES */
        .msg-row {
            display: flex;
            gap: 15px;
            max-width: 85%;
        }

        .msg-user {
            align-self: flex-start;
        }

        .msg-admin {
            align-self: flex-end;
            flex-direction: row-reverse;
        }

        .msg-internal {
            align-self: center;
            width: 100%;
            max-width: 90%;
        }

        .bubble {
            padding: 15px;
            border-radius: 12px;
            font-size: 0.9rem;
            line-height: 1.5;
            position: relative;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .msg-user .bubble {
            background: white;
            border: 1px solid #e2e8f0;
            border-top-left-radius: 0;
            color: #1e293b;
        }

        .msg-admin .bubble {
            background: var(--primary);
            color: white;
            border-top-right-radius: 0;
        }

        .msg-internal .bubble {
            background: #fffbeb;
            border: 1px solid #fcd34d;
            color: #92400e;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            width: 100%;
        }

        .chat-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .msg-meta {
            font-size: 0.7rem;
            margin-top: 5px;
            opacity: 0.7;
        }

        .msg-admin .msg-meta {
            text-align: right;
            color: rgba(255, 255, 255, 0.9);
        }

        /* BADGES */
        .badge-prio {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .prio-Haute {
            background: #fee2e2;
            color: #991b1b;
        }

        .prio-Moyenne {
            background: #ffedd5;
            color: #9a3412;
        }

        .prio-Basse {
            background: #f1f5f9;
            color: #64748b;
        }
    </style>
</head>

<body>

    <!-- HEADER NAVIGATION -->
    <header class="app-header">
        <div class="d-flex align-items-center gap-2">
            <div class="brand-logo">RM</div> <span class="fw-bold text-dark">RembourseMaroc</span>
        </div>
        <!-- NAVBAR ADMIN UNIFIÉE -->
        <nav class="app-nav d-none d-md-flex">
            <a href="dashboard.php"
                class="nav-item-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="bi bi-grid-fill me-2"></i>Dashboard
            </a>
            <a href="manage_pending.php"
                class="nav-item-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_pending.php' ? 'active' : ''; ?>">
                <i class="bi bi-layers-fill me-2"></i>Paiements
            </a>
            <a href="manage_data.php?tab=users"
                class="nav-item-link <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_data.php' && (!isset($_GET['tab']) || $_GET['tab'] == 'users')) ? 'active' : ''; ?>">
                <i class="bi bi-people me-2"></i>Utilisateurs
            </a>
            <a href="manage_data.php?tab=teams"
                class="nav-item-link <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_data.php' && (isset($_GET['tab']) && $_GET['tab'] == 'teams')) ? 'active' : ''; ?>">
                <i class="bi bi-diagram-3 me-2"></i>Équipes
            </a>
            <a href="manage_categories.php"
                class="nav-item-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_categories.php' ? 'active' : ''; ?>">
                <i class="bi bi-tags me-2"></i>Catégories
            </a>
            <a href="manage_reclamations.php"
                class="nav-item-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_reclamations.php' ? 'active' : ''; ?>">
                <i class="bi bi-life-preserver me-2"></i>Réclamations
            </a>
        </nav>
        <div class="dropdown">
            <a href="#" class="d-flex align-items-center gap-2 text-decoration-none" data-bs-toggle="dropdown">
                <div class="text-end d-none d-sm-block">
                    <div class="fw-bold text-dark small"><?= htmlspecialchars($user_name) ?></div>
                    <div class="text-muted" style="font-size: 0.65rem;">Administrateur</div>
                </div>
                <img src="<?= htmlspecialchars($avatar) ?>" class="avatar-circle">
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-3 p-2 rounded-3">
                <li><a class="dropdown-item rounded-2" href="profil.php">Mon Profil</a></li>
                <li>
                    <hr class="dropdown-divider">
                </li>
                <li><a class="dropdown-item rounded-2 text-danger" href="../../actions/logout.php">Déconnexion</a></li>
            </ul>
        </div>
    </header>


    <div class="container-fluid px-4 px-xl-5" style="max-width: 1500px; margin-top: 20px;">

        <!-- TITRE -->
        <div class="mb-4">
            <h3 class="fw-bolder m-0" style="color: var(--primary);">Gestion des Tickets</h3>
            <div class="text-muted">Suivi et résolution des réclamations collaborateurs.</div>
        </div>

        <!-- LISTE DES TICKETS AVEC FILTRES -->
        <div class="card-list">

            <!-- BARRE DE FILTRES AVANCÉS -->
            <div class="filter-bar">
                <form method="GET" class="row g-2 align-items-center">

                    <!-- Recherche Texte -->
                    <div class="col-md-4">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0"><i
                                    class="bi bi-search text-muted"></i></span>
                            <input type="text" name="search" class="form-control border-start-0 ps-0"
                                placeholder="N° Ticket, Sujet, Demandeur..."
                                value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                        </div>
                    </div>

                    <!-- Filtre Statut -->
                    <div class="col-md-2">
                        <select name="f_status" class="form-select form-select-sm">
                            <option value="">-- Tous Statuts --</option>
                            <option value="Ouvert"
                                <?= (isset($_GET['f_status']) && $_GET['f_status'] == 'Ouvert') ? 'selected' : '' ?>>Ouvert
                            </option>
                            <option value="En Cours"
                                <?= (isset($_GET['f_status']) && $_GET['f_status'] == 'En Cours') ? 'selected' : '' ?>>En
                                Cours</option>
                            <option value="Résolu"
                                <?= (isset($_GET['f_status']) && $_GET['f_status'] == 'Résolu') ? 'selected' : '' ?>>Résolu
                            </option>
                            <option value="Fermé"
                                <?= (isset($_GET['f_status']) && $_GET['f_status'] == 'Fermé') ? 'selected' : '' ?>>Fermé
                            </option>
                        </select>
                    </div>

                    <!-- Filtre Priorité -->
                    <div class="col-md-2">
                        <select name="f_prio" class="form-select form-select-sm">
                            <option value="">-- Toute Priorité --</option>
                            <option value="Haute"
                                <?= (isset($_GET['f_prio']) && $_GET['f_prio'] == 'Haute') ? 'selected' : '' ?>>Haute</option>
                            <option value="Moyenne"
                                <?= (isset($_GET['f_prio']) && $_GET['f_prio'] == 'Moyenne') ? 'selected' : '' ?>>Moyenne
                            </option>
                            <option value="Basse"
                                <?= (isset($_GET['f_prio']) && $_GET['f_prio'] == 'Basse') ? 'selected' : '' ?>>Basse</option>
                        </select>
                    </div>

                    <!-- Tri -->
                    <div class="col-md-2">
                        <select name="sort" class="form-select form-select-sm">
                            <option value="date_desc"
                                <?= (isset($_GET['sort']) && $_GET['sort'] == 'date_desc') ? 'selected' : '' ?>>Date (Récent)
                            </option>
                            <option value="date_asc"
                                <?= (isset($_GET['sort']) && $_GET['sort'] == 'date_asc') ? 'selected' : '' ?>>Date (Ancien)
                            </option>
                            <option value="prio_high"
                                <?= (isset($_GET['sort']) && $_GET['sort'] == 'prio_high') ? 'selected' : '' ?>>Priorité Haute
                            </option>
                            <option value="status"
                                <?= (isset($_GET['sort']) && $_GET['sort'] == 'status') ? 'selected' : '' ?>>Par Statut
                            </option>
                        </select>
                    </div>

                    <!-- Boutons -->
                    <div class="col-md-2 d-flex gap-1">
                        <button type="submit" class="btn btn-sm btn-primary flex-grow-1">Filtrer</button>
                        <a href="manage_reclamations.php" class="btn btn-sm btn-outline-secondary" title="Reset"><i
                                class="bi bi-arrow-counterclockwise"></i></a>
                    </div>
                </form>
            </div>

            <!-- EN-TÊTE TABLEAU -->
            <div class="list-header d-flex text-muted small fw-bold text-uppercase">
                <div style="width: 80px;">#ID</div>
                <div style="width: 200px;">Demandeur</div>
                <div class="flex-grow-1">Sujet & Contexte</div>
                <div style="width: 120px;">Priorité</div>
                <div style="width: 120px;">Statut</div>
                <div style="width: 120px;" class="text-end">Date</div>
            </div>

            <?php if (empty($tickets)): ?>
                <div class="text-center p-5 text-muted">
                    <i class="bi bi-inbox fs-1 mb-3 d-block opacity-25"></i> Aucun ticket ne correspond à votre recherche.
                </div>
            <?php else: ?>
                <?php foreach ($tickets as $t):
                    $u_img = !empty($t['user_avatar']) ? '../../assets/img/' . $t['user_avatar'] : '../../assets/img/default.png';
                    // Reconstruire l'URL avec les filtres actuels pour ne pas les perdre en ouvrant un ticket
                    $currentParams = $_GET;
                    $currentParams['id'] = $t['id_reclam'] ?? '';
                    $link = '?' . http_build_query($currentParams);
                ?>
                    <a href="<?= htmlspecialchars($link) ?>" class="ticket-row">
                        <div class="d-flex align-items-center">
                            <div style="width: 80px;" class="fw-bold text-muted font-monospace">#<?= $t['id_reclam'] ?? '' ?>
                            </div>
                            <div style="width: 200px;" class="d-flex align-items-center gap-2">
                                <img src="<?= htmlspecialchars($u_img) ?>" class="rounded-circle border" width="28" height="28">
                                <span
                                    class="small fw-bold text-dark text-truncate"><?= htmlspecialchars(substr($t['user_nom'], 0, 18)) ?></span>
                            </div>
                            <div class="flex-grow-1">
                                <?php 
                                // Utiliser le message comme sujet (car colonne sujet n'existe pas)
                                $sujet = substr($t['message'] ?? 'Sans sujet', 0, 50);
                                // Type de réclamation n'existe pas en BDD, on peut afficher vide ou un texte par défaut
                                $type_reclamation = ''; // Colonne n'existe pas
                                ?>
                                <span class="fw-bold text-dark"><?= htmlspecialchars($sujet) ?></span>
                                <?php if (!empty($type_reclamation)): ?>
                                    <span class="small text-muted ms-2"><?= str_replace('_', ' ', $type_reclamation) ?></span>
                                <?php endif; ?>
                            </div>
                            <div style="width: 120px;">
                                <?php 
                                // Priorité n'existe pas en BDD, afficher "Moyenne" par défaut
                                $priorite = $t['priorite'] ?? 'Moyenne';
                                ?>
                                <span class="badge-prio prio-<?= $priorite ?>"><?= htmlspecialchars($priorite) ?></span>
                            </div>
                            <div style="width: 120px;">
                                <?php 
                                // Le statut vient directement de la requête SQL avec COALESCE
                                // Les valeurs sont déjà correctes dans la BDD : "Ouvert", "En Cours", "Résolu", "Fermé"
                                $statut = $t['statut'] ?? 'Ouvert';
                                ?>
                                <span
                                    class="badge bg-light text-dark border small"><?= htmlspecialchars($statut) ?></span>
                            </div>
                            <div style="width: 120px;" class="text-end text-muted small">
                                <?= date('d/m/Y', strtotime($t['created_at'])) ?>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- MODAL CONVERSATION (Chat) -->
    <div class="modal fade" id="modalChat" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <?php if ($current_ticket): ?>
                <div class="modal-content border-0 shadow-lg" style="height: 85vh;">

                    <!-- HEADER MODAL -->
                    <div class="modal-header bg-white border-bottom py-3">
                        <div class="w-100 d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="modal-title fw-bold">#<?= $current_ticket['id_reclam'] ?? '' ?> -
                                    <?= htmlspecialchars(substr($current_ticket['message'] ?? 'Sans sujet', 0, 50)) ?></h5>
                                <div class="text-muted small">
                                    Demandé par <span
                                        class="fw-bold text-dark"><?= htmlspecialchars($current_ticket['user_nom']) ?></span>
                                    <?php if ($current_ticket['id_dem']): ?>
                                        &nbsp;|&nbsp; <a href="validate_demande.php?id=<?= $current_ticket['id_dem'] ?>"
                                            target="_blank" class="text-primary fw-bold text-decoration-none">
                                            Voir Dossier #<?= $current_ticket['id_dem'] ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <!-- FORMULAIRE RAPIDE STATUT -->
                                <form method="POST" class="d-flex gap-2">
                                    <input type="hidden" name="action" value="update_meta">
                                    <input type="hidden" name="reclamation_id" value="<?= $selected_id ?>">

                                    <?php 
                                    $priorite = $current_ticket['priorite'] ?? 'Moyenne';
                                    ?>
                                    <select name="priorite" class="form-select form-select-sm"
                                        onchange="this.form.submit()">
                                        <option value="Basse"
                                            <?= $priorite == 'Basse' ? 'selected' : '' ?>>Basse
                                        </option>
                                        <option value="Moyenne"
                                            <?= $priorite == 'Moyenne' ? 'selected' : '' ?>>Moyenne
                                        </option>
                                        <option value="Haute"
                                            <?= $priorite == 'Haute' ? 'selected' : '' ?>>Haute
                                        </option>
                                    </select>
                                    <select name="status"
                                        class="form-select form-select-sm fw-bold border-primary text-primary"
                                        onchange="this.form.submit()">
                                        <?php 
                                        $statut = $current_ticket['statut'] ?? 'Ouvert';
                                        ?>
                                        <option value="Ouvert"
                                            <?= $statut == 'Ouvert' ? 'selected' : '' ?>>Ouvert
                                        </option>
                                        <option value="En Cours"
                                            <?= $statut == 'En Cours' ? 'selected' : '' ?>>En Cours
                                        </option>
                                        <option value="Résolu"
                                            <?= $statut == 'Résolu' ? 'selected' : '' ?>>Résolu
                                        </option>
                                        <option value="Fermé" <?= $statut == 'Fermé' ? 'selected' : '' ?>>
                                            Fermé</option>
                                    </select>
                                </form>
                                <?php
                                // Lien de fermeture pour garder les filtres mais enlever l'ID
                                $closeParams = $_GET;
                                unset($closeParams['id']);
                                $closeLink = '?' . http_build_query($closeParams);
                                ?>
                                <a href="<?= htmlspecialchars($closeLink) ?>" class="btn-close"></a>
                            </div>
                        </div>
                    </div>

                    <!-- BODY MODAL (MESSAGES) -->
                    <div class="modal-body modal-chat-body" id="chatContainer">
                        <?php foreach ($messages as $msg):
                            $isAdmin = (isset($msg['role_user']) && $msg['role_user'] == 'admin') || ($msg['user_id'] == $user_id);
                            $u_avatar = !empty($msg['avatar_user']) ? '../../assets/img/' . $msg['avatar_user'] : '../../assets/img/default.png';

                            if ($msg['is_internal']) {
                                $rowClass = 'msg-internal';
                            } else {
                                $rowClass = $isAdmin ? 'msg-admin' : 'msg-user';
                            }
                        ?>
                            <div class="msg-row <?= $rowClass ?>">
                                <?php if (!$msg['is_internal']): ?>
                                    <img src="<?= htmlspecialchars($u_avatar) ?>" class="chat-avatar"
                                        title="<?= htmlspecialchars($msg['nom_user']) ?>">
                                <?php endif; ?>

                                <div class="bubble">
                                    <?php if ($msg['is_internal']): ?>
                                        <i class="bi bi-lock-fill fs-5"></i>
                                        <div class="w-100">
                                            <div class="fw-bold text-uppercase small mb-1" style="font-size: 0.7rem;">Note
                                                Interne
                                                (Privé)</div>
                                            <?= nl2br($msg['message']) ?>
                                            <div class="text-end small text-muted mt-1">
                                                <?= htmlspecialchars($msg['nom_user']) ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="fw-bold small mb-1 opacity-75"><?= htmlspecialchars($msg['nom_user']) ?>
                                        </div>
                                        <?= nl2br(htmlspecialchars($msg['message'])) ?>

                                        <?php if (!empty($msg['piece_jointe'])): ?>
                                            <div class="mt-2 pt-2 border-top border-opacity-25 border-secondary">
                                                <a href="../../uploads/reclamations/<?= htmlspecialchars($msg['piece_jointe']) ?>"
                                                    target="_blank"
                                                    class="text-reset text-decoration-none small d-inline-flex align-items-center gap-2 p-1 border rounded bg-white bg-opacity-25">
                                                    <i class="bi bi-paperclip"></i> Voir la pièce jointe
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                        <div class="msg-meta"><?= date('d/m H:i', strtotime($msg['created_at'])) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- FOOTER MODAL (REPONSE) -->
                    <div class="modal-footer bg-white border-top">
                        <?php 
                        $statut = $current_ticket['statut'] ?? $current_ticket['status'] ?? 'Ouvert';
                        if ($statut === 'Ferme'): ?>
                            <div class="w-100 alert alert-secondary m-0 text-center py-2 small">
                                <i class="bi bi-lock-fill"></i> Ce ticket est fermé. Rouvrez-le pour répondre.
                            </div>
                        <?php else: ?>
                            <form method="POST" enctype="multipart/form-data" class="w-100">
                                <input type="hidden" name="action" value="reply">
                                <input type="hidden" name="reclamation_id" value="<?= $selected_id ?>">

                                <div class="mb-2">
                                    <textarea name="message" class="form-control" rows="2"
                                        placeholder="Écrivez votre réponse..." required></textarea>
                                </div>

                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="d-flex gap-3 align-items-center">
                                        <label class="btn btn-sm btn-light border text-secondary" title="Joindre un fichier">
                                            <i class="bi bi-paperclip"></i> <input type="file" name="attachment" hidden>
                                        </label>
                                        <div class="form-check form-switch" title="Note visible uniquement par les admins">
                                            <input class="form-check-input" type="checkbox" name="is_internal"
                                                id="internalSwitch">
                                            <label class="form-check-label small text-muted user-select-none"
                                                for="internalSwitch">Note Interne</label>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-success px-4 fw-bold"><i
                                            class="bi bi-send-fill me-2"></i> Envoyer</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="modal-content">
                    <div class="modal-body">Chargement...</div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/admin_theme.js"></script>
    <script>
        // Ouverture automatique de la modale si un ID est présent dans l'URL
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($selected_id > 0): ?>
                var chatModal = new bootstrap.Modal(document.getElementById('modalChat'));
                chatModal.show();

                // Scroll en bas du chat
                var chatContainer = document.getElementById('chatContainer');
                if (chatContainer) {
                    setTimeout(() => {
                        chatContainer.scrollTop = chatContainer.scrollHeight;
                    }, 300);
                }

                // Gérer la fermeture pour nettoyer l'URL proprement (en gardant les filtres)
                document.getElementById('modalChat').addEventListener('hidden.bs.modal', function() {
                    <?php
                    $closeParams = $_GET;
                    unset($closeParams['id']);
                    $closeLink = '?' . http_build_query($closeParams);
                    ?>
                    window.location.href = "manage_reclamations.php<?= htmlspecialchars_decode($closeLink) ?>";
                });
            <?php endif; ?>
        });
    </script>
</body>

</html>