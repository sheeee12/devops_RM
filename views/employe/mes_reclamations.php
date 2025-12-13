<?php

// -------------------------------------------------------------------------

// MES RECLAMATIONS (VUE EMPLOYÉ - LISTE + POPUP CHAT)

// -------------------------------------------------------------------------

require_once __DIR__ . '/../../includes/session.php';

requireRole('employee');

require_once __DIR__ . '/../../config/Database.php';



$user_id = $_SESSION['user']['user_id'];

$user_name = $_SESSION['user']['nom'];

$user_role_raw = $_SESSION['user']['role'];

$role_display = ($user_role_raw === 'employee') ? 'Collaborateur' : ucfirst($user_role_raw);

$avatar_bdd = $_SESSION['user']['avatar'] ?? 'default.png';

$chemin_physique = __DIR__ . '/../../assets/img/' . $avatar_bdd;

$avatar = (file_exists($chemin_physique) && !empty($avatar_bdd)) ? '../../assets/img/' . $avatar_bdd : '../../assets/img/default.png';



$pdo = Database::getInstance()->getConnexion();

// Vérifier si la colonne id_reclamation existe dans la table reclamations
$has_id_reclamation_column = false;
    try {
    $checkCol = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                           WHERE TABLE_SCHEMA = DATABASE() 
                           AND TABLE_NAME = 'reclamations' 
                           AND COLUMN_NAME = 'id_reclamation'");
    $has_id_reclamation_column = ($checkCol->fetchColumn() > 0);
        } catch (PDOException $e) {
    $has_id_reclamation_column = false;
}

// Vérifier quelle colonne de clé primaire existe dans reclamation_messages (id_msg, id_message, ou id)
$reclamation_messages_id_column = 'id'; // Par défaut
try {
    $checkCol = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                           WHERE TABLE_SCHEMA = DATABASE() 
                           AND TABLE_NAME = 'reclamation_messages' 
                           AND COLUMN_KEY = 'PRI'
                           LIMIT 1");
    $result = $checkCol->fetch(PDO::FETCH_ASSOC);
    if ($result && isset($result['COLUMN_NAME'])) {
        $reclamation_messages_id_column = $result['COLUMN_NAME'];
        }
    } catch (PDOException $e) {
    // Par défaut, utiliser 'id'
    $reclamation_messages_id_column = 'id';
}

// --- NOTIFICATIONS ---
require_once __DIR__ . '/../../includes/employee_notifications.php';
$notifications = getEmployeeNotifications($pdo, $user_id);
$notificationCount = count($notifications);

// --- VARIABLES POUR LE POPUP ---

$view_id = isset($_GET['view_id']) ? intval($_GET['view_id']) : 0;

$current_ticket = null;

$messages = [];



// --- TRAITEMENT DES FORMULAIRES ---

if ($_SERVER['REQUEST_METHOD'] === 'POST') {



    // CRÉATION TICKET

    if (isset($_POST['action']) && $_POST['action'] === 'create_ticket') {

        $sujet = trim($_POST['sujet']);

        $type = $_POST['type_reclamation'];

        $priorite = $_POST['priorite'];

        $message = trim($_POST['message']);

        $demande_id = !empty($_POST['demande_id']) ? $_POST['demande_id'] : NULL;



        $file_name = NULL;

        if (isset($_FILES['piece_jointe']) && $_FILES['piece_jointe']['error'] === 0) {

            $uploadDir = __DIR__ . '/../../uploads/reclamations/';

            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $ext = pathinfo($_FILES['piece_jointe']['name'], PATHINFO_EXTENSION);

            $file_name = uniqid('rec_', true) . '.' . $ext;

            move_uploaded_file($_FILES['piece_jointe']['tmp_name'], $uploadDir . $file_name);

        }



        if (!empty($sujet) && !empty($message)) {
            // Construire l'INSERT selon les colonnes disponibles
            // Toujours inclure id_dem (même si NULL) car la colonne est requise
            try {
                // Vérifier quelles colonnes existent
                $has_priorite = false;
                $has_sujet = false;
                $has_type = false;
                $has_piece_jointe = false;
                
                try {
                    $check = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reclamations' AND COLUMN_NAME = 'priorite'");
                    $has_priorite = ($check->fetchColumn() > 0);
                } catch (PDOException $e) {}
                
                try {
                    $check = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reclamations' AND COLUMN_NAME = 'sujet'");
                    $has_sujet = ($check->fetchColumn() > 0);
                } catch (PDOException $e) {}
                
                try {
                    $check = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reclamations' AND COLUMN_NAME = 'type_reclamation'");
                    $has_type = ($check->fetchColumn() > 0);
                } catch (PDOException $e) {}
                
                try {
                    $check = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reclamations' AND COLUMN_NAME = 'piece_jointe'");
                    $has_piece_jointe = ($check->fetchColumn() > 0);
                } catch (PDOException $e) {}
                
                // Vérifier si la colonne s'appelle 'statut' ou 'status'
                $has_statut = false;
                $has_status = false;
                try {
                    $check = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reclamations' AND COLUMN_NAME = 'statut'");
                    $has_statut = ($check->fetchColumn() > 0);
                } catch (PDOException $e) {}
                
                try {
                    $check = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reclamations' AND COLUMN_NAME = 'status'");
                    $has_status = ($check->fetchColumn() > 0);
                } catch (PDOException $e) {}
                
                // Construire la requête INSERT dynamiquement
                $columns = ['id_dem', 'message'];
                $values = [$demande_id, $message];
                
                // Ajouter statut ou status selon ce qui existe
                if ($has_statut) {
                    $columns[] = 'statut';
                    $values[] = 'Ouvert';
                } elseif ($has_status) {
                    $columns[] = 'status';
                    $values[] = 'Ouvert';
            }
            
            if ($has_priorite) {
                    $columns[] = 'priorite';
                    $values[] = $priorite ?? 'Moyenne';
            }
            if ($has_sujet) {
                    $columns[] = 'sujet';
                    $values[] = $sujet;
            }
                if ($has_type) {
                    $columns[] = 'type_reclamation';
                    $values[] = $type ?? 'Autre';
                }
                if ($has_piece_jointe && $file_name) {
                    $columns[] = 'piece_jointe';
                    $values[] = $file_name;
            }
            
                $placeholders = str_repeat('?,', count($columns) - 1) . '?';
                $sql = "INSERT INTO reclamations (" . implode(', ', $columns) . ") VALUES ($placeholders)";
            $stmt = $pdo->prepare($sql);
                $stmt->execute($values);
                
            } catch (PDOException $e) {
                // En cas d'erreur, essayer avec seulement les colonnes essentielles
                error_log("Erreur création réclamation: " . $e->getMessage());
                // Vérifier quelle colonne de statut existe
                try {
                    $check = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reclamations' AND COLUMN_NAME = 'statut'");
                    if ($check->fetchColumn() > 0) {
                        $stmt = $pdo->prepare("INSERT INTO reclamations (id_dem, message, statut) VALUES (?, ?, 'Ouvert')");
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO reclamations (id_dem, message) VALUES (?, ?)");
                    }
                    $stmt->execute([$demande_id, $message]);
                } catch (PDOException $e2) {
                    // Dernier recours : seulement id_dem et message (sans statut)
                    $stmt = $pdo->prepare("INSERT INTO reclamations (id_dem, message) VALUES (?, ?)");
                    $stmt->execute([$demande_id, $message]);
                }
            }
            
            header("Location: mes_reclamations.php?msg=created");

            exit();

        }

    }



    // RÉPONSE DANS LE POPUP

    if (isset($_POST['action']) && $_POST['action'] === 'reply') {

        $rec_id = intval($_POST['reclamation_id']);

        $msg = trim($_POST['message']);



        // Vérif sécurité (le ticket appartient bien à l'user via la demande)
        if ($has_id_reclamation_column) {
            $check = $pdo->prepare("SELECT r.id_reclamation as reclamation_id_for_messages FROM reclamations r 
                                    JOIN demande d ON r.id_dem = d.id_dem 
                                    WHERE r.id_reclamation = ? AND d.user_id = ?");
        $check->execute([$rec_id, $user_id]);
        } else {
            $check = $pdo->prepare("SELECT r.id_reclam as reclamation_id_for_messages FROM reclamations r 
                                    JOIN demande d ON r.id_dem = d.id_dem 
                                    WHERE r.id_reclam = ? AND d.user_id = ?");
            $check->execute([$rec_id, $user_id]);
        }
        $recData = $check->fetch(PDO::FETCH_ASSOC);

        if ($recData && (!empty($msg) || !empty($_FILES['attachment']['name']))) {
            $reclamation_id_for_messages = $recData['reclamation_id_for_messages'] ?? $rec_id;

            $pj = null;

            if (!empty($_FILES['attachment']['name'])) {

                $uploadDir = __DIR__ . '/../../uploads/reclamations/';

                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

                $ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);

                $pj = uniqid('reply_', true) . '.' . $ext;

                move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir . $pj);

            }



            // Insérer message (is_internal = 0)

            $stmt = $pdo->prepare("INSERT INTO reclamation_messages (reclamation_id, user_id, message, is_internal, piece_jointe) VALUES (?, ?, ?, 0, ?)");

            $stmt->execute([$reclamation_id_for_messages, $user_id, $msg, $pj]);



            // Réouvrir le ticket si fermé - Adapter selon la structure
            try {
                $pdo->prepare("UPDATE reclamations SET statut = 'En_Cours' WHERE id_reclam = ? AND statut IN ('Resolu', 'Ferme')")->execute([$rec_id]);
            } catch (PDOException $e) {
                // Si statut n'existe pas, essayer avec status
                $pdo->prepare("UPDATE reclamations SET status = 'En_Cours' WHERE id_reclam = ? AND status IN ('Resolu', 'Ferme')")->execute([$rec_id]);
            }

        }

        // Rediriger vers la vue du ticket pour rouvrir le popup

        header("Location: mes_reclamations.php?view_id=$rec_id");

        exit();

    }

}



// --- RÉCUPÉRATION LISTE ---

$filter = $_GET['filter'] ?? 'all';

$search = $_GET['search'] ?? '';

$sort   = $_GET['sort'] ?? 'created_at';

$dir    = $_GET['dir'] ?? 'desc';



// Adapter les noms de colonnes selon la structure existante
$allowed_sorts = ['id_reclam' => 'id_reclam', 'sujet' => 'sujet', 'priorite' => 'priorite', 'statut' => 'statut', 'created_at' => 'created_at'];

if (!array_key_exists($sort, $allowed_sorts)) $sort = 'created_at';

// Mapper les noms de colonnes pour la requête SQL
$sort_col = $sort;
if ($sort === 'id') $sort_col = 'id_reclam';
if ($sort === 'status') $sort_col = 'statut';

$dir_sql = (strtolower($dir) === 'asc') ? 'ASC' : 'DESC';



// Adapter selon la structure (utiliser id_dem uniquement)
$sql = "SELECT r.*, d.titre_dem, d.id_dem as ref_demande 

        FROM reclamations r

        LEFT JOIN demande d ON r.id_dem = d.id_dem

        WHERE d.user_id = ?";

$params = [$user_id];



if ($filter === 'open') {
    $sql .= " AND r.statut IN ('Ouvert', 'En_Cours')";
}

if ($filter === 'closed') {
    $sql .= " AND r.statut IN ('Resolu', 'Ferme')";
}

if (!empty($search)) {

    $sql .= " AND (r.sujet LIKE ? OR r.message LIKE ? OR d.titre_dem LIKE ?)";

    $params[] = "%$search%";

    $params[] = "%$search%";

    $params[] = "%$search%";

}

$sql .= " ORDER BY $sort_col $dir_sql";

$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);



$stmtDos = $pdo->prepare("SELECT id_dem, titre_dem FROM demande WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");

$stmtDos->execute([$user_id]);

$mes_dossiers = $stmtDos->fetchAll(PDO::FETCH_ASSOC);



// --- CHARGEMENT DU TICKET POUR LE POPUP ---

if ($view_id > 0) {

    // Infos principales - Adapter selon la structure (utiliser id_dem uniquement)
    $stmt = $pdo->prepare("SELECT r.*, d.titre_dem, d.id_dem FROM reclamations r 
                          LEFT JOIN demande d ON r.id_dem = d.id_dem 
                          WHERE r.id_reclam = ? AND d.user_id = ?");
    $stmt->execute([$view_id, $user_id]);

    $current_ticket = $stmt->fetch(PDO::FETCH_ASSOC);



    if ($current_ticket) {

        // Message initial (transformé en objet message)

        $initial_msg = [
            'id_msg' => 0, // Clé fixe pour compatibilité, la vraie colonne peut être id_msg, id_message, ou id
            'user_id' => $user_id,
            'nom_user' => 'Moi',
            'avatar_user' => $avatar_bdd,
            'message' => $current_ticket['message'],
            'piece_jointe' => $current_ticket['piece_jointe'] ?? null,
            'created_at' => $current_ticket['created_at'],
            'is_internal' => 0
        ];



        // Récupérer l'ID de réclamation correct (id_reclamation si elle existe, sinon id_reclam)
        if ($has_id_reclamation_column) {
            $stmtCheckId = $pdo->prepare("SELECT id_reclamation as reclamation_id_for_messages FROM reclamations WHERE id_reclamation = ?");
            $stmtCheckId->execute([$view_id]);
        } else {
            $stmtCheckId = $pdo->prepare("SELECT id_reclam as reclamation_id_for_messages FROM reclamations WHERE id_reclam = ?");
            $stmtCheckId->execute([$view_id]);
        }
        $recIdData = $stmtCheckId->fetch(PDO::FETCH_ASSOC);
        $reclamation_id_for_messages = $recIdData['reclamation_id_for_messages'] ?? $view_id;
        
        // Réponses - Adapter selon la structure
        // La table reclamation_messages utilise reclamation_id qui référence id_reclamation
        $stmtMsg = $pdo->prepare("
            SELECT m.{$reclamation_messages_id_column} as id_msg, m.reclamation_id, m.user_id, m.message, m.piece_jointe, m.is_internal, m.created_at,
                   u.nom as nom_user, u.avatar as avatar_user 
            FROM reclamation_messages m 
            JOIN users u ON m.user_id = u.user_id 
            WHERE m.reclamation_id = ? AND (m.is_internal = 0 OR m.is_internal IS NULL)
            ORDER BY m.created_at ASC
        ");
        $stmtMsg->execute([$reclamation_id_for_messages]);

        $replies = $stmtMsg->fetchAll(PDO::FETCH_ASSOC);



        $messages = array_merge([$initial_msg], $replies);

    }

}



// --- HELPERS VISUELS ---

function getPriorityBadge($prio)

{

    $c = match ($prio) {

        'Haute' => ['bg' => '#fee2e2', 'txt' => '#b91c1c', 'icon' => 'bi-fire'],

        'Moyenne' => ['bg' => '#fff7ed', 'txt' => '#c2410c', 'icon' => 'bi-exclamation'],

        default => ['bg' => '#f1f5f9', 'txt' => '#475569', 'icon' => 'bi-info-circle'],

    };

    return sprintf('<span class="badge-prio" style="background:%s; color:%s"><i class="bi %s me-1"></i>%s</span>', $c['bg'], $c['txt'], $c['icon'], $prio);

}

function getStatusBadge($status)

{

    // Normaliser le statut (peut être 'statut' ou 'status')
    $status_normalized = $status;
    if ($status === 'resolu') $status_normalized = 'Resolu';
    if ($status === 'en_attente') $status_normalized = 'Ouvert';
    
    $styles = [

        'Ouvert' => ['bg' => '#e0f2fe', 'text' => '#0369a1', 'label' => 'Ouvert'],

        'En_Cours' => ['bg' => '#fef3c7', 'text' => '#b45309', 'label' => 'En cours'],

        'Resolu' => ['bg' => '#dcfce7', 'text' => '#15803d', 'label' => 'Résolu'],

        'Ferme' => ['bg' => '#f1f5f9', 'text' => '#64748b', 'label' => 'Clos']

    ];

    $s = $styles[$status_normalized] ?? $styles['Ferme'];

    return sprintf('<span class="status-pill" style="background:%s; color:%s">%s</span>', $s['bg'], $s['text'], $s['label']);

}

function sortLink($col, $label, $currSort, $currDir)

{

    global $filter, $search;

    // Mapper les noms de colonnes pour l'affichage
    $sort_key = $col;
    if ($col === 'id_reclamation' || $col === 'id_reclam') $sort_key = 'id_reclam';
    if ($col === 'status') $sort_key = 'statut';
    
    $nextDir = ($currSort === $sort_key && $currDir === 'desc') ? 'asc' : 'desc';

    $icon = ($currSort === $sort_key) ? ($currDir === 'asc' ? '<i class="bi bi-caret-up-fill text-primary"></i>' : '<i class="bi bi-caret-down-fill text-primary"></i>') : '<i class="bi bi-arrow-down-up text-muted opacity-25"></i>';

    return '<a href="?sort=' . $sort_key . '&dir=' . $nextDir . '&filter=' . $filter . '&search=' . urlencode($search) . '" class="sort-link">' . $label . ' ' . $icon . '</a>';

}

?>



<!DOCTYPE html>

<html lang="fr">



<head>

    <meta charset="UTF-8">

    <title>Centre de Support | Rembourse Maroc</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">



    <style>

        /* STYLE EXISTANT CONSERVÉ */

        :root {

            --app-bg: #f8fafc;

            --header-bg: #ffffff;

            --header-border: #e2e8f0;

            --primary: #059669;

            --text-main: #1e293b;

            --text-light: #64748b;

            --card-border: #e2e8f0;

            --radius: 8px;

        }



        body {

            font-family: 'Inter', sans-serif;

            background-color: var(--app-bg);

            color: var(--text-main);

            font-size: 0.875rem;

            padding-top: 60px;

        }



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

        .app-header .d-flex span {
            font-weight: 700 !important;
        }
        
        .brand-logo:hover {
            animation: logo-bounce 1s infinite;
            background-color: #047857;
        }

        .notification-bell {
            position: relative;
            color: var(--text-light);
            font-size: 1.25rem;
            text-decoration: none;
            transition: color 0.2s;
        }

        .notification-bell:hover {
            color: var(--primary);
        }
        


        .app-nav {

            display: flex;

            gap: 4px;

            height: 100%;

        }



        .nav-item-link {

            color: var(--text-light);

            text-decoration: none;

            padding: 0 16px;

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

            background-color: rgba(5, 150, 105, 0.04);

        }



        .avatar-circle {

            width: 32px;

            height: 32px;

            border-radius: 50%;

            object-fit: cover;

            border: 1px solid #cbd5e1;

        }



        .main-container {

            max-width: 1440px;

            margin: 0 auto;

            padding: 24px;

        }



        .page-header {

            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 24px;

        }



        .btn-action {

            background-color: var(--primary);

            color: white;

            padding: 8px 16px;

            border-radius: 6px;

            font-weight: 500;

            text-decoration: none;

            display: inline-flex;

            align-items: center;

            gap: 8px;

            border: none;

            font-size: 0.85rem;

        }



        .btn-action:hover {

            background-color: #047857;

            color: white;

        }



        .toolbar {

            display: flex;

            justify-content: space-between;

            align-items: center;

            background: white;

            border: 1px solid var(--card-border);

            border-radius: var(--radius);

            padding: 8px;

            margin-bottom: 16px;

        }



        .filter-group {

            display: flex;

            gap: 4px;

        }



        .filter-btn {

            padding: 6px 12px;

            border-radius: 6px;

            font-size: 0.85rem;

            font-weight: 500;

            color: var(--text-light);

            text-decoration: none;

            transition: 0.2s;

        }



        .filter-btn:hover {

            background: #f1f5f9;

            color: var(--text-main);

        }



        .filter-btn.active {

            background: #ecfdf5;

            color: var(--primary);

            font-weight: 600;

        }



        .search-box {

            position: relative;

            width: 250px;

        }



        .search-box input {

            width: 100%;

            padding: 6px 10px 6px 32px;

            border-radius: 6px;

            border: 1px solid var(--card-border);

            font-size: 0.85rem;

            outline: none;

        }



        .search-box i {

            position: absolute;

            left: 10px;

            top: 50%;

            transform: translateY(-50%);

            color: var(--text-light);

        }



        .table-container {

            background: white;

            border: 1px solid var(--card-border);

            border-radius: var(--radius);

            overflow: hidden;

        }



        .table-app {

            width: 100%;

            border-collapse: separate;

            border-spacing: 0;

        }



        .table-app th {

            background: #f8fafc;

            color: var(--text-light);

            font-weight: 600;

            font-size: 0.75rem;

            text-transform: uppercase;

            padding: 12px 16px;

            border-bottom: 1px solid var(--card-border);

            text-align: left;

        }



        .table-app td {

            padding: 12px 16px;

            border-bottom: 1px solid var(--card-border);

            vertical-align: middle;

            color: var(--text-main);

        }



        .table-app tr:last-child td {

            border-bottom: none;

        }



        .table-app tr:hover td {

            background-color: #f8fafc;

            cursor: pointer;

        }



        /* Added cursor pointer */

        .sort-link {

            text-decoration: none;

            color: inherit;

            display: flex;

            align-items: center;

            gap: 5px;

        }



        .status-pill {

            padding: 4px 10px;

            border-radius: 12px;

            font-size: 0.75rem;

            font-weight: 600;

        }



        .badge-prio {

            padding: 4px 8px;

            border-radius: 6px;

            font-size: 0.75rem;

            font-weight: 500;

            display: inline-flex;

            align-items: center;

        }



        .widget-box {

            background: white;

            border: 1px solid var(--card-border);

            border-radius: var(--radius);

            padding: 20px;

            margin-bottom: 20px;

        }



        .widget-title {

            font-weight: 600;

            font-size: 0.95rem;

            margin-bottom: 16px;

            color: var(--text-main);

        }



        .faq-item {

            border: 1px solid #f1f5f9;

            border-radius: 6px;

            margin-bottom: 8px;

            overflow: hidden;

        }



        .faq-btn {

            width: 100%;

            text-align: left;

            background: #f8fafc;

            border: none;

            padding: 10px 12px;

            font-size: 0.85rem;

            font-weight: 500;

            color: var(--text-main);

            display: flex;

            justify-content: space-between;

            align-items: center;

        }



        .faq-body {

            padding: 10px 12px;

            font-size: 0.8rem;

            color: var(--text-light);

            line-height: 1.5;

            background: white;

            border-top: 1px solid #f1f5f9;

        }



        .contact-row {

            display: flex;

            align-items: center;

            gap: 12px;

            margin-bottom: 12px;

        }



        .contact-icon {

            width: 36px;

            height: 36px;

            background: #f0fdf4;

            color: var(--primary);

            border-radius: 8px;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 1.1rem;

        }



        /* NOUVEAU STYLE POUR LE CHAT (POPUP) */

        .chat-body {

            background: #f1f5f9;

            padding: 20px;

            height: 400px;

            overflow-y: auto;

            display: flex;

            flex-direction: column;

            gap: 15px;

        }



        .msg-row {

            display: flex;

            gap: 15px;

            max-width: 85%;

        }



        .msg-me {

            align-self: flex-end;

            flex-direction: row-reverse;

        }



        .msg-other {

            align-self: flex-start;

        }



        .bubble {

            padding: 12px 16px;

            border-radius: 12px;

            font-size: 0.9rem;

            line-height: 1.5;

            position: relative;

            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);

        }



        .msg-me .bubble {

            background: var(--primary);

            color: white;

            border-top-right-radius: 0;

        }



        .msg-other .bubble {

            background: white;

            border: 1px solid #e2e8f0;

            border-top-left-radius: 0;

            color: #1e293b;

        }



        .chat-avatar {

            width: 32px;

            height: 32px;

            border-radius: 50%;

            object-fit: cover;

        }



        .msg-meta {

            font-size: 0.7rem;

            margin-top: 4px;

            opacity: 0.7;

        }



        .msg-me .msg-meta {

            text-align: right;

            color: rgba(255, 255, 255, 0.9);

        }

    </style>

</head>



<body>



    <!-- TOP NAV -->

    <header class="app-header">

        <div class="d-flex align-items-center gap-2">

            <div class="brand-logo">RM</div> <span class="fw-bold text-dark">RembourseMaroc</span>

            </div>

        <nav class="app-nav">

            <a href="dashboard.php" class="nav-item-link"><i class="bi bi-grid-fill"></i> Tableau de bord</a>

            <a href="nouvelle_demande.php" class="nav-item-link"><i class="bi bi-plus-circle"></i> Nouvelle demande</a>

            <a href="mes_frais.php" class="nav-item-link"><i class="bi bi-receipt"></i> Mes frais</a>

            <a href="mes_brouillons.php" class="nav-item-link"><i class="bi bi-file-earmark"></i> Brouillons</a>

            <a href="mes_reclamations.php" class="nav-item-link active"><i class="bi bi-life-preserver"></i> Support</a>

            <a href="mes_avances.php" class="nav-item-link"><i class="bi bi-cash-stack"></i> Avances</a>

            <a href="guide_politique.php" class="nav-item-link"><i class="bi bi-journal-text fw-bold"></i> Guide</a>

        </nav>

        <div class="d-flex align-items-center gap-3">
            <?= renderNotificationBell($notifications) ?>
            
            <div class="text-end d-none d-sm-block">
                <div class="fw-bold small"><?= htmlspecialchars($user_name) ?></div>
                <div class="text-muted" style="font-size: 0.7rem;"><?= htmlspecialchars($role_display) ?></div>
            </div>
            <div class="dropdown">
                <a href="#" data-bs-toggle="dropdown"><img src="<?= htmlspecialchars($avatar) ?>"
                        class="avatar-circle"></a>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2">
                    <li><a class="dropdown-item small" href="profil.php">Mon Profil</a></li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li><a class="dropdown-item small text-danger" href="../../actions/logout.php">Déconnexion</a></li>
                </ul>
            </div>
        </div>

    </header>



    <!-- MAIN CONTENT -->

    <div class="main-container">



        <div class="page-header">

            <div>

                <h4 class="fw-bold m-0 text-dark">Centre de Support</h4>

                <div class="text-muted small">Suivez vos tickets et contactez l'équipe finance.</div>

            </div>

            <button class="btn-action" data-bs-toggle="modal" data-bs-target="#modalTicket">

                <i class="bi bi-plus-lg"></i> Créer un ticket

            </button>

        </div>



        <div class="row">

            <!-- COLONNE GAUCHE : TABLEAU -->

            <div class="col-lg-8 mb-4">



                <!-- Toolbar Filtres -->

                <div class="toolbar">

                    <div class="filter-group">

                        <a href="?filter=all&search=<?= htmlspecialchars($search) ?>"

                            class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">Tous</a>

                        <a href="?filter=open&search=<?= htmlspecialchars($search) ?>"

                            class="filter-btn <?= $filter === 'open' ? 'active' : '' ?>">En cours</a>

                        <a href="?filter=closed&search=<?= htmlspecialchars($search) ?>"

                            class="filter-btn <?= $filter === 'closed' ? 'active' : '' ?>">Clôturés</a>

                    </div>

                    <form method="GET" class="search-box">

                        <input type="hidden" name="filter" value="<?= $filter ?>">

                        <i class="bi bi-search"></i>

                        <input type="text" name="search" placeholder="Rechercher..."

                            value="<?= htmlspecialchars($search) ?>">

                    </form>

                </div>



                <!-- Liste Tickets -->

                <div class="table-container">

                    <table class="table-app">

                        <thead>

                            <tr>

                                <th style="width: 80px;"><?= sortLink('id_reclam', '#', $sort, $dir) ?></th>

                                <th><?= sortLink('sujet', 'Sujet / Type', $sort, $dir) ?></th>

                                <th style="width: 140px;"><?= sortLink('created_at', 'Date', $sort, $dir) ?></th>

                                <th style="width: 120px;"><?= sortLink('priorite', 'Priorité', $sort, $dir) ?></th>

                                <th style="width: 120px;"><?= sortLink('statut', 'Statut', $sort, $dir) ?></th>

                                <th style="width: 50px;"></th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php if (empty($tickets)): ?>

                                <tr>

                                    <td colspan="6" class="text-center py-5">

                                        <i class="bi bi-chat-square-text fs-1 text-muted opacity-25 d-block mb-2"></i>

                                        <span class="text-muted small">Aucune réclamation trouvée.</span>

                                    </td>

                                </tr>

                            <?php else: ?>

                                <?php foreach ($tickets as $t): ?>

                                    <!-- Le clic sur la ligne déclenche le chargement de la page avec view_id -->

                                    <tr

                                        onclick="window.location.href='?view_id=<?= $t['id_reclam'] ?? '' ?>&filter=<?= $filter ?>'">

                                        <td>

                                            <span

                                                class="text-secondary small font-monospace">#<?= $t['id_reclam'] ?? '' ?></span>

                                        </td>

                                        <td>

                                            <div class="fw-medium text-dark"><?= htmlspecialchars($t['sujet'] ?? substr($t['message'] ?? '', 0, 50)) ?></div>

                                            <div class="small text-muted d-flex align-items-center gap-2">

                                                <span><?= str_replace('_', ' ', $t['type_reclamation'] ?? 'Autre') ?></span>

                                                <?php if ($t['ref_demande']): ?>

                                                    <span class="badge bg-light text-secondary border px-1">Dossier

                                                        #<?= $t['ref_demande'] ?></span>

                                                <?php endif; ?>

                                            </div>

                                        </td>

                                        <td class="small text-secondary">

                                            <?= date('d/m/Y', strtotime($t['created_at'])) ?><br>

                                            <span class="text-muted"

                                                style="font-size:0.75rem"><?= date('H:i', strtotime($t['created_at'])) ?></span>

                                        </td>

                                        <td><?= getPriorityBadge($t['priorite'] ?? 'Moyenne') ?></td>

                                        <td><?= getStatusBadge($t['statut'] ?? $t['status'] ?? 'Ouvert') ?></td>

                                        <td class="text-end">

                                            <!-- Bouton spécifique -->

                                            <a href="?view_id=<?= $t['id_reclam'] ?? '' ?>&filter=<?= $filter ?>"

                                                class="btn btn-sm btn-link text-secondary p-0">

                                                <i class="bi bi-chevron-right"></i>

                                            </a>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>



            </div>



            <!-- COLONNE DROITE : AIDE (Reste inchangée) -->

            <div class="col-lg-4">

                <div class="widget-box">

                    <div class="widget-title"><i class="bi bi-headset me-2"></i>Besoin d'aide immédiate ?</div>

                    <div class="contact-row">

                        <div class="contact-icon"><i class="bi bi-envelope"></i></div>

                        <div>

                            <div class="small fw-bold text-dark">support@remboursemaroc.com</div>

                            <div class="small text-muted">Réponse sous 24h</div>

                        </div>

                    </div>

                    <div class="contact-row mb-0">

                        <div class="contact-icon" style="background:#e0f2fe; color:#0284c7"><i

                                class="bi bi-telephone"></i></div>

                        <div>

                            <div class="small fw-bold text-dark">+212 5 22 00 00 00</div>

                            <div class="small text-muted">Lundi - Vendredi, 9h-18h</div>

                        </div>

                    </div>

                </div>

                <!-- (FAQ Widgets conservés) -->

            </div>

        </div>

    </div>



    <!-- MODAL NOUVEAU TICKET (Inchangé) -->

    <div class="modal fade" id="modalTicket" tabindex="-1" aria-hidden="true">

        <div class="modal-dialog modal-dialog-centered">

            <div class="modal-content border-0 shadow-lg">

                <div class="modal-header border-bottom">

                    <h5 class="modal-title fs-6 fw-bold">Nouveau Ticket de Support</h5>

                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>

                </div>

                <form method="POST" enctype="multipart/form-data">

                    <div class="modal-body p-4 bg-light bg-opacity-10">

                        <input type="hidden" name="action" value="create_ticket">

                        <div class="row g-3 mb-3">

                            <div class="col-md-6">

                                <label class="form-label small fw-bold text-secondary">Type</label>

                                <select name="type_reclamation" class="form-select form-select-sm" required>

                                    <option value="Retard_Paiement">Retard Paiement</option>

                                    <option value="Montant_Incorrect">Erreur Montant</option>

                                    <option value="Rejet_Injustifie">Contestation</option>

                                    <option value="Probleme_Technique">Bug Technique</option>

                                    <option value="Autre">Autre</option>

                                </select>

                            </div>

                            <div class="col-md-6">

                                <label class="form-label small fw-bold text-secondary">Priorité</label>

                                <select name="priorite" class="form-select form-select-sm">

                                    <option value="Basse">Basse</option>

                                    <option value="Moyenne" selected>Moyenne</option>

                                    <option value="Haute">Haute (Urgent)</option>

                                </select>

                            </div>

                        </div>

                        <div class="mb-3">

                            <label class="form-label small fw-bold text-secondary">Dossier lié</label>

                            <select name="demande_id" class="form-select form-select-sm">

                                <option value="">-- Sélectionner un dossier --</option>

                                <?php foreach ($mes_dossiers as $dos): ?>

                                    <option value="<?= $dos['id_dem'] ?>">#<?= $dos['id_dem'] ?> -

                                        <?= htmlspecialchars(substr($dos['titre_dem'], 0, 30)) ?></option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div class="mb-3">

                            <label class="form-label small fw-bold text-secondary">Sujet <span

                                    class="text-danger">*</span></label>

                            <input type="text" name="sujet" class="form-control" required>

                        </div>

                        <div class="mb-3">

                            <label class="form-label small fw-bold text-secondary">Message <span

                                    class="text-danger">*</span></label>

                            <textarea name="message" class="form-control" rows="4" required></textarea>

                        </div>

                        <div class="mb-0">

                            <label class="form-label small fw-bold text-secondary">Pièce jointe</label>

                            <input type="file" name="piece_jointe" class="form-control form-control-sm">

                        </div>

                    </div>

                    <div class="modal-footer border-top bg-white">

                        <button type="button" class="btn btn-light btn-sm text-secondary"

                            data-bs-dismiss="modal">Annuler</button>

                        <button type="submit" class="btn btn-action py-1 px-3">Envoyer</button>

                    </div>

                </form>

            </div>

        </div>

    </div>



    <!-- NOUVEAU : POPUP DE CONVERSATION (Chat) -->

    <!-- Cette modale est toujours présente mais vide, et remplie si $current_ticket existe -->

    <div class="modal fade" id="modalChat" tabindex="-1" aria-hidden="true">

        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">

            <?php if ($current_ticket): ?>

                <div class="modal-content border-0 shadow-lg" style="height: 80vh;">

                    <div class="modal-header border-bottom py-3 bg-white">

                        <div>

                            <h5 class="modal-title fw-bold">#<?= $current_ticket['id_reclam'] ?? '' ?> -

                                <?= htmlspecialchars($current_ticket['sujet'] ?? 'Sans sujet') ?></h5>

                            <div class="small text-muted">

                                <span

                                    class="badge bg-light text-dark border me-2"><?= str_replace('_', ' ', $current_ticket['statut'] ?? $current_ticket['status'] ?? 'Ouvert') ?></span>

                                <?php if (isset($current_ticket['type_reclamation']) && $current_ticket['type_reclamation'] !== null): ?>
                                    <span class="badge bg-info text-white"><?= str_replace('_', ' ', $current_ticket['type_reclamation']) ?></span>
                                <?php endif; ?>

                            </div>

                        </div>

                        <!-- Bouton pour nettoyer l'URL à la fermeture -->

                        <button type="button" class="btn-close"

                            onclick="window.location.href='mes_reclamations.php?filter=<?= $filter ?>'"></button>

                    </div>



                    <div class="modal-body chat-body" id="chatContainer">

                        <?php foreach ($messages as $msg):

                            $isMe = ($msg['user_id'] == $user_id);

                            $rowClass = $isMe ? 'msg-me' : 'msg-other';

                            $avatarUrl = !empty($msg['avatar_user']) ? '../../assets/img/' . $msg['avatar_user'] : '../../assets/img/default.png';

                            $senderName = $isMe ? 'Moi' : 'Support';

                        ?>

                            <div class="msg-row <?= $rowClass ?>">

                                <?php if (!$isMe): ?>

                                    <img src="<?= htmlspecialchars($avatarUrl) ?>" class="chat-avatar" title="Support">

                                <?php endif; ?>



                                <div class="bubble">

                                    <div class="fw-bold small opacity-75 mb-1"><?= $senderName ?></div>

                                    <?= nl2br(htmlspecialchars($msg['message'])) ?>



                                    <?php if (!empty($msg['piece_jointe'])): ?>

                                        <div class="mt-2 pt-2 border-top border-secondary border-opacity-25">

                                            <a href="../../uploads/reclamations/<?= htmlspecialchars($msg['piece_jointe']) ?>"

                                                target="_blank"

                                                class="text-reset text-decoration-none small d-flex align-items-center gap-2">

                                                <i class="bi bi-paperclip"></i> Fichier joint

                                            </a>

                                        </div>

                                    <?php endif; ?>

                                    <div class="msg-meta"><?= date('d/m H:i', strtotime($msg['created_at'])) ?></div>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>



                    <div class="modal-footer bg-white border-top">

                        <?php if (($current_ticket['statut'] ?? $current_ticket['status'] ?? '') === 'Ferme'): ?>

                            <div class="w-100 alert alert-secondary m-0 text-center py-2 small"><i class="bi bi-lock-fill"></i>

                                Ticket fermé.</div>

                        <?php else: ?>

                            <form method="POST" enctype="multipart/form-data" class="w-100">

                                <input type="hidden" name="action" value="reply">

                                <input type="hidden" name="reclamation_id" value="<?= $view_id ?>">

                                <div class="mb-2">

                                    <textarea name="message" class="form-control" rows="2" placeholder="Répondre..."

                                        required></textarea>

                                </div>

                                <div class="d-flex justify-content-between align-items-center">

                                    <label class="btn btn-sm btn-light border text-secondary" title="Joindre un fichier">

                                        <i class="bi bi-paperclip"></i> <input type="file" name="attachment" hidden>

                                    </label>

                                    <button type="submit" class="btn btn-action py-1 px-4">Envoyer</button>

                                </div>

                            </form>

                        <?php endif; ?>

                    </div>

                </div>

            <?php endif; ?>

        </div>

    </div>



    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Script pour gérer les notifications -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.notification-item').forEach(function(item) {
                item.addEventListener('click', function() {
                    const notificationId = this.getAttribute('data-notification-id');
                    const notificationType = this.getAttribute('data-notification-type');
                    const demandId = this.getAttribute('data-demand-id');
                    const reclamationId = this.getAttribute('data-reclamation-id');
                    
                    if (notificationId) {
                        fetch('../../actions/mark_notification_read_employee.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({id: notificationId, type: notificationType})
                        }).then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                this.style.opacity = '0.5';
                                if (notificationType === 'reclamation_reply' && reclamationId) {
                                    setTimeout(function() {
                                        window.location.href = 'mes_reclamations.php?view_id=' + reclamationId;
                                    }, 300);
                                } else if (demandId) {
                                    setTimeout(function() {
                                        window.location.href = 'details_demande.php?id=' + demandId;
                                    }, 300);
                                }
                            }
                        });
                    } else if (notificationType === 'reclamation_reply' && reclamationId) {
                        window.location.href = 'mes_reclamations.php?view_id=' + reclamationId;
                    } else if (demandId) {
                        window.location.href = 'details_demande.php?id=' + demandId;
                    }
                });
            });
        });
    </script>

    <script>

        document.addEventListener('DOMContentLoaded', function() {

            // Si view_id est présent, on ouvre le modal automatiquement

            <?php if ($view_id > 0 && $current_ticket): ?>

                var chatModal = new bootstrap.Modal(document.getElementById('modalChat'));

                chatModal.show();



                // Scroll en bas du chat

                var chatContainer = document.getElementById('chatContainer');

                if (chatContainer) {

                    setTimeout(() => {

                        chatContainer.scrollTop = chatContainer.scrollHeight;

                    }, 300);

                }



                // Gérer la fermeture via echap ou clic hors zone -> rediriger pour nettoyer URL

                var modalElement = document.getElementById('modalChat');

                modalElement.addEventListener('hidden.bs.modal', function() {

                    window.location.href = 'mes_reclamations.php?filter=<?= $filter ?>';

                });

            <?php endif; ?>

        });

    </script>

</body>



</html>
