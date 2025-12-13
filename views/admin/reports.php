<?php
// -------------------------------------------------------------------------
// VUE ADMIN : EXPORT & RAPPORTS
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

// --- LOGIQUE D'EXPORTATION CSV ---
if (isset($_POST['export_csv'])) {
    // Récupération des filtres
    $date_debut = $_POST['date_debut'] ?? '2020-01-01';
    $date_fin   = $_POST['date_fin'] ?? date('Y-m-d');
    $team_id    = !empty($_POST['team_id']) ? $_POST['team_id'] : null;
    $status     = !empty($_POST['status']) ? $_POST['status'] : null;

    // Construction de la requête
    $sql = "SELECT d.id_dem, 
                   d.created_at,
                   d.date_dep,
                   d.titre_dem, d.montant_total, d.status,
                   u.nom as user_nom, u.email as user_email,
                   t.nom_team
            FROM demande d
            JOIN users u ON d.user_id = u.user_id
            LEFT JOIN teams t ON u.team_id = t.team_id
            WHERE d.created_at BETWEEN ? AND ? + INTERVAL 1 DAY";

    $params = [$date_debut, $date_fin];

    if ($team_id) {
        $sql .= " AND u.team_id = ?";
        $params[] = $team_id;
    }
    if ($status) {
        $sql .= " AND d.status = ?";
        $params[] = $status;
    }

    $sql .= " ORDER BY d.created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Génération du fichier CSV
    $filename = "export_frais_" . date('Y-m-d_H-i') . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    $output = fopen('php://output', 'w');

    // BOM pour forcer Excel à lire l'UTF-8 (Accents)
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Entêtes du CSV
    fputcsv($output, ['ID', 'Date Creation', 'Date Mission', 'Employe', 'Email', 'Equipe', 'Titre Mission', 'Statut', 'Montant (DH)'], ';');

    // Données
    foreach ($results as $row) {
        // Formater la date de création
        $dateCreation = '';
        if (isset($row['created_at']) && $row['created_at'] !== null && $row['created_at'] !== '' && $row['created_at'] !== '0000-00-00 00:00:00') {
            // Nettoyer la valeur
            $rawDate = trim($row['created_at']);
            // Essayer de convertir en timestamp
            $timestamp = strtotime($rawDate);
            if ($timestamp !== false && $timestamp > 0) {
                // Format ISO pour Excel : YYYY-MM-DD HH:MM:SS
                $dateCreation = date('Y-m-d H:i:s', $timestamp);
            } else {
                // Si la conversion échoue, utiliser la valeur brute si elle ressemble à une date
                if (preg_match('/\d{4}-\d{2}-\d{2}/', $rawDate)) {
                    $dateCreation = $rawDate;
                }
            }
        }
        
        // Formater la date de mission
        $dateMission = '';
        if (isset($row['date_dep']) && $row['date_dep'] !== null && $row['date_dep'] !== '' && $row['date_dep'] !== '0000-00-00') {
            // Nettoyer la valeur
            $rawDate = trim($row['date_dep']);
            // Essayer de convertir en timestamp
            $timestamp = strtotime($rawDate);
            if ($timestamp !== false && $timestamp > 0) {
                // Format ISO pour Excel : YYYY-MM-DD
                $dateMission = date('Y-m-d', $timestamp);
            } else {
                // Si la conversion échoue, utiliser la valeur brute si elle ressemble à une date
                if (preg_match('/\d{4}-\d{2}-\d{2}/', $rawDate)) {
                    $dateMission = $rawDate;
                }
            }
        }
        
        fputcsv($output, [
            $row['id_dem'] ?? '',
            $dateCreation ?: '',
            $dateMission ?: '',
            $row['user_nom'] ?? '',
            $row['user_email'] ?? '',
            $row['nom_team'] ?? 'N/A',
            $row['titre_dem'] ?? '',
            $row['status'] ?? '',
            number_format($row['montant_total'] ?? 0, 2, '.', '')
        ], ';');
    }
    fclose($output);
    exit(); // Arrêter le script ici pour ne pas afficher le HTML
}

// --- CHARGEMENT DES LISTES POUR LE FORMULAIRE ---
$teams = $db->query("SELECT * FROM teams ORDER BY nom_team")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Rapports & Exports | Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <style>
        /* STYLE DASHBOARD UNIFIÉ */
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
            --radius: 16px;
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
        [data-theme="dark"] .table-custom td {
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
        [data-theme="dark"] .badge {
            background: #1e293b !important;
            background-color: #1e293b !important;
            color: var(--text-main) !important;
            border-color: var(--card-border) !important;
        }

        [data-theme="dark"] .input-group-text {
            border-color: var(--card-border) !important;
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

        /* WIDGETS */
        .card-widget {
            background: white;
            border-radius: var(--radius);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(145, 190, 150, 0.2);
            padding: 30px;
        }

        .form-label {
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control,
        .form-select {
            padding: 12px 15px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            background-color: #f8fafc;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
            background-color: white;
        }

        .btn-export {
            background: var(--primary);
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            width: 100%;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-export:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.2);
        }
    </style>
</head>

<body>

    <header class="app-header">
        <div class="d-flex align-items-center gap-2">
            <div class="brand-logo">RM</div> <span class="fw-bold text-dark">RembourseMaroc</span>
        </div>
        <!-- NAVBAR ADMIN UNIFIÉE -->
        <nav class="app-nav d-none d-md-flex">

            <!-- Lien 1: Dashboard -->
            <a href="dashboard.php"
                class="nav-item-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="bi bi-grid-fill me-2"></i>Dashboard
            </a>

            <!-- Lien 2: Pilotage Paiements -->
            <a href="manage_pending.php"
                class="nav-item-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_pending.php' ? 'active' : ''; ?>">
                <i class="bi bi-layers-fill me-2"></i>Paiements
            </a>

            <!-- Lien 3: Utilisateurs (Onglet par défaut) -->
            <!-- Active si la page est manage_data.php ET (tab est 'users' OU tab est vide) -->
            <a href="manage_data.php?tab=users"
                class="nav-item-link <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_data.php' && (!isset($_GET['tab']) || $_GET['tab'] == 'users')) ? 'active' : ''; ?>">
                <i class="bi bi-people me-2"></i>Utilisateurs
            </a>

            <!-- Lien 4: Équipes (Onglet spécifique) -->
            <!-- Active si la page est manage_data.php ET tab est 'teams' -->
            <a href="manage_data.php?tab=teams"
                class="nav-item-link <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_data.php' && (isset($_GET['tab']) && $_GET['tab'] == 'teams')) ? 'active' : ''; ?>">
                <i class="bi bi-diagram-3 me-2"></i>Équipes
            </a>

            <!-- Lien 5: Catégories -->
            <a href="manage_categories.php"
                class="nav-item-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_categories.php' ? 'active' : ''; ?>">
                <i class="bi bi-tags me-2"></i>Catégories
            </a>

            <a href="manage_reclamations.php" class="nav-item-link"><i
                    class="bi bi-life-preserver me-2"></i>Réclamations</a>
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


    <div class="container px-4" style="max-width: 1000px; margin-top: 40px;">

        <div class="row mb-4 align-items-center">
            <div class="col-md-8">
                <h3 class="fw-bolder m-0" style="color: var(--primary-dark);">Exports & Rapports</h3>
                <div class="text-muted">Générez des fichiers CSV pour la comptabilité ou l'archivage.</div>
            </div>
        </div>

        <div class="card-widget">
            <form method="POST">
                <div class="row g-4">
                    <div class="col-12">
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <div class="bg-success bg-opacity-10 text-success p-2 rounded"><i
                                    class="bi bi-calendar-range"></i></div>
                            <h6 class="fw-bold m-0 text-dark">Période concernée</h6>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Date de début</label>
                                <input type="date" name="date_debut" class="form-control" value="<?= date('Y-m-01') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Date de fin</label>
                                <input type="date" name="date_fin" class="form-control" value="<?= date('Y-m-d') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <hr class="opacity-10">
                    </div>

                    <div class="col-12">
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <div class="bg-primary bg-opacity-10 text-primary p-2 rounded"><i class="bi bi-funnel"></i>
                            </div>
                            <h6 class="fw-bold m-0 text-dark">Filtres avancés</h6>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Filtrer par Équipe</label>
                                <select name="team_id" class="form-select">
                                    <option value="">-- Toutes les équipes --</option>
                                    <?php foreach ($teams as $t): ?>
                                        <option value="<?= $t['team_id'] ?>"><?= htmlspecialchars($t['nom_team']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Statut du dossier</label>
                                <select name="status" class="form-select">
                                    <option value="">-- Tous les statuts --</option>
                                    <option value="Attente_Manager">Attente Manager</option>
                                    <option value="Attente_Admin">Attente Validation Finance</option>
                                    <option value="Paye">Payé</option>
                                    <option value="Rejete">Rejeté</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 mt-4">
                        <button type="submit" name="export_csv" class="btn-export">
                            <i class="bi bi-file-earmark-arrow-down-fill"></i> Télécharger le rapport CSV
                        </button>
                        <div class="text-center mt-3 text-muted small">
                            <i class="bi bi-info-circle me-1"></i> Le fichier généré est compatible avec Excel,
                            Google
                            Sheets et les logiciels comptables.
                        </div>
                    </div>
                </div>
            </form>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/admin_theme.js"></script>
</body>

</html>