<?php
// -------------------------------------------------------------------------
// DASHBOARD EMPLOYÉ - DESIGN MODERNE & ANIMÉ
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

$db = Database::getInstance()->getConnexion();

// --- NOTIFICATIONS ---
require_once __DIR__ . '/../../includes/employee_notifications.php';
$notifications = getEmployeeNotifications($db, $user_id);
$notificationCount = count($notifications);

// --- 1. KPI ---
$stmt = $db->prepare("SELECT COUNT(*) FROM demande WHERE user_id = ? AND status IN ('Valide', 'Paye')");
$stmt->execute([$user_id]);
$kpi_validated = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM demande WHERE user_id = ? AND status = 'Rejete'");
$stmt->execute([$user_id]);
$kpi_rejected = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COALESCE(SUM(montant_total), 0) FROM demande WHERE user_id = ? AND status = 'Valide'");
$stmt->execute([$user_id]);
$kpi_pending_payment = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COALESCE(SUM(montant_total), 0) FROM demande WHERE user_id = ? AND status != 'Rejete' AND MONTH(date_dep) = MONTH(CURRENT_DATE()) AND YEAR(date_dep) = YEAR(CURRENT_DATE())");
$stmt->execute([$user_id]);
$kpi_month_spent = $stmt->fetchColumn();

// --- 2. GRAPHIQUES ---
$stmt = $db->prepare("SELECT c.nom_categ, SUM(el.montant) as total FROM expense_line el JOIN categories c ON el.id_categ = c.id_categ JOIN demande d ON el.id_dem = d.id_dem WHERE d.user_id = ? AND d.status != 'Rejete' GROUP BY c.nom_categ");
$stmt->execute([$user_id]);
$categData = $stmt->fetchAll(PDO::FETCH_ASSOC);
$categLabels = json_encode(array_column($categData, 'nom_categ'));
$categValues = json_encode(array_column($categData, 'total'));

$stmt = $db->prepare("SELECT DATE_FORMAT(date_dep, '%Y-%m') as mois, SUM(montant_total) as total FROM demande WHERE user_id = ? AND status != 'Rejete' GROUP BY mois ORDER BY mois ASC LIMIT 6");
$stmt->execute([$user_id]);
$monthData = $stmt->fetchAll(PDO::FETCH_ASSOC);
$formattedLabels = array_map(function ($m) {
    return date('M Y', strtotime($m . '-01'));
}, array_column($monthData, 'mois'));
$monthLabels = json_encode($formattedLabels);
$monthValues = json_encode(array_column($monthData, 'total'));

// --- 3. DERNIÈRES DEMANDES ---
$stmt = $db->prepare("SELECT * FROM demande WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$user_id]);
$recent_demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getStatusBadge($status)
{
    $styles = [
        'Valide' => ['bg' => '#dcfce7', 'text' => '#15803d', 'icon' => 'bi-check-circle-fill', 'label' => 'Validé'],
        'Paye' => ['bg' => '#e0f2fe', 'text' => '#0369a1', 'icon' => 'bi-wallet-fill', 'label' => 'Payé'],
        'Rejete' => ['bg' => '#fee2e2', 'text' => '#b91c1c', 'icon' => 'bi-x-circle-fill', 'label' => 'Rejeté'],
        'Attente_Manager' => ['bg' => '#fef3c7', 'text' => '#b45309', 'icon' => 'bi-hourglass-split', 'label' => 'Manager'],
        'Attente_Admin' => ['bg' => '#ffedd5', 'text' => '#c2410c', 'icon' => 'bi-building', 'label' => 'Finance'],
        'Brouillon' => ['bg' => '#f1f5f9', 'text' => '#475569', 'icon' => 'bi-pencil', 'label' => 'Brouillon']
    ];
    $s = $styles[$status] ?? $styles['Brouillon'];
    return sprintf('<span class="status-pill" style="background:%s; color:%s"><i class="bi %s me-1"></i>%s</span>', $s['bg'], $s['text'], $s['icon'], $s['label']);
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Dashboard | Rembourse Maroc</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/dark-theme.css">
    <?php
    $currentTheme = $_SESSION['theme'] ?? $_COOKIE['app_theme'] ?? 'light';
    ?>
    <script>
        // Appliquer le thème immédiatement pour éviter le flash
        (function() {
            const theme = '<?= $currentTheme ?>';
            if (theme === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>

    <style>
        :root {
            --app-bg: #f8fafc;
            --header-bg: #ffffff;
            --header-border: #e2e8f0;
            --primary: #059669;
            --primary-dark: #047857;
            --text-main: #1e293b;
            --text-light: #64748b;
            --card-border: #e2e8f0;
            --radius: 12px;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--app-bg);
            color: var(--text-main);
            font-size: 0.875rem;
            padding-top: 70px;
            /* Plus d'espace pour l'effet 3D */
            overflow-x: hidden;
        }

        /* --- HEADER & LOGO ANIMÉ --- */
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

            0%,
            100% {
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
            background-color: #ff0000;
        }

        .app-header .d-flex span {
            font-weight: 700 !important;
        }

        .brand-text-wrapper {
            overflow: hidden;
            width: 0;
            opacity: 0;
            white-space: nowrap;
            animation: slideTextOut 0.8s cubic-bezier(0.25, 1, 0.5, 1) forwards;
            animation-delay: 0.3s;
        }

        .brand-text {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--text-main);
            letter-spacing: -0.5px;
        }

        @keyframes slideTextOut {
            to {
                width: 160px;
                opacity: 1;
            }
        }

        /* --- NAVIGATION --- */
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
            background: linear-gradient(to bottom, transparent 90%, rgba(5, 150, 105, 0.1));
        }

        /* --- PROFILE --- */
        .user-area {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .avatar-circle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }

        .avatar-circle:hover {
            transform: scale(1.1);
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

        /* --- WIDGETS --- */
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 24px;
        }

        .card-widget {
            background: white;
            border: none;
            border-radius: var(--radius);
            padding: 24px;
            height: 100%;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            position: relative;
            overflow: hidden;
        }

        .card-widget:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 10px 10px -5px rgba(0, 0, 0, 0.02);
        }

        .kpi-value {
            font-size: 1.8rem;
            font-weight: 800;
            letter-spacing: -1px;
        }

        .kpi-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            transition: transform 0.3s;
        }

        .card-widget:hover .kpi-icon {
            transform: rotate(10deg) scale(1.1);
        }

        /* --- TABLEAU 3D ANIMÉ --- */
        .table-3d-container {
            background: transparent;
        }

        .table-3d {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 12px;
            /* Espace entre les lignes pour l'effet flottant */
        }

        .table-3d thead th {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-light);
            font-weight: 700;
            padding: 0 20px 8px 20px;
            border: none;
            background: transparent;
        }

        .table-3d tbody tr {
            background: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02), 0 1px 2px rgba(0, 0, 0, 0.03);
            border-radius: 12px;
            /* Arrondi des lignes */
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            animation: fadeInRow 0.5s ease-out forwards;
            opacity: 0;
            transform: translateY(20px);
        }

        /* Délai d'animation pour chaque ligne */
        .table-3d tbody tr:nth-child(1) {
            animation-delay: 0.1s;
        }

        .table-3d tbody tr:nth-child(2) {
            animation-delay: 0.2s;
        }

        .table-3d tbody tr:nth-child(3) {
            animation-delay: 0.3s;
        }

        .table-3d tbody tr:nth-child(4) {
            animation-delay: 0.4s;
        }

        .table-3d tbody tr:nth-child(5) {
            animation-delay: 0.5s;
        }

        @keyframes fadeInRow {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .table-3d tbody tr:hover {
            transform: translateY(-4px) scale(1.005);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.08), 0 5px 15px rgba(0, 0, 0, 0.04);
            z-index: 10;
        }

        /* Coins arrondis pour les cellules de début et fin */
        .table-3d td {
            padding: 20px;
            border: none;
            vertical-align: middle;
        }

        .table-3d td:first-child {
            border-top-left-radius: 12px;
            border-bottom-left-radius: 12px;
        }

        .table-3d td:last-child {
            border-top-right-radius: 12px;
            border-bottom-right-radius: 12px;
        }

        .btn-action-main {
            background: var(--primary);
            color: white;
            padding: 10px 20px;
            border-radius: 30px;
            font-weight: 600;
            text-decoration: none;
            box-shadow: 0 4px 6px rgba(5, 150, 105, 0.2);
            transition: all 0.2s;
        }

        .btn-action-main:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 12px rgba(5, 150, 105, 0.3);
            color: white;
        }

        .btn-icon-circle {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            background: #f8fafc;
            color: var(--text-light);
            text-decoration: none;
        }

        .btn-icon-circle:hover {
            background: var(--primary);
            color: white;
            transform: rotate(15deg);
        }

        .btn-icon-circle.danger:hover {
            background: #ef4444;
            color: white;
        }

        .status-pill {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>

<body>

    <!-- TOP NAVIGATION -->
    <header class="app-header">
        <div class="d-flex align-items-center gap-2">
            <div class="brand-logo">RM</div> <span class="fw-bold text-dark">RembourseMaroc</span>
        </div>

        <nav class="app-nav">
            <a href="dashboard.php" class="nav-item-link active"><i class="bi bi-grid-fill"></i> Tableau de bord</a>
            <a href="nouvelle_demande.php" class="nav-item-link"><i class="bi bi-plus-circle"></i> Nouvelle demande</a>
            <a href="mes_frais.php" class="nav-item-link"><i class="bi bi-receipt"></i> Mes frais</a>
            <a href="mes_brouillons.php" class="nav-item-link"><i class="bi bi-file-earmark"></i> Brouillons</a>
            <a href="mes_reclamations.php" class="nav-item-link"><i class="bi bi-life-preserver"></i> Support</a>
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

    <div class="main-container">

        <!-- HEADER SECTION -->
        <div class="d-flex justify-content-between align-items-end mb-5">
            <div>
                <h3 class="fw-bolder m-0 text-dark" style="letter-spacing: -0.5px;">Bonjour,
                    <?= explode(' ', $user_name)[0] ?> </h3>
                <div class="text-muted mt-1">Voici ce qui se passe avec vos dépenses aujourd'hui.</div>
            </div>
            <a href="nouvelle_demande.php" class="btn-action-main">
                <i class="bi bi-plus-lg me-2"></i>Déclarer des frais
            </a>
        </div>

        <!-- KPI CARDS -->
        <div class="row g-4 mb-5">
            <div class="col-xl-3 col-md-6">
                <div class="card-widget">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-uppercase fw-bold text-muted small mb-2">À recevoir</div>
                            <div class="kpi-value text-primary"><?= number_format($kpi_pending_payment, 0, ',', ' ') ?>
                                <small class="fs-6 text-muted fw-normal">DH</small>
                            </div>
                        </div>
                        <div class="kpi-icon" style="background: #e0f2fe; color: #0284c7;"><i class="bi bi-wallet2"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card-widget">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-uppercase fw-bold text-muted small mb-2">Dépenses (Mois)</div>
                            <div class="kpi-value text-dark"><?= number_format($kpi_month_spent, 0, ',', ' ') ?> <small
                                    class="fs-6 text-muted fw-normal">DH</small></div>
                        </div>
                        <div class="kpi-icon" style="background: #f1f5f9; color: #475569;"><i
                                class="bi bi-calendar-check"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card-widget">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-uppercase fw-bold text-muted small mb-2">Dossiers Validés</div>
                            <div class="kpi-value text-success"><?= $kpi_validated ?></div>
                        </div>
                        <div class="kpi-icon" style="background: #dcfce7; color: #16a34a;"><i
                                class="bi bi-check-lg"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card-widget">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-uppercase fw-bold text-muted small mb-2">Dossiers Rejetés</div>
                            <div class="kpi-value text-danger"><?= $kpi_rejected ?></div>
                        </div>
                        <div class="kpi-icon" style="background: #fee2e2; color: #dc2626;"><i class="bi bi-x-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CHARTS SECTION -->
        <div class="row g-4 mb-5">
            <div class="col-lg-8">
                <div class="card-widget">
                    <h6 class="fw-bold mb-4">Évolution des remboursements</h6>
                    <div style="height: 300px; width: 100%;">
                        <canvas id="barChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card-widget">
                    <h6 class="fw-bold mb-4">Répartition par catégorie</h6>
                    <div style="height: 300px; display: flex; justify-content: center;">
                        <canvas id="doughnutChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- TABLEAU RÉCENT (STYLE 3D) -->
        <div class="mb-5">
            <div class="d-flex justify-content-between align-items-center mb-3 px-2">
                <h5 class="fw-bold m-0">Opérations Récentes</h5>
                <a href="mes_frais.php" class="text-decoration-none fw-bold small text-primary">Tout voir <i
                        class="bi bi-arrow-right ms-1"></i></a>
            </div>

            <div class="table-responsive table-3d-container">
                <table class="table-3d">
                    <thead>
                        <tr>
                            <th>Date & Réf.</th>
                            <th>Intitulé de la mission</th>
                            <th>Montant Total</th>
                            <th>État actuel</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_demandes)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <div class="py-4">
                                        <i class="bi bi-inbox fs-1 text-light mb-3 d-block"
                                            style="color: #cbd5e1 !important;"></i>
                                        <span class="text-muted fw-medium">Aucune activité récente.</span>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_demandes as $d): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark"><?= date('d/m/Y', strtotime($d['date_dep'])) ?></div>
                                        <div class="small text-muted font-monospace mt-1">
                                            #<?= str_pad($d['id_dem'], 4, '0', STR_PAD_LEFT) ?></div>
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-dark"><?= htmlspecialchars($d['titre_dem']) ?></span>
                                    </td>
                                    <td>
                                        <span class="fw-bold fs-6 text-dark"><?= number_format($d['montant_total'], 2) ?></span>
                                        <span class="text-muted small">DH</span>
                                    </td>
                                    <td>
                                        <?= getStatusBadge($d['status']) ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="details_demande.php?id=<?= $d['id_dem'] ?>" class="btn-icon-circle shadow-sm"
                                            title="Voir les détails">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                        <?php if (in_array($d['status'], ['Brouillon', 'Rejete'])): ?>
                                            <a href="../../actions/delete_demande.php?id=<?= $d['id_dem'] ?>&source=dashboard"
                                                class="btn-icon-circle danger shadow-sm ms-1"
                                                onclick="return confirm('Supprimer définitivement ?');" title="Supprimer">
                                                <i class="bi bi-trash"></i>
                                            </a>
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

    <!-- SCRIPTS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        // Configuration Globale Chart.js
        Chart.defaults.font.family = "'Inter', sans-serif";

        // Détecter le mode sombre
        const isDarkMode = document.documentElement.getAttribute('data-theme') === 'dark';
        Chart.defaults.color = isDarkMode ? '#94a3b8' : '#94a3b8';

        // Bar Chart
        const ctxBar = document.getElementById('barChart').getContext('2d');
        const gradient = ctxBar.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, '#059669');
        gradient.addColorStop(1, 'rgba(5, 150, 105, 0.1)');

        new Chart(ctxBar, {
            type: 'bar',
            data: {
                labels: <?= $monthLabels ?>,
                datasets: [{
                    label: 'Montant (DH)',
                    data: <?= $monthValues ?>,
                    backgroundColor: gradient,
                    borderRadius: 6,
                    barPercentage: 0.5,
                    hoverBackgroundColor: '#047857'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                weight: '500'
                            }
                        }
                    },
                    y: {
                        border: {
                            dash: [4, 4]
                        },
                        grid: {
                            color: isDarkMode ? 'transparent' : '#f1f5f9',
                            borderDash: [5, 5]
                        },
                        beginAtZero: true
                    }
                },
                animation: {
                    duration: 2000,
                    easing: 'easeOutQuart'
                }
            }
        });

        // Doughnut Chart
        const ctxPie = document.getElementById('doughnutChart').getContext('2d');
        const doughnutBorderColor = isDarkMode ? '#1e293b' : '#ffffff';
        const doughnutLegendColor = isDarkMode ? '#f1f5f9' : '#1e293b';

        new Chart(ctxPie, {
            type: 'doughnut',
            data: {
                labels: <?= $categLabels ?>,
                datasets: [{
                    data: <?= $categValues ?>,
                    backgroundColor: ['#059669', '#3b82f6', '#f59e0b', '#8b5cf6', '#ef4444'],
                    borderWidth: 5,
                    borderColor: doughnutBorderColor,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 20,
                            font: {
                                size: 11
                            },
                            color: doughnutLegendColor
                        }
                    }
                },
                animation: {
                    animateScale: true,
                    animateRotate: true
                }
            }
        });
    </script>
    <script src="../../assets/js/theme.js"></script>
</body>

</html>