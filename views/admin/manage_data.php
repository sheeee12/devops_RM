<?php
// -------------------------------------------------------------------------
// VUE ADMIN : GESTION UNIFIÉE (UTILISATEURS & ÉQUIPES)
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

$message = "";
$error = "";
// On récupère l'onglet actif soit par GET (filtres), soit par défaut
$active_tab = $_GET['tab'] ?? 'users';

// -------------------------------------------------------------------------
// TRAITEMENT DES FORMULAIRES (POST - ACTIONS CRUD)
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- ACTIONS UTILISATEURS ---
    if (isset($_POST['action_user'])) {
        $active_tab = 'users';
        $nom = trim($_POST['nom']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        // Normaliser le rôle : 'employe' -> 'employee'
        if ($role === 'employe') {
            $role = 'employee';
        }
        $team_id = !empty($_POST['team_id']) ? $_POST['team_id'] : null;
        $id_user = !empty($_POST['user_id']) ? $_POST['user_id'] : null;

        try {
            if ($_POST['action_user'] === 'delete' && $id_user) {
                $db->prepare("DELETE FROM users WHERE user_id = ?")->execute([$id_user]);
                $message = "Utilisateur supprimé.";
            } else {
                // Création ou Édition
                if ($id_user) {
                    // UPDATE
                    $sql = "UPDATE users SET nom=?, email=?, role=?, team_id=? WHERE user_id=?";
                    $params = [$nom, $email, $role, $team_id, $id_user];

                    if (!empty($_POST['password'])) {
                        $sql = "UPDATE users SET nom=?, email=?, role=?, team_id=?, password=? WHERE user_id=?";
                        $params = [$nom, $email, $role, $team_id, password_hash($_POST['password'], PASSWORD_DEFAULT), $id_user];
                    }
                    $db->prepare($sql)->execute($params);
                    $message = "Utilisateur modifié.";
                } else {
                    // INSERT
                    if (!empty($_POST['password'])) {
                        $pwd = password_hash($_POST['password'], PASSWORD_DEFAULT);
                        $stmt = $db->prepare("INSERT INTO users (nom, email, password, role, team_id, avatar) VALUES (?, ?, ?, ?, ?, 'default.png')");
                        $stmt->execute([$nom, $email, $pwd, $role, $team_id]);
                        $message = "Utilisateur créé.";
                    } else {
                        $error = "Le mot de passe est obligatoire pour une création.";
                    }
                }
            }
        } catch (PDOException $e) {
            $error = "Erreur SGBD : " . $e->getMessage();
        }
    }

    // --- ACTIONS ÉQUIPES ---
    if (isset($_POST['action_team'])) {
        $active_tab = 'teams';
        $nom_team = trim($_POST['nom_team']);
        $budget = floatval($_POST['budget_annuel']);
        $manager_id = !empty($_POST['manager_id']) ? $_POST['manager_id'] : null;
        $id_team = !empty($_POST['team_id']) ? $_POST['team_id'] : null;

        try {
            if ($_POST['action_team'] === 'delete' && $id_team) {
                $db->prepare("UPDATE users SET team_id = NULL WHERE team_id = ?")->execute([$id_team]);
                $db->prepare("DELETE FROM teams WHERE team_id = ?")->execute([$id_team]);
                $message = "Équipe supprimée.";
            } else {
                if ($id_team) {
                    // UPDATE
                    $db->prepare("UPDATE teams SET nom_team=?, budget_annuel=?, manager_id=? WHERE team_id=?")
                        ->execute([$nom_team, $budget, $manager_id, $id_team]);
                    $message = "Équipe mise à jour.";
                } else {
                    // INSERT
                    $db->prepare("INSERT INTO teams (nom_team, budget_annuel, budget_consomme, manager_id) VALUES (?, ?, 0, ?)")
                        ->execute([$nom_team, $budget, $manager_id]);
                    $message = "Équipe créée.";
                }
            }
        } catch (PDOException $e) {
            $error = "Erreur SGBD : " . $e->getMessage();
        }
    }
}

// -------------------------------------------------------------------------
// RECUPERATION DES DONNEES AVEC FILTRES ET TRI
// -------------------------------------------------------------------------

// Listes pour les Selects des formulaires (Modals et Filtres)
$all_teams_select = $db->query("SELECT team_id, nom_team FROM teams ORDER BY nom_team")->fetchAll(PDO::FETCH_ASSOC);
$all_users_select = $db->query("SELECT user_id, nom FROM users ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);

// --- 1. FILTRAGE UTILISATEURS ---
$sql_users = "SELECT u.*, t.nom_team FROM users u LEFT JOIN teams t ON u.team_id = t.team_id WHERE 1=1";
$params_users = [];

// Recherche texte
if (!empty($_GET['search_user'])) {
    $search = "%" . trim($_GET['search_user']) . "%";
    $sql_users .= " AND (u.nom LIKE ? OR u.email LIKE ?)";
    $params_users[] = $search;
    $params_users[] = $search;
}
// Filtre Rôle
if (!empty($_GET['filter_role'])) {
    $filter_role = $_GET['filter_role'];
    // Normaliser le rôle : 'employe' -> 'employee'
    if ($filter_role === 'employe') {
        $filter_role = 'employee';
    }
    $sql_users .= " AND u.role = ?";
    $params_users[] = $filter_role;
}
// Filtre Équipe
if (!empty($_GET['filter_team'])) {
    $sql_users .= " AND u.team_id = ?";
    $params_users[] = $_GET['filter_team'];
}
// Tri Utilisateurs
$sort_user = $_GET['sort_user'] ?? 'nom_asc';
switch ($sort_user) {
    case 'nom_desc':
        $sql_users .= " ORDER BY u.nom DESC";
        break;
    case 'role':
        $sql_users .= " ORDER BY u.role ASC, u.nom ASC";
        break;
    case 'team':
        $sql_users .= " ORDER BY t.nom_team ASC, u.nom ASC";
        break;
    default:
        $sql_users .= " ORDER BY u.nom ASC";
        break; // nom_asc
}

$stmt_u = $db->prepare($sql_users);
$stmt_u->execute($params_users);
$users = $stmt_u->fetchAll(PDO::FETCH_ASSOC);


// --- 2. FILTRAGE ÉQUIPES ---
$sql_teams = "SELECT t.*, u.nom as manager_nom FROM teams t LEFT JOIN users u ON t.manager_id = u.user_id WHERE 1=1";
$params_teams = [];

// Recherche texte équipe
if (!empty($_GET['search_team'])) {
    $sql_teams .= " AND t.nom_team LIKE ?";
    $params_teams[] = "%" . trim($_GET['search_team']) . "%";
}

// Tri Équipes
$sort_team = $_GET['sort_team'] ?? 'nom_asc';
switch ($sort_team) {
    case 'budget_desc':
        $sql_teams .= " ORDER BY t.budget_annuel DESC";
        break;
    case 'budget_asc':
        $sql_teams .= " ORDER BY t.budget_annuel ASC";
        break;
    case 'conso_desc':
        $sql_teams .= " ORDER BY t.budget_consomme DESC";
        break;
    default:
        $sql_teams .= " ORDER BY t.nom_team ASC";
        break;
}

$stmt_t = $db->prepare($sql_teams);
$stmt_t->execute($params_teams);
$teams = $stmt_t->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Gestion Données | Admin</title>
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
        [data-theme="dark"] .nav-pills,
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

        [data-theme="dark"] .nav-pills .nav-link {
            color: var(--text-main) !important;
        }

        [data-theme="dark"] .nav-pills .nav-link.active {
            background-color: var(--primary) !important;
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
            transition: background-color 0.3s, color 0.3s;
        }

        /* Header */
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

        /* Tabs Custom */
        .nav-pills .nav-link {
            color: var(--text-light);
            font-weight: 600;
            border-radius: 10px;
            padding: 10px 20px;
        }

        .nav-pills .nav-link.active {
            background-color: var(--primary);
            color: white;
        }

        /* Card & Table */
        .card-widget {
            background: white;
            border-radius: var(--radius);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(145, 190, 150, 0.2);
            overflow: hidden;
        }

        .table-custom {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table-custom th {
            font-size: 0.75rem;
            text-transform: uppercase;
            color: var(--text-light);
            font-weight: 700;
            padding: 15px 20px;
            border-bottom: 1px solid #f1f5f9;
            background: #f8fafc;
            text-align: left;
        }

        .table-custom td {
            padding: 15px 20px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
        }

        .table-custom tr:last-child td {
            border-bottom: none;
        }

        .table-custom tr:hover td {
            background-color: #f8fafc;
        }

        .role-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .role-admin {
            background: #fee2e2;
            color: #991b1b;
        }

        .role-manager {
            background: #fef3c7;
            color: #92400e;
        }

        .role-employe {
            background: #e0f2fe;
            color: #075985;
        }

        .filter-bar {
            background-color: #f8fafc;
            padding: 15px 20px;
            border-bottom: 1px solid #f1f5f9;
        }
    </style>
</head>

<body>

    <!-- HEADER -->
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
            <a href="manage_data.php?tab=users"
                class="nav-item-link <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_data.php' && (!isset($_GET['tab']) || $_GET['tab'] == 'users')) ? 'active' : ''; ?>">
                <i class="bi bi-people me-2"></i>Utilisateurs
            </a>

            <!-- Lien 4: Équipes (Onglet spécifique) -->
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


    <div class="container-fluid px-4 px-xl-5" style="max-width: 1400px; margin-top: 20px;">

        <!-- ALERTS -->
        <?php if ($message): ?>
            <div
                class="alert alert-success border-0 bg-success bg-opacity-10 text-success fw-bold d-flex align-items-center mb-4">
                <i class="bi bi-check-circle-fill me-2"></i> <?= $message ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div
                class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger fw-bold d-flex align-items-center mb-4">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <!-- TITLE & TABS -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
            <div>
                <h3 class="fw-bolder m-0" style="color: var(--primary-dark);">Gestion des Données</h3>
                <div class="text-muted">Administrez les utilisateurs et les équipes.</div>
            </div>

            <ul class="nav nav-pills bg-white p-1 rounded-3 border" role="tablist">
                <li class="nav-item">
                    <button class="nav-link <?= $active_tab == 'users' ? 'active' : '' ?>" id="pills-users-tab"
                        data-bs-toggle="pill" data-bs-target="#pills-users" type="button"
                        onclick="window.history.pushState({}, '', '?tab=users')">
                        <i class="bi bi-people me-2"></i>Utilisateurs
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link <?= $active_tab == 'teams' ? 'active' : '' ?>" id="pills-teams-tab"
                        data-bs-toggle="pill" data-bs-target="#pills-teams" type="button"
                        onclick="window.history.pushState({}, '', '?tab=teams')">
                        <i class="bi bi-diagram-3 me-2"></i>Équipes
                    </button>
                </li>
            </ul>
        </div>

        <div class="tab-content" id="pills-tabContent">

            <!-- ====================================
                 ONGLET UTILISATEURS
            ==================================== -->
            <div class="tab-pane fade <?= $active_tab == 'users' ? 'show active' : '' ?>" id="pills-users"
                role="tabpanel">
                <div class="card-widget">

                    <!-- En-tête + Bouton Ajouter -->
                    <div class="p-4 d-flex justify-content-between align-items-center border-bottom">
                        <h6 class="fw-bold m-0"><i class="bi bi-person-lines-fill text-primary me-2"></i>Liste du
                            personnel</h6>
                        <button class="btn btn-sm btn-success rounded-pill px-3 fw-bold" data-bs-toggle="modal"
                            data-bs-target="#modalUser" onclick="resetUserForm()">
                            <i class="bi bi-plus-lg me-1"></i> Ajouter
                        </button>
                    </div>

                    <!-- BARRE DE FILTRES UTILISATEURS -->
                    <div class="filter-bar">
                        <form method="GET" class="row g-2 align-items-center">
                            <input type="hidden" name="tab" value="users">

                            <!-- Recherche -->
                            <div class="col-md-3">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-white border-end-0"><i
                                            class="bi bi-search text-muted"></i></span>
                                    <input type="text" name="search_user" class="form-control border-start-0 ps-0"
                                        placeholder="Nom ou email..."
                                        value="<?= htmlspecialchars($_GET['search_user'] ?? '') ?>">
                                </div>
                            </div>

                            <!-- Filtre Rôle -->
                            <div class="col-md-2">
                                <select name="filter_role" class="form-select form-select-sm">
                                    <option value="">-- Tous Rôles --</option>
                                    <option value="employee"
                                        <?= (isset($_GET['filter_role']) && ($_GET['filter_role'] == 'employee' || $_GET['filter_role'] == 'employe')) ? 'selected' : '' ?>>
                                        Employé</option>
                                    <option value="manager"
                                        <?= (isset($_GET['filter_role']) && $_GET['filter_role'] == 'manager') ? 'selected' : '' ?>>
                                        Manager</option>
                                    <option value="admin"
                                        <?= (isset($_GET['filter_role']) && $_GET['filter_role'] == 'admin') ? 'selected' : '' ?>>
                                        Admin</option>
                                </select>
                            </div>

                            <!-- Filtre Équipe -->
                            <div class="col-md-3">
                                <select name="filter_team" class="form-select form-select-sm">
                                    <option value="">-- Toutes Équipes --</option>
                                    <?php foreach ($all_teams_select as $t): ?>
                                        <option value="<?= $t['team_id'] ?>"
                                            <?= (isset($_GET['filter_team']) && $_GET['filter_team'] == $t['team_id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($t['nom_team']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Tri -->
                            <div class="col-md-2">
                                <select name="sort_user" class="form-select form-select-sm">
                                    <option value="nom_asc"
                                        <?= (isset($_GET['sort_user']) && $_GET['sort_user'] == 'nom_asc') ? 'selected' : '' ?>>
                                        Nom (A-Z)</option>
                                    <option value="nom_desc"
                                        <?= (isset($_GET['sort_user']) && $_GET['sort_user'] == 'nom_desc') ? 'selected' : '' ?>>
                                        Nom (Z-A)</option>
                                    <option value="role"
                                        <?= (isset($_GET['sort_user']) && $_GET['sort_user'] == 'role') ? 'selected' : '' ?>>
                                        Par Rôle</option>
                                    <option value="team"
                                        <?= (isset($_GET['sort_user']) && $_GET['sort_user'] == 'team') ? 'selected' : '' ?>>
                                        Par Équipe</option>
                                </select>
                            </div>

                            <!-- Boutons -->
                            <div class="col-md-2 d-flex gap-1">
                                <button type="submit" class="btn btn-sm btn-primary flex-grow-1">Filtrer</button>
                                <a href="manage_data.php?tab=users" class="btn btn-sm btn-outline-secondary"
                                    title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
                            </div>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table-custom">
                            <thead>
                                <tr>
                                    <th>Identité</th>
                                    <th>Rôle</th>
                                    <th>Équipe</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($users) > 0): ?>
                                    <?php foreach ($users as $u):
                                        $roleBadge = 'role-employe';
                                        if ($u['role'] == 'admin') $roleBadge = 'role-admin';
                                        if ($u['role'] == 'manager') $roleBadge = 'role-manager';
                                        // Normaliser l'affichage du rôle
                                        $roleDisplay = $u['role'];
                                        if ($roleDisplay === 'employee') {
                                            $roleDisplay = 'Employé';
                                        } elseif ($roleDisplay === 'manager') {
                                            $roleDisplay = 'Manager';
                                        } elseif ($roleDisplay === 'admin') {
                                            $roleDisplay = 'Admin';
                                        }
                                        $u_img = !empty($u['avatar']) ? '../../assets/img/' . $u['avatar'] : '../../assets/img/default.png';
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-3">
                                                    <img src="<?= htmlspecialchars($u_img) ?>" class="rounded-circle border"
                                                        width="40" height="40" style="object-fit:cover;">
                                                    <div>
                                                        <div class="fw-bold text-dark"><?= htmlspecialchars($u['nom']) ?>
                                                        </div>
                                                        <div class="small text-muted"><?= htmlspecialchars($u['email']) ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><span class="role-badge <?= $roleBadge ?>"><?= htmlspecialchars($roleDisplay) ?></span>
                                            </td>
                                            <td>
                                                <?php if ($u['nom_team']): ?>
                                                    <span class="badge bg-light text-dark border fw-normal"><i
                                                            class="bi bi-people me-1"></i><?= htmlspecialchars($u['nom_team']) ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted small">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-light border"
                                                    onclick='editUser(<?= json_encode($u) ?>)'>
                                                    <i class="bi bi-pencil-fill text-primary"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">Aucun utilisateur trouvé.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ====================================
                 ONGLET ÉQUIPES
            ==================================== -->
            <div class="tab-pane fade <?= $active_tab == 'teams' ? 'show active' : '' ?>" id="pills-teams"
                role="tabpanel">
                <div class="card-widget">

                    <!-- En-tête + Bouton Ajouter -->
                    <div class="p-4 d-flex justify-content-between align-items-center border-bottom">
                        <h6 class="fw-bold m-0"><i class="bi bi-diagram-3-fill text-primary me-2"></i>Départements &
                            Budgets</h6>
                        <button class="btn btn-sm btn-success rounded-pill px-3 fw-bold" data-bs-toggle="modal"
                            data-bs-target="#modalTeam" onclick="resetTeamForm()">
                            <i class="bi bi-plus-lg me-1"></i> Ajouter
                        </button>
                    </div>

                    <!-- BARRE DE FILTRES ÉQUIPES -->
                    <div class="filter-bar">
                        <form method="GET" class="row g-2 align-items-center">
                            <input type="hidden" name="tab" value="teams">

                            <!-- Recherche -->
                            <div class="col-md-5">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-white border-end-0"><i
                                            class="bi bi-search text-muted"></i></span>
                                    <input type="text" name="search_team" class="form-control border-start-0 ps-0"
                                        placeholder="Nom de l'équipe..."
                                        value="<?= htmlspecialchars($_GET['search_team'] ?? '') ?>">
                                </div>
                            </div>

                            <!-- Tri -->
                            <div class="col-md-4">
                                <select name="sort_team" class="form-select form-select-sm">
                                    <option value="nom_asc"
                                        <?= (isset($_GET['sort_team']) && $_GET['sort_team'] == 'nom_asc') ? 'selected' : '' ?>>
                                        Nom (A-Z)</option>
                                    <option value="budget_desc"
                                        <?= (isset($_GET['sort_team']) && $_GET['sort_team'] == 'budget_desc') ? 'selected' : '' ?>>
                                        Budget Annuel (Haut->Bas)</option>
                                    <option value="budget_asc"
                                        <?= (isset($_GET['sort_team']) && $_GET['sort_team'] == 'budget_asc') ? 'selected' : '' ?>>
                                        Budget Annuel (Bas->Haut)</option>
                                    <option value="conso_desc"
                                        <?= (isset($_GET['sort_team']) && $_GET['sort_team'] == 'conso_desc') ? 'selected' : '' ?>>
                                        Plus gros consommateurs</option>
                                </select>
                            </div>

                            <!-- Boutons -->
                            <div class="col-md-3 d-flex gap-1">
                                <button type="submit" class="btn btn-sm btn-primary flex-grow-1">Filtrer</button>
                                <a href="manage_data.php?tab=teams" class="btn btn-sm btn-outline-secondary"
                                    title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
                            </div>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table-custom">
                            <thead>
                                <tr>
                                    <th>Nom de l'équipe</th>
                                    <th>Manager</th>
                                    <th>Budget Annuel</th>
                                    <th>Consommé</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($teams) > 0): ?>
                                    <?php foreach ($teams as $t):
                                        $percent = ($t['budget_annuel'] > 0) ? ($t['budget_consomme'] / $t['budget_annuel']) * 100 : 0;
                                        $color = $percent > 80 ? 'text-danger' : 'text-success';
                                    ?>
                                        <tr>
                                            <td class="fw-bold text-dark fs-6"><?= htmlspecialchars($t['nom_team']) ?></td>
                                            <td>
                                                <?php if ($t['manager_nom']): ?>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <i class="bi bi-person-badge text-muted"></i>
                                                        <span><?= htmlspecialchars($t['manager_nom']) ?></span>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted small italic">Non assigné</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= number_format($t['budget_annuel'], 2) ?> DH</td>
                                            <td>
                                                <div class="<?= $color ?> fw-bold">
                                                    <?= number_format($t['budget_consomme'], 2) ?> DH</div>
                                                <div class="progress mt-1" style="height: 4px; width: 80px;">
                                                    <div class="progress-bar <?= $percent > 80 ? 'bg-danger' : 'bg-success' ?>"
                                                        style="width: <?= $percent ?>%"></div>
                                                </div>
                                            </td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-light border"
                                                    onclick='editTeam(<?= json_encode($t) ?>)'>
                                                    <i class="bi bi-pencil-fill text-primary"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">Aucune équipe trouvée.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div> <!-- Fin Tab Content -->
    </div>

    <!-- ====================================
         MODAL UTILISATEUR
    ==================================== -->
    <div class="modal fade" id="modalUser" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalUserTitle">Nouvel Utilisateur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="user_id" id="u_id">

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Nom complet</label>
                            <input type="text" name="nom" id="u_nom" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Email</label>
                            <input type="email" name="email" id="u_email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Mot de passe</label>
                            <input type="password" name="password" class="form-control"
                                placeholder="Laisser vide si inchangé">
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Rôle</label>
                                <select name="role" id="u_role" class="form-select">
                                    <option value="employee">Employé</option>
                                    <option value="manager">Manager</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Équipe</label>
                                <select name="team_id" id="u_team" class="form-select">
                                    <option value="">Aucune</option>
                                    <?php foreach ($all_teams_select as $ts): ?>
                                        <option value="<?= $ts['team_id'] ?>"><?= htmlspecialchars($ts['nom_team']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="submit" name="action_user" value="delete" class="btn btn-danger me-auto d-none"
                            id="btnDelUser" onclick="return confirm('Supprimer cet utilisateur ?')">Supprimer</button>
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="action_user" value="save"
                            class="btn btn-success fw-bold">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ====================================
         MODAL ÉQUIPE
    ==================================== -->
    <div class="modal fade" id="modalTeam" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalTeamTitle">Nouvelle Équipe</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="team_id" id="t_id">

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Nom de l'équipe</label>
                            <input type="text" name="nom_team" id="t_nom" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Budget Annuel (DH)</label>
                            <input type="number" step="0.01" name="budget_annuel" id="t_budget" class="form-control"
                                required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Manager Responsable</label>
                            <select name="manager_id" id="t_manager" class="form-select">
                                <option value="">-- Sélectionner un manager --</option>
                                <?php foreach ($all_users_select as $us): ?>
                                    <option value="<?= $us['user_id'] ?>"><?= htmlspecialchars($us['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="submit" name="action_team" value="delete" class="btn btn-danger me-auto d-none"
                            id="btnDelTeam" onclick="return confirm('Supprimer cette équipe ?')">Supprimer</button>
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="action_team" value="save"
                            class="btn btn-success fw-bold">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/admin_theme.js"></script>
    <script>
        // --- JS POUR UTILISATEUR ---
        function resetUserForm() {
            document.getElementById('modalUserTitle').innerText = "Nouvel Utilisateur";
            document.getElementById('u_id').value = "";
            document.getElementById('u_nom').value = "";
            document.getElementById('u_email').value = "";
            document.getElementById('u_role').value = "employee";
            document.getElementById('u_team').value = "";
            document.getElementById('btnDelUser').classList.add('d-none');
        }

        function editUser(u) {
            var myModal = new bootstrap.Modal(document.getElementById('modalUser'));
            document.getElementById('modalUserTitle').innerText = "Modifier Utilisateur";
            document.getElementById('u_id').value = u.user_id;
            document.getElementById('u_nom').value = u.nom;
            document.getElementById('u_email').value = u.email;
            // Normaliser le rôle : 'employe' -> 'employee'
            var roleValue = u.role;
            if (roleValue === 'employe') {
                roleValue = 'employee';
            }
            document.getElementById('u_role').value = roleValue;
            document.getElementById('u_team').value = u.team_id || "";
            document.getElementById('btnDelUser').classList.remove('d-none');
            myModal.show();
        }

        // --- JS POUR ÉQUIPE ---
        function resetTeamForm() {
            document.getElementById('modalTeamTitle').innerText = "Nouvelle Équipe";
            document.getElementById('t_id').value = "";
            document.getElementById('t_nom').value = "";
            document.getElementById('t_budget').value = "";
            document.getElementById('t_manager').value = "";
            document.getElementById('btnDelTeam').classList.add('d-none');
        }

        function editTeam(t) {
            var myModal = new bootstrap.Modal(document.getElementById('modalTeam'));
            document.getElementById('modalTeamTitle').innerText = "Modifier Équipe";
            document.getElementById('t_id').value = t.team_id;
            document.getElementById('t_nom').value = t.nom_team;
            document.getElementById('t_budget').value = t.budget_annuel;
            document.getElementById('t_manager').value = t.manager_id || "";
            document.getElementById('btnDelTeam').classList.remove('d-none');
            myModal.show();
        }
    </script>
</body>

</html>