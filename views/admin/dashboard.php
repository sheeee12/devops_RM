<?php
// -------------------------------------------------------------------------
// DASHBOARD ADMIN - VUE SUPERVISEUR
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

// --- KPI DATA (Identique) ---
$stmt = $db->query("SELECT SUM(budget_annuel) as total, SUM(budget_consomme) as consumed FROM teams");
$budgetData = $stmt->fetch(PDO::FETCH_ASSOC);
$budget_total = $budgetData['total'] ?? 0;
$budget_consumed = $budgetData['consumed'] ?? 0;
$percent_used = ($budget_total > 0) ? round(($budget_consumed / $budget_total) * 100, 1) : 0;

$stmt = $db->query("SELECT COUNT(*) FROM demande WHERE status = 'Attente_Admin'");
$pending_finance = $stmt->fetchColumn();

$stmt = $db->query("SELECT COALESCE(SUM(montant_total), 0) FROM demande WHERE status = 'Paye' AND YEAR(date_dep) = YEAR(CURRENT_DATE())");
$paid_year = $stmt->fetchColumn();

// Vérifier si la colonne est statut ou status
try {
    $stmt = $db->query("SELECT COUNT(*) FROM reclamations WHERE statut = 'Ouvert'");
    $open_tickets = $stmt->fetchColumn();
} catch (PDOException $e) {
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM reclamations WHERE status = 'Ouvert'");
        $open_tickets = $stmt->fetchColumn();
    } catch (PDOException $e2) {
        $open_tickets = 0;
    }
}

// --- DATA LISTES ---
// On affiche toujours les 5 plus urgentes ici
$sqlTasks = "SELECT d.*, u.nom as user_name, u.avatar as user_avatar, t.nom_team FROM demande d JOIN users u ON d.user_id = u.user_id LEFT JOIN teams t ON u.team_id = t.team_id WHERE d.status = 'Attente_Admin' ORDER BY d.created_at ASC LIMIT 5";
$tasks = $db->query($sqlTasks)->fetchAll(PDO::FETCH_ASSOC);

// Budgets des équipes (si la table teams existe)
try {
    $sqlBudgets = "SELECT nom_team, budget_annuel, budget_consomme FROM teams ORDER BY (budget_consomme / NULLIF(budget_annuel, 0)) DESC LIMIT 4";
    $teamBudgets = $db->query($sqlBudgets)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $teamBudgets = [];
}

// Logs d'audit (si la table audit_logs existe)
try {
    $sqlLogs = "SELECT l.*, u.nom FROM audit_logs l LEFT JOIN users u ON l.user_id = u.user_id ORDER BY l.created_at DESC LIMIT 6";
    $logs = $db->query($sqlLogs)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $logs = [];
}

// --- CHARTS DATA ---
$sqlTrend = "SELECT DATE_FORMAT(date_dep, '%Y-%m') as mois, SUM(montant_total) as total FROM demande WHERE status = 'Paye' GROUP BY mois ORDER BY mois ASC LIMIT 6";
$trendData = $db->query($sqlTrend)->fetchAll(PDO::FETCH_ASSOC);
$trendLabels = json_encode(array_map(function ($m) {
    return date('M', strtotime($m . '-01'));
}, array_column($trendData, 'mois')));
$trendValues = json_encode(array_column($trendData, 'total'));
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Administration | Rembourse Maroc</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <!-- Style CSS conservé -->
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

        [data-theme="dark"] .btn-quick {
            background: #1e293b !important;
            border-color: var(--card-border) !important;
            color: var(--text-main) !important;
        }

        [data-theme="dark"] .btn-quick:hover {
            background: var(--primary) !important;
            color: white !important;
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

        [data-theme="dark"] .alert {
            background: #1e293b !important;
            border-color: var(--card-border) !important;
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
        [data-theme="dark"] .nav-pills,
        [data-theme="dark"] .list-group-item,
        [data-theme="dark"] .badge,
        [data-theme="dark"] .progress-slim {
            background: #1e293b !important;
            background-color: #1e293b !important;
            color: var(--text-main) !important;
            border-color: var(--card-border) !important;
        }

        [data-theme="dark"] .input-group-text {
            border-color: var(--card-border) !important;
        }

        [data-theme="dark"] .nav-pills .nav-link {
            color: var(--text-main) !important;
        }

        [data-theme="dark"] .nav-pills .nav-link.active {
            background-color: var(--primary) !important;
            color: white !important;
        }

        [data-theme="dark"] .activity-item {
            border-color: var(--card-border) !important;
        }

        [data-theme="dark"] .progress-slim {
            background-color: #334155 !important;
        }

        /* Éléments spécifiques avec background white */
        [data-theme="dark"] .kpi-icon-wrapper,
        [data-theme="dark"] .bg-gradient-green,
        [data-theme="dark"] .bg-gradient-blue,
        [data-theme="dark"] .bg-gradient-orange,
        [data-theme="dark"] .bg-gradient-purple {
            background: #334155 !important;
            opacity: 0.8;
        }

        [data-theme="dark"] .kpi-icon-wrapper {
            background: rgba(5, 150, 105, 0.2) !important;
        }

        [data-theme="dark"] .bg-gradient-green {
            background: rgba(5, 150, 105, 0.2) !important;
            color: #10b981 !important;
        }

        [data-theme="dark"] .bg-gradient-blue {
            background: rgba(37, 99, 235, 0.2) !important;
            color: #60a5fa !important;
        }

        [data-theme="dark"] .bg-gradient-orange {
            background: rgba(234, 88, 12, 0.2) !important;
            color: #fb923c !important;
        }

        [data-theme="dark"] .bg-gradient-purple {
            background: rgba(124, 58, 237, 0.2) !important;
            color: #a78bfa !important;
        }

        /* Textes et labels */
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
        }

        .btn-quick {
            background: white;
            border: 1px solid var(--primary-light);
            color: var(--primary-dark);
            padding: 10px 20px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 0.85rem;
            white-space: nowrap;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-quick:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);
        }

        .btn-quick.primary {
            background: var(--primary);
            color: white;
            border: none;
        }

        .btn-quick.primary:hover {
            background: var(--primary-dark);
        }

        .card-widget {
            background: white;
            border-radius: var(--radius);
            padding: 24px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(145, 190, 150, 0.2);
            height: auto;
            transition: transform 0.2s;
        }

        .card-widget:hover {
            transform: translateY(-3px);
        }

        .kpi-card {
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .kpi-icon-wrapper {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .bg-gradient-green {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            color: #059669;
        }

        .bg-gradient-blue {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            color: #2563eb;
        }

        .bg-gradient-orange {
            background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%);
            color: #ea580c;
        }

        .bg-gradient-purple {
            background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%);
            color: #7c3aed;
        }

        .kpi-value {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--text-main);
            margin-top: 10px;
        }

        .kpi-label {
            font-size: 0.8rem;
            text-transform: uppercase;
            font-weight: 700;
            color: var(--text-light);
        }

        .table-custom {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
        }

        .table-custom th {
            font-size: 0.75rem;
            text-transform: uppercase;
            color: var(--text-light);
            font-weight: 700;
            padding: 0 16px 8px;
            border: none;
        }

        .table-custom td {
            background: white;
            padding: 16px;
            vertical-align: middle;
            border-top: 1px solid #f1f5f9;
            border-bottom: 1px solid #f1f5f9;
        }

        .table-custom td:first-child {
            border-left: 1px solid #f1f5f9;
            border-top-left-radius: 12px;
            border-bottom-left-radius: 12px;
        }

        .table-custom td:last-child {
            border-right: 1px solid #f1f5f9;
            border-top-right-radius: 12px;
            border-bottom-right-radius: 12px;
        }

        .table-custom tr:hover td {
            background-color: #f8fafc;
            border-color: #cbd5e1;
        }

        .progress-slim {
            height: 8px;
            border-radius: 4px;
            background-color: #f1f5f9;
            overflow: hidden;
        }

        .progress-bar-green {
            background: linear-gradient(90deg, var(--primary-light), var(--primary));
        }

        .progress-bar-warning {
            background: linear-gradient(90deg, #fbbf24, #d97706);
        }

        .progress-bar-danger {
            background: linear-gradient(90deg, #f87171, #dc2626);
        }

        .activity-item {
            display: flex;
            gap: 12px;
            padding-bottom: 16px;
            margin-bottom: 16px;
            border-bottom: 1px dashed #e2e8f0;
        }

        .activity-item:last-child {
            border: none;
            margin: 0;
            padding: 0;
        }

        .activity-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--text-light);
            margin-top: 6px;
            flex-shrink: 0;
        }

        .activity-dot.success {
            background: var(--primary);
            box-shadow: 0 0 0 3px #d1fae5;
        }

        .activity-dot.info {
            background: #3b82f6;
            box-shadow: 0 0 0 3px #dbeafe;
        }

        .activity-dot.warning {
            background: #f59e0b;
            box-shadow: 0 0 0 3px #fef3c7;
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

    <div class="container-fluid px-4 px-xl-5" style="max-width: 1600px; margin-top: 10px;">
        <div class="row mb-4 align-items-end">
            <div class="col-md-6 mb-3 mb-md-0">
                <h3 class="fw-bolder m-0" style="color: var(--primary-dark);">Vue d'ensemble</h3>
                <div class="text-muted">Bienvenue dans votre centre de contrôle financier.</div>
            </div>

            <!-- CORRECTION ICI: Ajout de d-flex et gap-2 pour aligner les boutons -->
            <div class="col-md-6">
                <div class="quick-actions d-flex gap-2 flex-wrap justify-content-md-end">
                    <a href="manage_data.php?action=add" class="btn-quick"><i class="bi bi-person-plus-fill"></i> Nouv.
                        Utilisateur</a>
                    <!-- BOUTON D'EXPORT CONSERVÉ -->
                    <a href="reports.php" class="btn-quick"><i class="bi bi-file-earmark-spreadsheet"></i> Export
                        Global</a>
                    <!-- BOUTON VERS LA PAGE PILOTAGE -->
                    <a href="manage_pending.php" class="btn-quick primary"><i class="bi bi-layers-fill"></i> Pilotage
                        Paiements</a>
                </div>
            </div>
        </div>

        <!-- KPI CARDS -->
        <div class="row g-4 mb-5">
            <div class="col-xl-3 col-md-6">
                <div class="card-widget kpi-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="kpi-label">Validation Finance</div>
                            <div class="kpi-value"><?= $pending_finance ?></div>
                        </div>
                        <div class="kpi-icon-wrapper bg-gradient-orange"><i class="bi bi-hourglass-split"></i></div>
                    </div>
                    <div class="mt-3 small text-muted"><span
                            class="<?= $pending_finance > 0 ? 'text-warning fw-bold' : 'text-success' ?>"><?= $pending_finance > 0 ? 'Dossiers en attente' : 'Tout est à jour' ?></span>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card-widget kpi-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="kpi-label">Budget Utilisé</div>
                            <div class="d-flex align-items-end gap-2">
                                <div class="kpi-value"><?= $percent_used ?>%</div>
                            </div>
                        </div>
                        <div class="kpi-icon-wrapper bg-gradient-green"><i class="bi bi-pie-chart-fill"></i></div>
                    </div>
                    <div class="mt-3 w-100">
                        <div class="progress progress-slim">
                            <div class="progress-bar progress-bar-green" role="progressbar"
                                style="width: <?= $percent_used ?>%"></div>
                        </div>
                        <div class="d-flex justify-content-between mt-1" style="font-size: 0.7rem;"><span
                                class="text-muted">Conso:
                                <?= number_format($budget_consumed, 0, ',', ' ') ?></span><span
                                class="text-muted">Total: <?= number_format($budget_total, 0, ',', ' ') ?></span></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card-widget kpi-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="kpi-label">Décaissements (Année)</div>
                            <div class="kpi-value"><?= number_format($paid_year, 0, ',', ' ') ?> <small
                                    class="fs-6 text-muted fw-normal">DH</small></div>
                        </div>
                        <div class="kpi-icon-wrapper bg-gradient-blue"><i class="bi bi-wallet-fill"></i></div>
                    </div>
                    <div class="mt-3 small text-muted"><i class="bi bi-arrow-up-right text-success"></i> Cumul payé
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card-widget kpi-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="kpi-label">Tickets Support</div>
                            <div class="kpi-value"><?= $open_tickets ?></div>
                        </div>
                        <div class="kpi-icon-wrapper bg-gradient-purple"><i class="bi bi-headset"></i></div>
                    </div>
                    <div class="mt-3 small text-muted">Réclamations ouvertes</div>
                </div>
            </div>
        </div>

        <div class="row g-4 align-items-start">
            <!-- GAUCHE -->
            <div class="col-lg-8">
                <!-- À VALIDER (PRIORITAIRE) -->
                <div class="card-widget mb-4 p-0 overflow-hidden">
                    <div
                        class="p-4 border-bottom d-flex justify-content-between align-items-center bg-light bg-opacity-25">
                        <h6 class="fw-bold m-0 text-dark"><i class="bi bi-check2-circle text-primary me-2"></i>À Valider
                            Prioritairement</h6>
                        <a href="manage_pending.php" class="text-decoration-none small fw-bold text-primary">Voir toute
                            la liste</a>
                    </div>
                    <div class="p-3">
                        <table class="table-custom">
                            <thead>
                                <tr>
                                    <th>Collaborateur</th>
                                    <th>Détails Mission</th>
                                    <th>Montant</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($tasks)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">Aucune demande en attente.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($tasks as $t):
                                        $u_img = !empty($t['user_avatar']) ? '../../assets/img/' . $t['user_avatar'] : '../../assets/img/default.png';
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-3">
                                                    <img src="<?= htmlspecialchars($u_img) ?>" class="rounded-circle" width="32"
                                                        height="32" style="object-fit:cover;">
                                                    <div style="line-height:1.2;">
                                                        <div class="fw-bold text-dark small">
                                                            <?= htmlspecialchars($t['user_name']) ?></div>
                                                        <div class="text-muted" style="font-size:0.7rem;">
                                                            <?= htmlspecialchars($t['nom_team'] ?? '-') ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-medium text-dark small"><?= htmlspecialchars($t['titre_dem']) ?>
                                                </div>
                                                <div class="text-muted" style="font-size:0.7rem;">Ref: #<?= $t['id_dem'] ?> •
                                                    <?= date('d/m', strtotime($t['date_dep'])) ?></div>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-dark"><?= number_format($t['montant_total'], 2) ?> DH
                                                </div>
                                            </td>
                                            <td class="text-end"><a href="validate_demande.php?id=<?= $t['id_dem'] ?>"
                                                    class="btn btn-sm btn-outline-success rounded-pill px-3">Traiter</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <!-- CHART -->
                <div class="card-widget">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h6 class="fw-bold m-0">Tendance des dépenses</h6><select
                            class="form-select form-select-sm w-auto border-0 bg-light">
                            <option>Cette année</option>
                        </select>
                    </div>
                    <div style="height: 250px; width: 100%;"><canvas id="trendChart"></canvas></div>
                </div>
            </div>
            <!-- DROITE (Budgets & Logs - Code conservé) -->
            <div class="col-lg-4">
                <div class="card-widget mb-4">
                    <h6 class="fw-bold mb-3">Santé Budgétaire</h6>
                    <div class="d-flex flex-column gap-3"><?php if (empty($teamBudgets)): ?><div
                                class="text-muted small">Aucune donnée budgétaire.</div>
                            <?php else: ?><?php foreach ($teamBudgets as $tb): $pct = ($tb['budget_annuel'] > 0) ? ($tb['budget_consomme'] / $tb['budget_annuel']) * 100 : 0;
                                                                    $colorClass = 'progress-bar-green';
                                                                    if ($pct > 50) $colorClass = 'progress-bar-warning';
                                                                    if ($pct > 80) $colorClass = 'progress-bar-danger'; ?>
                            <div>
                                <div class="d-flex justify-content-between small mb-1"><span
                                        class="fw-bold"><?= htmlspecialchars($tb['nom_team']) ?></span><span
                                        class="text-muted"><?= round($pct) ?>%</span></div>
                                <div class="progress progress-slim">
                                    <div class="progress-bar <?= $colorClass ?>" role="progressbar"
                                        style="width: <?= $pct ?>%"></div>
                                </div>
                            </div><?php endforeach; ?><?php endif; ?>
                    </div>
                    <div class="mt-3 text-center"><a href="manage_data.php?tab=teams"
                            class="small fw-bold text-decoration-none text-primary">Gérer les budgets <i
                                class="bi bi-arrow-right"></i></a></div>
                </div>
                <div class="card-widget">
                    <h6 class="fw-bold mb-4">Activité Récente</h6>
                    <div class="d-flex flex-column"><?php if (empty($logs)): ?><div class="text-muted small">Aucun log
                                enregistré.</div>
                            <?php else: ?><?php foreach ($logs as $log): $dotColor = 'info';
                                                            if (strpos($log['action'], 'Valide') !== false) $dotColor = 'success';
                                                            if (strpos($log['action'], 'Rejet') !== false) $dotColor = 'warning';
                                                            $details = json_decode($log['details'], true);
                                                            $detailTxt = $details['description'] ?? $log['action']; ?>
                            <div class="activity-item">
                                <div class="activity-dot <?= $dotColor ?>"></div>
                                <div>
                                    <div class="small fw-bold text-dark"><?= htmlspecialchars($log['nom'] ?? 'Système') ?>
                                    </div>
                                    <div class="text-muted" style="font-size: 0.75rem; line-height: 1.3;">
                                        <?= htmlspecialchars($detailTxt) ?></div>
                                    <div class="text-muted mt-1" style="font-size: 0.65rem;">
                                        <?= date('d/m H:i', strtotime($log['created_at'])) ?></div>
                                </div>
                            </div><?php endforeach; ?><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="my-5 text-center small text-muted">&copy; <?= date('Y') ?> RembourseMaroc Admin Panel</div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        Chart.defaults.font.family = "'Inter', sans-serif";
        Chart.defaults.color = '#64748b';
        const ctxTrend = document.getElementById('trendChart').getContext('2d');
        const gradient = ctxTrend.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(16, 185, 129, 0.2)');
        gradient.addColorStop(1, 'rgba(16, 185, 129, 0)');
        new Chart(ctxTrend, {
            type: 'line',
            data: {
                labels: <?= $trendLabels ?>,
                datasets: [{
                    label: 'Montant Validé (DH)',
                    data: <?= $trendValues ?>,
                    borderColor: '#10b981',
                    backgroundColor: gradient,
                    borderWidth: 3,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#059669',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#064e3b',
                        padding: 12,
                        cornerRadius: 8,
                        displayColors: false
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: true
                        }
                    },
                    y: {
                        border: {
                            display: false
                        },
                        grid: {
                            display: false
                        },
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
    <script src="../../assets/js/admin_theme.js"></script>
</body>

</html>