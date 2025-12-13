<?php
// -------------------------------------------------------------------------
// VUE ADMIN : TRAITEMENT INDIVIDUEL (VALIDER = PAYER)
// -------------------------------------------------------------------------
require_once __DIR__ . '/../../includes/session.php';
requireRole('admin');
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../includes/security.php';

if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}
$id_dem = intval($_GET['id']);
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


// Infos Demande
$sql = "SELECT d.*, u.nom, u.email, u.avatar, t.nom_team, t.team_id 
        FROM demande d JOIN users u ON d.user_id = u.user_id 
        LEFT JOIN teams t ON u.team_id = t.team_id WHERE d.id_dem = ?";
$stmt = $db->prepare($sql);
$stmt->execute([$id_dem]);
$demande = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$demande) {
    echo "Demande introuvable.";
    exit();
}

// Lignes
$lignes = $db->prepare("SELECT el.*, c.nom_categ FROM expense_line el JOIN categories c ON el.id_categ = c.id_categ WHERE el.id_dem = ?");
$lignes->execute([$id_dem]);
$lignes = $lignes->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];

    if ($action === 'valider') {
        // --- LOGIQUE ADMIN : VALIDER = CONFIRMER PAIEMENT ---
        // Statut -> Paye
        $upd = $db->prepare("UPDATE demande SET status = 'Paye' WHERE id_dem = ?");
        $upd->execute([$id_dem]);

        // Débiter Budget Équipe
        if ($demande['team_id']) {
            $updTeam = $db->prepare("UPDATE teams SET budget_consomme = budget_consomme + ? WHERE team_id = ?");
            $updTeam->execute([$demande['montant_total'], $demande['team_id']]);
        }

        // Log (si la table audit_logs existe)
        try {
            $db->prepare("INSERT INTO audit_logs (user_id, action, details) VALUES (?, ?, ?)")
                ->execute([$user_id, 'Paiement Confirmé', json_encode(['id_dem' => $id_dem, 'msg' => 'Confirmé manuellement par Admin'])]);
        } catch (PDOException $e) {
            // Table audit_logs n'existe pas, ignorer
        }
    } elseif ($action === 'rejeter') {
        $motif = trim($_POST['motif_rejet']);
        $upd = $db->prepare("UPDATE demande SET status = 'Rejete', motif_rejet = ? WHERE id_dem = ?");
        $upd->execute([$motif, $id_dem]);

        // Log (si la table audit_logs existe)
        try {
            $db->prepare("INSERT INTO audit_logs (user_id, action, details) VALUES (?, ?, ?)")
                ->execute([$user_id, 'Rejet Admin', json_encode(['id_dem' => $id_dem, 'motif' => $motif])]);
        } catch (PDOException $e) {
            // Table audit_logs n'existe pas, ignorer
        }
    }

    header("Location: manage_pending.php"); // Redirection vers la liste des paiements
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Traitement #<?= $id_dem ?> | Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <style>
        /* STYLE UNIFIÉ (DASHBOARD) */
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
        }

        /* CARDS & TABLES */
        .card-widget {
            background: white;
            border-radius: var(--radius);
            padding: 24px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(145, 190, 150, 0.2);
            height: auto;
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

        /* Buttons & Specifics */
        .btn-action {
            width: 100%;
            padding: 12px;
            font-weight: 600;
            border-radius: 12px;
            transition: all 0.2s;
        }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .user-chip {
            background: #f1f5f9;
            padding: 10px 15px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
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

    <div class="container-fluid px-4 px-xl-5" style="max-width: 1400px; margin-top: 20px;">

        <!-- BREADCRUMB / TITLE -->
        <div class="row mb-4 align-items-center">
            <div class="col-md-8">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1 small text-muted">
                        <li class="breadcrumb-item"><a href="manage_pending.php"
                                class="text-decoration-none text-muted">Paiements</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Détails</li>
                    </ol>
                </nav>
                <h3 class="fw-bolder m-0" style="color: var(--primary-dark);">Dossier
                    #<?= str_pad($id_dem, 5, '0', STR_PAD_LEFT) ?></h3>
            </div>
            <div class="col-md-4 text-end">
                <span
                    class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill border border-success border-opacity-25">
                    <i class="bi bi-check-circle me-1"></i> Validé par Manager
                </span>
            </div>
        </div>

        <div class="row g-4">

            <!-- GAUCHE : DÉTAILS -->
            <div class="col-lg-8">
                <div class="card-widget mb-4">
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <div>
                            <h5 class="fw-bold text-dark m-0"><?= htmlspecialchars($demande['titre_dem']) ?></h5>
                            <div class="text-muted small mt-1">
                                Soumis le <?= date('d/m/Y', strtotime($demande['created_at'])) ?>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="small text-muted text-uppercase fw-bold">Montant Total</div>
                            <div class="fs-3 fw-bold text-dark"><?= number_format($demande['montant_total'], 2) ?>
                                <small class="fs-6 text-muted">DH</small>
                            </div>
                        </div>
                    </div>

                    <div class="user-chip mb-4">
                        <?php $u_img = !empty($demande['avatar']) ? '../../assets/img/' . $demande['avatar'] : '../../assets/img/default.png'; ?>
                        <img src="<?= htmlspecialchars($u_img) ?>" class="rounded-circle" width="45" height="45"
                            style="object-fit:cover;">
                        <div>
                            <div class="fw-bold text-dark"><?= htmlspecialchars($demande['nom']) ?></div>
                            <div class="text-muted small">
                                <?= htmlspecialchars($demande['nom_team'] ?? 'Aucune équipe') ?> •
                                <?= htmlspecialchars($demande['email']) ?></div>
                        </div>
                    </div>

                    <h6 class="fw-bold text-muted text-uppercase small mb-3">Lignes de frais</h6>
                    <div class="table-responsive">
                        <table class="table-custom">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Catégorie</th>
                                    <th>Justificatif</th>
                                    <th class="text-end">Montant</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lignes as $l): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-medium text-dark">
                                                <?= date('d/m/Y', strtotime($l['date_depense'])) ?></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border fw-normal">
                                                <?= htmlspecialchars($l['nom_categ']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($l['justificatif_path']): ?>
                                                <a href="../../uploads/justificatifs/<?= $l['justificatif_path'] ?>"
                                                    target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                                    <i class="bi bi-paperclip me-1"></i> Voir
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted small fst-italic">Non fourni</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end fw-bold text-dark">
                                            <?= number_format($l['montant'], 2) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- DROITE : ACTIONS -->
            <div class="col-lg-4">
                <div class="card-widget sticky-top" style="top: 100px;">
                    <h5 class="fw-bold mb-3">Traitement Financier</h5>

                    <form method="POST">
                        <div class="alert alert-primary bg-opacity-10 border-0 small mb-4">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            En validant, vous confirmez que le virement a été ordonné. Le budget de l'équipe sera
                            débité
                            immédiatement.
                        </div>

                        <button type="submit" name="action" value="valider" class="btn btn-success btn-action mb-4">
                            <i class="bi bi-check-lg me-2"></i> Confirmer Paiement
                        </button>

                        <hr class="text-muted opacity-25 my-4">

                        <label class="form-label fw-bold small text-muted">Zone de Rejet</label>
                        <textarea name="motif_rejet" class="form-control bg-light border-0 mb-3" rows="3"
                            placeholder="Motif du refus (obligatoire si rejet)..."
                            style="font-size: 0.9rem;"></textarea>

                        <button type="submit" name="action" value="rejeter" class="btn btn-outline-danger btn-action">
                            <i class="bi bi-x-circle me-2"></i> Rejeter le dossier
                        </button>
                    </form>
                </div>
            </div>

        </div>

        <div class="my-5 text-center small text-muted">&copy; <?= date('Y') ?> RembourseMaroc Admin Panel</div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/admin_theme.js"></script>
</body>

</html>