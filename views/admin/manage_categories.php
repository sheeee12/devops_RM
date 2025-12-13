<?php
// -------------------------------------------------------------------------
// VUE ADMIN : GESTION DES CATÉGORIES DE FRAIS
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

// --- TRAITEMENT DU FORMULAIRE (CRUD) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $nom = trim($_POST['nom_categ'] ?? '');
    $plafond = floatval($_POST['plafond_max'] ?? 0);
    $id_categ = intval($_POST['id_categ'] ?? 0);

    if ($action === 'save') {
        if ($id_categ > 0) {
            // EDIT
            $sql = "UPDATE categories SET nom_categ = ?, plafond_max = ? WHERE id_categ = ?";
            $db->prepare($sql)->execute([$nom, $plafond, $id_categ]);
            $message = "Catégorie mise à jour avec succès.";
        } else {
            // CREATE
            $sql = "INSERT INTO categories (nom_categ, plafond_max) VALUES (?, ?)";
            $db->prepare($sql)->execute([$nom, $plafond]);
            $message = "Nouvelle catégorie ajoutée.";
        }
    } elseif ($action === 'delete' && $id_categ > 0) {
        try {
            // Vérifier utilisation avant suppression (Sécurité intégrité)
            $check = $db->prepare("SELECT COUNT(*) FROM expense_line WHERE id_categ = ?");
            $check->execute([$id_categ]);
            if ($check->fetchColumn() > 0) {
                $error = "Impossible de supprimer : cette catégorie est utilisée dans des notes de frais existantes.";
            } else {
                $db->prepare("DELETE FROM categories WHERE id_categ = ?")->execute([$id_categ]);
                $message = "Catégorie supprimée.";
            }
        } catch (Exception $e) {
            $error = "Erreur lors de la suppression.";
        }
    }
}

$sql = "SELECT c.*, COUNT(el.id_categ) as usage_count, SUM(el.montant) as total_depense
        FROM categories c 
        LEFT JOIN expense_line el ON c.id_categ = el.id_categ 
        GROUP BY c.id_categ 
        ORDER BY c.nom_categ ASC";
$categories = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
$categories = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Calculs KPI
$total_cats = count($categories);
$avg_plafond = $total_cats > 0 ? array_sum(array_column($categories, 'plafond_max')) / $total_cats : 0;
// Trouver la catégorie la plus utilisée
$most_used = array_reduce($categories, function ($a, $b) {
    return $a ? ($a['usage_count'] > $b['usage_count'] ? $a : $b) : $b;
});
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Catégories | Admin</title>
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

        /* Éléments spécifiques */
        [data-theme="dark"] .kpi-icon,
        [data-theme="dark"] .bg-icon-purple,
        [data-theme="dark"] .bg-icon-blue,
        [data-theme="dark"] .bg-icon-orange {
            background: #334155 !important;
            opacity: 0.8;
        }

        [data-theme="dark"] .bg-icon-purple {
            background: rgba(147, 51, 234, 0.2) !important;
            color: #c084fc !important;
        }

        [data-theme="dark"] .bg-icon-blue {
            background: rgba(37, 99, 235, 0.2) !important;
            color: #60a5fa !important;
        }

        [data-theme="dark"] .bg-icon-orange {
            background: rgba(234, 88, 12, 0.2) !important;
            color: #fb923c !important;
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
            overflow: hidden;
            height: 100%;
        }

        /* KPI CARDS */
        .kpi-box {
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .kpi-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .bg-icon-purple {
            background: #f3e8ff;
            color: #9333ea;
        }

        .bg-icon-blue {
            background: #dbeafe;
            color: #2563eb;
        }

        .bg-icon-orange {
            background: #ffedd5;
            color: #ea580c;
        }

        /* TABLE */
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

        .table-custom tr:hover td {
            background-color: #f8fafc;
        }

        /* TAGS */
        .tag-plafond {
            padding: 4px 10px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.8rem;
        }

        .tag-high {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Rouge si > 5000 */
        .tag-med {
            background: #fef3c7;
            color: #92400e;
        }

        /* Orange si > 2000 */
        .tag-low {
            background: #dcfce7;
            color: #166534;
        }

        /* Vert si <= 2000 */
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
                <i class="bi bi-slash-circle-fill me-2"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <!-- HEADER SECTION -->
        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <h3 class="fw-bolder m-0" style="color: var(--primary-dark);">Types de Frais</h3>
                <div class="text-muted">Configurez les plafonds de remboursement autorisés.</div>
            </div>
            <button class="btn btn-primary d-flex align-items-center gap-2 fw-bold px-4 py-2 rounded-3 shadow-sm"
                onclick="openModal()">
                <i class="bi bi-plus-circle-fill"></i> Nouvelle Catégorie
            </button>
        </div>

        <!-- KPI CARDS -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card-widget kpi-box">
                    <div class="kpi-icon bg-icon-purple"><i class="bi bi-collection"></i></div>
                    <div>
                        <div class="text-muted small fw-bold text-uppercase">Types actifs</div>
                        <div class="fs-4 fw-bolder text-dark"><?= $total_cats ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card-widget kpi-box">
                    <div class="kpi-icon bg-icon-blue"><i class="bi bi-speedometer2"></i></div>
                    <div>
                        <div class="text-muted small fw-bold text-uppercase">Plafond Moyen</div>
                        <div class="fs-4 fw-bolder text-dark"><?= number_format($avg_plafond, 0, ',', ' ') ?> DH
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card-widget kpi-box">
                    <div class="kpi-icon bg-icon-orange"><i class="bi bi-star-fill"></i></div>
                    <div>
                        <div class="text-muted small fw-bold text-uppercase">La plus utilisée</div>
                        <div class="fs-5 fw-bolder text-dark text-truncate" style="max-width: 180px;">
                            <?= !empty($most_used) ? htmlspecialchars($most_used['nom_categ']) : '-' ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TABLEAU PRINCIPAL -->
        <div class="card-widget">
            <div class="p-4 border-bottom bg-light bg-opacity-25">
                <h6 class="fw-bold m-0 text-dark"><i class="bi bi-list-columns-reverse me-2 text-primary"></i>Grille
                    des
                    plafonds</h6>
            </div>
            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Nom de la catégorie</th>
                            <th>Plafond Autorisé (DH)</th>
                            <th>Utilisation</th>
                            <th>Dépenses cumulées</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($categories)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">Aucune catégorie configurée.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($categories as $cat):
                                $bgClass = 'tag-low';
                                if ($cat['plafond_max'] > 2000) $bgClass = 'tag-med';
                                if ($cat['plafond_max'] > 5000) $bgClass = 'tag-high';
                            ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($cat['nom_categ']) ?></div>
                                        <div class="small text-muted">ID: #<?= $cat['id_categ'] ?></div>
                                    </td>
                                    <td>
                                        <span class="tag-plafond <?= $bgClass ?>">
                                            <?= number_format($cat['plafond_max'], 2, ',', ' ') ?> DH
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi bi-file-earmark-text text-muted"></i>
                                            <span class="fw-bold"><?= $cat['usage_count'] ?></span>
                                            <span class="small text-muted">fois</span>
                                        </div>
                                    </td>
                                    <td class="text-muted small fw-medium">
                                        <?= $cat['total_depense'] ? number_format($cat['total_depense'], 2) . ' DH' : '-' ?>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-light border me-1"
                                            onclick='openModal(<?= json_encode($cat) ?>)' title="Modifier">
                                            <i class="bi bi-pencil-fill text-primary"></i>
                                        </button>

                                        <?php if ($cat['usage_count'] > 0): ?>
                                            <!-- Bouton désactivé si utilisé -->
                                            <span class="d-inline-block" tabindex="0" data-bs-toggle="tooltip"
                                                title="Impossible de supprimer : Catégorie utilisée dans l'historique.">
                                                <button class="btn btn-sm btn-light border text-muted" disabled>
                                                    <i class="bi bi-trash-fill"></i>
                                                </button>
                                            </span>
                                        <?php else: ?>
                                            <!-- Bouton actif si inutilisé -->
                                            <button class="btn btn-sm btn-light border text-danger"
                                                onclick='confirmDelete(<?= $cat['id_categ'] ?>)' title="Supprimer">
                                                <i class="bi bi-trash-fill"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- MODAL AJOUT / EDIT -->
    <div class="modal fade" id="categoryModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold" id="modalTitle">Nouvelle Catégorie</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form method="POST" id="catForm">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id_categ" id="id_categ" value="0">

                        <div class="mb-4">
                            <label class="form-label small fw-bold text-uppercase text-muted">Intitulé</label>
                            <input type="text" class="form-control form-control-lg bg-light border-0" name="nom_categ"
                                id="nom_categ" placeholder="Ex: Transport, Hôtel..." required>
                        </div>

                        <div class="mb-4">
                            <label class="form-label small fw-bold text-uppercase text-muted">Plafond Maximum
                                (DH)</label>
                            <div class="input-group">
                                <input type="number" step="0.01" class="form-control form-control-lg bg-light border-0"
                                    name="plafond_max" id="plafond_max" placeholder="0.00" required>
                                <span class="input-group-text border-0 bg-light fw-bold text-muted">DH</span>
                            </div>
                            <div class="form-text text-success"><i class="bi bi-info-circle me-1"></i> Ce montant
                                bloquera les saisies supérieures.</div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-success btn-lg fw-bold rounded-3">Enregistrer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- FORMULAIRE CACHÉ POUR SUPPRESSION -->
    <form method="POST" id="deleteForm" class="d-none">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id_categ" id="del_id_categ">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/admin_theme.js"></script>
    <script>
        // Initialisation des Tooltips Bootstrap
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })

        const modal = new bootstrap.Modal(document.getElementById('categoryModal'));

        function openModal(data = null) {
            if (data) {
                // Mode Édition
                document.getElementById('modalTitle').innerText = "Modifier la catégorie";
                document.getElementById('id_categ').value = data.id_categ;
                document.getElementById('nom_categ').value = data.nom_categ;
                document.getElementById('plafond_max').value = data.plafond_max;
            } else {
                // Mode Ajout
                document.getElementById('modalTitle').innerText = "Nouvelle Catégorie";
                document.getElementById('catForm').reset();
                document.getElementById('id_categ').value = 0;
            }
            modal.show();
        }

        function confirmDelete(id) {
            if (confirm("Êtes-vous sûr de vouloir supprimer cette catégorie ? Cette action est irréversible.")) {
                document.getElementById('del_id_categ').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
    </script>
</body>

</html>