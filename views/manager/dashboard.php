<?php
// -------------------------------------------------------------------------
// DASHBOARD MANAGER - VERSION AVANCÉE (EVOLUTION & URGENCES)
// -------------------------------------------------------------------------

session_start();
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../config/Lang.php';

Lang::init();
// Sécurité : Seul le manager passe
protect_page('manager');

$pdo = Database::getInstance()->getConnexion();
$managerId = $_SESSION['user_id'];
$userName = $_SESSION['user']['nom'] ?? 'Manager';

// Avatar
$stmt = $pdo->prepare("SELECT avatar FROM users WHERE user_id = ?");
$stmt->execute([$managerId]);
$userParams = $stmt->fetch();
$avatarFile = $userParams['avatar'] ?? 'default.png'; 
$avatar = '../../assets/img/' . $avatarFile;

// =========================================================================
// CALCUL DES KPI AVANCÉS
// =========================================================================

// --- A. DEMANDES "URGENTES" (RISQUE DE DÉPASSER 20 JOURS) ---
// Demandes qui risquent de dépasser 20 jours depuis leur dépôt (date_dep)
$sqlUrgent = "SELECT COUNT(*) FROM demande d 
              JOIN users u ON d.user_id = u.user_id 
              WHERE u.manager_id = ? 
              AND d.status = 'Attente_Manager'
               AND DATEDIFF(NOW(), d.date_dep) > 10"; // Plus de 20 jours depuis le dépôt
$stmt = $pdo->prepare($sqlUrgent);
$stmt->execute([$managerId]);
$kpi_urgent = $stmt->fetchColumn();


// --- B. DEMANDES "À VALIDER" (TOTAL) ---
$sqlPending = "SELECT COUNT(*) FROM demande d 
               JOIN users u ON d.user_id = u.user_id 
               WHERE u.manager_id = ? AND d.status = 'Attente_Manager'";
$stmt = $pdo->prepare($sqlPending);
$stmt->execute([$managerId]);
$kpi_attente = $stmt->fetchColumn();

// --- B.1. DEMANDES "À VALIDER" CE MOIS ---
$sqlPendingMonth = "SELECT COUNT(*) FROM demande d 
                    JOIN users u ON d.user_id = u.user_id 
                    WHERE u.manager_id = ? AND d.status = 'Valide'
                    AND MONTH(d.date_dep) = MONTH(CURDATE())
                    AND YEAR(d.date_dep) = YEAR(CURDATE())";
$stmt = $pdo->prepare($sqlPendingMonth);
$stmt->execute([$managerId]);
$kpi_attente_mois = $stmt->fetchColumn();


// --- C. BUDGET GLOBAL ---
$sqlBudget = "SELECT budget_annuel, budget_consomme FROM teams WHERE manager_id = ?";
$stmt = $pdo->prepare($sqlBudget);
$stmt->execute([$managerId]);
$teamData = $stmt->fetch();

$budget_total = $teamData['budget_annuel'] ?? 0;
$budget_conso = $teamData['budget_consomme'] ?? 0;
$budget_restant = $budget_total - $budget_conso;
$budget_percent = ($budget_total > 0) ? round(($budget_conso / $budget_total) * 100, 1) : 0;


// --- D. ÉVOLUTION DES DÉPENSES (MENSUELLE) ---
// Comparaison : Ce mois vs Mois dernier (basé sur date_dep)
// UNIQUEMENT les demandes VALIDÉES par le manager (status = 'Valide')

// Total Ce Mois (mois en cours) - UNIQUEMENT VALIDÉES
$sqlThisMonth = "SELECT COALESCE(SUM(montant_total), 0) FROM demande d 
                 JOIN users u ON d.user_id = u.user_id 
                 WHERE u.manager_id = ? AND d.status = 'Valide' 
                 AND MONTH(d.date_dep) = MONTH(CURDATE())
                 AND YEAR(d.date_dep) = YEAR(CURDATE())";
$stmt = $pdo->prepare($sqlThisMonth);
$stmt->execute([$managerId]);
$amount_this_month = $stmt->fetchColumn();

// Total Mois Dernier - UNIQUEMENT VALIDÉES
$sqlLastMonth = "SELECT COALESCE(SUM(montant_total), 0) FROM demande d 
                 JOIN users u ON d.user_id = u.user_id 
                 WHERE u.manager_id = ? AND d.status = 'Valide' 
                 AND MONTH(d.date_dep) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
                 AND YEAR(d.date_dep) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
$stmt = $pdo->prepare($sqlLastMonth);
$stmt->execute([$managerId]);
$amount_last_month = $stmt->fetchColumn();

// Calculs Evolution
$difference = $amount_this_month - $amount_last_month;
$evolution_percent = 0;
$evolution_class = "text-muted";
$evolution_icon = "bi-dash";

// Calcul du pourcentage : seulement si le mois dernier > 0
if ($amount_last_month > 0) {
    $evolution_percent = round(($difference / $amount_last_month) * 100, 1);
} elseif ($amount_last_month == 0 && $amount_this_month > 0) {
    // Si mois dernier = 0 et mois actuel > 0, on indique "Nouveau" ou on calcule par rapport à ce mois
    $evolution_percent = 100; // Augmentation de 100% (de 0 à X)
} else {
    // Les deux sont à 0
    $evolution_percent = 0;
}

// Limiter le pourcentage à un maximum raisonnable (999% max)
if ($evolution_percent > 999) {
    $evolution_percent = 999;
}
if ($evolution_percent < -999) {
    $evolution_percent = -999;
}

if ($difference > 0) {
    $evolution_class = "text-danger";
    $evolution_icon = "bi-arrow-up-right";
    $evolution_text = "+" . $evolution_percent . "%";
    $diff_text = "+" . number_format($difference, 0) . " DH";
} elseif ($difference < 0) {
    $evolution_class = "text-success";
    $evolution_icon = "bi-arrow-down-right";
    $evolution_text = $evolution_percent . "%";
    $diff_text = number_format($difference, 0) . " DH";
} else {
    $evolution_text = "0%";
    $diff_text = "Stable";
}

// =========================================================================
// DÉTERMINATION DES COULEURS EXPRESSIVES POUR LES 4 KPI
// =========================================================================

// KPI 1 : Alertes Critique - Toujours en rouge
$kpi1_color_class = 'text-danger';
$kpi1_color = '#ef4444';

// KPI 2 : À Valider - Rouge si > 5, Orange si > 0, Vert si = 0
if ($kpi_attente > 5) {
    $kpi2_color_class = 'text-danger';
    $kpi2_color = '#ef4444';
} elseif ($kpi_attente > 0) {
    $kpi2_color_class = 'text-warning';
    $kpi2_color = '#f59e0b';
} else {
    $kpi2_color_class = 'text-success';
    $kpi2_color = '#10b981';
}

// KPI 3 : Évolution - Toujours en vert
$kpi3_color_class = 'text-success';
$kpi3_color = '#10b981';

// KPI 4 : Budget - Toujours en rouge/grenat
$kpi4_color_class = 'text-danger';
$kpi4_color = '#dc2626';

// =========================================================================
// GRAPHIQUES & LISTES
// =========================================================================

// Graphe 1 : Répartition par catég
$catLabels = []; $catValues = [];
try {
    $sqlCat = "SELECT c.nom_categ, SUM(el.montant) as total 
               FROM expense_line el
               JOIN demande d ON el.id_dem = d.id_dem
               JOIN categories c ON el.id_categ = c.id_categ
               JOIN users u ON d.user_id = u.user_id
                WHERE u.manager_id = ? AND d.status = 'Valide'
                GROUP BY c.nom_categ";
    $stmt = $pdo->prepare($sqlCat);
    $stmt->execute([$managerId]);
    $catData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $catLabels = json_encode(array_column($catData, 'nom_categ'));
    // S'assurer que les valeurs sont numériques
    $catValues = json_encode(array_map(function($item) {
        return floatval($item['total']);
    }, $catData));
} catch (Exception $e) { $catLabels = json_encode([]); $catValues = json_encode([]); }

// Graphique : Dépenses par mois (12 derniers mois) - basé sur date_dep
$sqlMonthly = "SELECT 
                   DATE_FORMAT(d.date_dep, '%Y-%m') as month_key,
                   DATE_FORMAT(d.date_dep, '%b %Y') as label, 
                   SUM(d.montant_total) as total
               FROM demande d 
               JOIN users u ON d.user_id = u.user_id
               WHERE u.manager_id = ? AND d.status != 'Rejete'
               AND d.date_dep >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
               GROUP BY DATE_FORMAT(d.date_dep, '%Y-%m'), DATE_FORMAT(d.date_dep, '%b %Y')
               ORDER BY DATE_FORMAT(d.date_dep, '%Y-%m') ASC";
$stmt = $pdo->prepare($sqlMonthly);
$stmt->execute([$managerId]);
$monthlyData = $stmt->fetchAll(PDO::FETCH_ASSOC);
$monthlyLabels = json_encode(array_column($monthlyData, 'label'));
$monthlyValues = json_encode(array_column($monthlyData, 'total'));

// Top 5 Dépenseurs
$sqlTop = "SELECT u.nom, SUM(d.montant_total) as total
           FROM demande d JOIN users u ON d.user_id = u.user_id
           WHERE u.manager_id = ? AND d.status != 'Rejete'
           GROUP BY u.user_id ORDER BY total DESC LIMIT 5";
$stmt = $pdo->prepare($sqlTop);
$stmt->execute([$managerId]);
$topData = $stmt->fetchAll(PDO::FETCH_ASSOC);
$topLabels = json_encode(array_column($topData, 'nom'));
$topValues = json_encode(array_column($topData, 'total'));

// LISTE DES 6 DEMANDES LES PLUS RÉCENTES (tous statuts)
$sqlList = "SELECT d.id_dem, d.titre_dem, d.date_dep, d.montant_total, d.status, d.created_at,
                   u.nom as user_name, u.avatar 
            FROM demande d 
            JOIN users u ON d.user_id = u.user_id 
            WHERE u.manager_id = ?
            ORDER BY d.created_at DESC
            LIMIT 6";
$stmt = $pdo->prepare($sqlList);
$stmt->execute([$managerId]);
$recentList = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =========================================================================
// NOTIFICATIONS
// =========================================================================
// Récupérer les réclamations depuis la table reclamations existante
// Les réclamations sont liées aux demandes des employés de l'équipe
// Exclure celles déjà vues par ce manager
try {
    // Créer la table de suivi si elle n'existe pas
    $pdo->exec("CREATE TABLE IF NOT EXISTS reclamation_views (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reclamation_id INT NOT NULL,
        manager_id INT NOT NULL,
        viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_view (reclamation_id, manager_id),
        FOREIGN KEY (manager_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $sqlReclamations = "SELECT r.id_reclam, r.id_dem, r.message, r.statut, r.created_at,
                               d.titre_dem, d.montant_total,
                               u.nom as employee_name, u.user_id as employee_id
                        FROM reclamations r
                        JOIN demande d ON r.id_dem = d.id_dem
                        JOIN users u ON d.user_id = u.user_id
                        LEFT JOIN reclamation_views rv ON r.id_reclam = rv.reclamation_id AND rv.manager_id = ?
                        WHERE u.manager_id = ?
                        AND r.statut != 'resolu'
                        AND rv.id IS NULL
                        ORDER BY r.created_at DESC
                        LIMIT 10";
    $stmt = $pdo->prepare($sqlReclamations);
    $stmt->execute([$managerId, $managerId]);
    $reclamations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Si la table n'existe pas ou erreur, on initialise avec un tableau vide
    $reclamations = [];
}

// Créer la table notifications pour les clarifications et autres types si elle n'existe pas
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type ENUM('clarification', 'validation', 'rejet') NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        related_id INT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    )");
} catch (PDOException $e) {
    // Table existe déjà ou erreur
}

// Récupérer les autres notifications (clarifications, validations, rejets)
// UNIQUEMENT pour les membres de l'équipe, PAS pour le manager lui-même
try {
    $sqlNotifications = "SELECT n.*, u.nom as from_user_name
                       FROM notifications n
                       LEFT JOIN users u ON n.user_id = u.user_id
                       WHERE u.manager_id = ?
                       AND n.user_id != ?
                       AND n.type NOT IN ('validation', 'rejet')
                       AND n.is_read = 0
                       ORDER BY n.created_at DESC
                       LIMIT 10";
    $stmt = $pdo->prepare($sqlNotifications);
    $stmt->execute([$managerId, $managerId]);
    $otherNotifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $otherNotifications = [];
}

// Combiner les réclamations et les autres notifications
$allNotifications = [];
foreach ($reclamations as $rec) {
    $allNotifications[] = [
        'id' => $rec['id_reclam'],
        'type' => 'reclamation',
        'title' => 'Réclamation - ' . htmlspecialchars($rec['titre_dem']),
        'message' => htmlspecialchars($rec['message']),
        'created_at' => $rec['created_at'],
        'employee_name' => $rec['employee_name'],
        'id_dem' => $rec['id_dem'],
        'statut' => $rec['statut']
    ];
}
foreach ($otherNotifications as $notif) {
    $allNotifications[] = [
        'id' => $notif['id'],
        'type' => $notif['type'],
        'title' => $notif['title'],
        'message' => $notif['message'],
        'created_at' => $notif['created_at'],
        'from_user_name' => $notif['from_user_name'] ?? null
    ];
}

// Trier par date décroissante
usort($allNotifications, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

$notifications = array_slice($allNotifications, 0, 10);
$notificationCount = count($notifications);

function getStatusBadge($status) {
    $badges = [
        'Brouillon' => '<span class="badge bg-secondary">Brouillon</span>',
        'Attente_Manager' => '<span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>À Valider</span>',
        'Attente_Admin' => '<span class="badge" style="background-color: #dcfce7; color: #166534;"><i class="bi bi-clock me-1"></i>En attente Admin</span>',
        'Valide' => '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Validé</span>',
        'Rejete' => '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Rejeté</span>',
        'Paye' => '<span class="badge bg-primary"><i class="bi bi-wallet me-1"></i>Payé</span>'
    ];
    return $badges[$status] ?? '<span class="badge bg-secondary">' . htmlspecialchars($status) . '</span>';
}
?>

<!DOCTYPE html>
<html lang="<?= Lang::current() ?>">
<head>
    <meta charset="UTF-8">
    <title><?= Lang::get('dashboard.title', 'Tableau de bord') ?> | Rembourse Maroc</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
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
        .logo-rm {
            width: 32px;
            height: 32px;
            background: #059669;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 14px;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2), 0 0 0 1px rgba(255, 255, 255, 0.1) inset;
        }
        .stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08), 0 1px 3px rgba(0, 0, 0, 0.05);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(0, 0, 0, 0.06);
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
           /* background: linear-gradient(90deg, #059669, #10b981);*/
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .stat-card::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
           /* background: radial-gradient(circle, rgba(5, 150, 105, 0.1) 0%, transparent 70%);*/
            opacity: 0;
            transition: opacity 0.4s ease;
        }
        .stat-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12), 0 8px 16px rgba(0, 0, 0, 0.08);
            border-color: rgba(5, 150, 105, 0.2);
        }
        .stat-card:hover::before {
            transform: scaleX(1);
        }
        .stat-card:hover::after {
            opacity: 1;
        }
         /*Carte KPI 1 - Alertes Délais (Rouge) */
        /*.stat-card:first-child::before {
            background: linear-gradient(90deg, #059669, #10b981);
        }*/
        .stat-card:first-child:hover {
            border-color: rgba(18, 114, 23, 0.11);
            box-shadow: 0 20px 40px rgba(26, 133, 42, 0.06), 0 8px 16px rgba(239, 68, 68, 0);
        }
        .stat-card:first-child::after {
            background: radial-gradient(circle, rgba(88, 239, 68, 0) 0%, transparent 70%);
        }
        /* Carte KPI 2 - À Valider (Orange) */
        .stat-card:nth-child(2)::before {
            background: linear-gradient(90deg, #f59e0b, #fbbf24);
        }
        .stat-card:nth-child(2):hover {
            border-color: rgba(245, 158, 11, 0.3);
            box-shadow: 0 20px 40px rgba(245, 158, 11, 0.15), 0 8px 16px rgba(245, 158, 11, 0.1);
        }
        .stat-card:nth-child(2)::after {
            background: radial-gradient(circle, rgba(245, 158, 11, 0.1) 0%, transparent 70%);
        }
        /* Carte KPI 3 - Évolution (Dynamique selon évolution) */
        .stat-card:nth-child(3)::before {
            background: linear-gradient(90deg, #3b82f6, #60a5fa);
        }
        .stat-card:nth-child(3):hover {
            border-color: rgba(59, 130, 246, 0.3);
            box-shadow: 0 20px 40px rgba(59, 130, 246, 0.15), 0 8px 16px rgba(59, 130, 246, 0.1);
        }
        .stat-card:nth-child(3)::after {
            background: radial-gradient(circle, rgba(59, 130, 246, 0.1) 0%, transparent 70%);
        }
        /* Carte KPI 4 - Budget (Rouge/Grenat) */
        .stat-card:nth-child(4)::before {
            background: linear-gradient(90deg, #991b1b, #dc2626);
        }
        .stat-card:nth-child(4):hover {
            border-color: rgba(220, 38, 38, 0.3);
            box-shadow: 0 20px 40px rgba(220, 38, 38, 0.15), 0 8px 16px rgba(220, 38, 38, 0.1);
        }
        .stat-card:nth-child(4)::after {
            background: radial-gradient(circle, rgba(220, 38, 38, 0.1) 0%, transparent 70%);
        }
        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -1px;
            position: relative;
            z-index: 1;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        /* Couleurs expressives pour chaque KPI */
        .stat-value.text-danger {
            color: #ef4444 !important;
        }
        .stat-value.text-warning {
            color: #f59e0b !important;
        }
        .stat-value.text-success {
            color: #10b981 !important;
        }
        .stat-value.text-primary {
            color: #3b82f6 !important;
        }
        .stat-value.text-success {
            color: #10b981 !important;
        }
        .stat-value.text-muted {
            color: #6b7280 !important;
        }
        /* Icônes dans les valeurs KPI */
        .stat-value.text-danger i,
        .stat-value.text-danger .bi {
            color: #ef4444 !important;
        }
        .stat-value.text-warning i,
        .stat-value.text-warning .bi {
            color: #f59e0b !important;
        }
        .stat-value.text-success i,
        .stat-value.text-success .bi {
            color: #10b981 !important;
        }
        .stat-value.text-primary i,
        .stat-value.text-primary .bi {
            color: #3b82f6 !important;
        }
        .stat-value.text-success i,
        .stat-value.text-success .bi {
            color: #10b981 !important;
        }
        .stat-value.text-muted i,
        .stat-value.text-muted .bi {
            color: #6b7280 !important;
        }
        .stat-card:hover .stat-value {
            transform: scale(1.05);
        }
        .stat-card .text-muted.small.mb-2 {
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.75rem;
            color: #64748b;
            position: relative;
            z-index: 1;
        }
        .stat-card .text-muted.small.mt-2,
        .stat-card .progress.mt-2 {
            opacity: 0;
            max-height: 0;
            overflow: hidden;
            transition: opacity 0.4s ease, max-height 0.4s ease, margin 0.4s ease;
            margin-top: 0 !important;
            position: relative;
            z-index: 1;
        }
        .stat-card:hover .text-muted.small.mt-2,
        .stat-card:hover .progress.mt-2 {
            opacity: 1;
            max-height: 100px;
            margin-top: 0.75rem !important;
        }
        .stat-card .progress {
            border-radius: 10px;
            background: rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        .stat-card .progress-bar {
            border-radius: 10px;
            transition: width 1s ease-in-out;
            background: linear-gradient(90deg, #059669, #10b981);
            box-shadow: 0 2px 8px rgba(5, 150, 105, 0.3);
        }
    </style>
</head>
<body >

    <header class="app-header" style="background-color: #059669;">
        <div class="d-flex align-items-center">
            <div class="brand-logo">
                <div class="logo-rm">RM</div> RembourseMaroc
            </div>
        </div>
        <nav class="app-nav">
            <a href="dashboard.php" class="nav-link active"><i class="bi bi-speedometer2"></i> Tableau de bord</a>
            <a href="validation.php" class="nav-link"><i class="bi bi-check-circle"></i> Validation</a>
            <a href="deplacements.php" class="nav-link"><i class="bi bi-airplane"></i> Déplacements</a>
            <a href="equipe.php" class="nav-link"><i class="bi bi-people"></i> Mon Équipe</a>
            <a href="historique.php" class="nav-link"><i class="bi bi-clock-history"></i> Historique</a>
        </nav>
        <div class="d-flex align-items-center gap-3">
            <!-- Barre de notifications -->
            <div class="dropdown position-relative">
                <a href="#" class="notification-bell position-relative text-decoration-none" data-bs-toggle="dropdown" id="notificationDropdown">
                    <i class="bi bi-bell fs-5"></i>
                    <span class="notification-badge" style="display: <?= $notificationCount > 0 ? 'flex' : 'none' ?>;"><?= $notificationCount ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end notification-dropdown shadow-lg border-0 mt-2" style="width: 350px; max-height: 400px; overflow-y: auto;">
                    <li class="px-3 py-2 border-bottom bg-light">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-bell me-2"></i>Notifications</h6>
                    </li>
                    <?php if (empty($notifications)): ?>
                        <li class="px-3 py-4 text-center text-muted">
                            <i class="bi bi-bell-slash fs-4 d-block mb-2"></i>
                            <small>Aucune notification</small>
                        </li>
                    <?php else: ?>
                        <?php foreach ($notifications as $notif): 
                            $iconClass = match($notif['type']) {
                                'reclamation' => 'bi-exclamation-triangle text-warning',
                                'clarification' => 'bi-question-circle',
                                'validation' => 'bi-check-circle text-success',
                                'rejet' => 'bi-x-circle text-danger',
                                default => 'bi-info-circle'
                            };
                            
                            // Afficher le nom de l'employé pour les réclamations
                            $employeeInfo = '';
                            if ($notif['type'] === 'reclamation' && isset($notif['employee_name'])) {
                                $employeeInfo = '<div class="text-primary small mt-1"><i class="bi bi-person"></i> ' . htmlspecialchars($notif['employee_name']) . '</div>';
                            }
                        ?>
                        <li class="notification-item px-3 py-2 border-bottom" style="cursor: pointer;" 
                            data-notification-id="<?= $notif['id'] ?>"
                            data-notification-type="<?= $notif['type'] ?>"
                            <?php if ($notif['type'] === 'reclamation' && isset($notif['id_dem'])): ?>
                                data-demand-id="<?= $notif['id_dem'] ?>"
                            <?php endif; ?>>
                            <div class="d-flex align-items-start">
                                <div class="notification-icon me-3">
                                    <i class="bi <?= $iconClass ?> fs-5"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold small"><?= $notif['title'] ?></div>
                                    <?= $employeeInfo ?>
                                    <div class="text-muted small mt-1"><?= $notif['message'] ?></div>
                                    <div class="text-muted" style="font-size: 0.7rem; margin-top: 4px;">
                                        <i class="bi bi-clock"></i> <?= date('d/m/Y H:i', strtotime($notif['created_at'])) ?>
                                    </div>
                                </div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <li class="px-3 py-2 border-top bg-light text-center">
                        <a href="notifications.php" class="small text-primary text-decoration-none">Voir toutes les notifications</a>
                    </li>
                </ul>
            </div>
            
            <div class="text-end d-none d-sm-block">
                <div class="fw-bold small"><?= htmlspecialchars($userName) ?></div>
                <div class="text-muted" style="font-size: 0.7rem;">Manager</div>
            </div>
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-decoration-none" data-bs-toggle="dropdown">
                    <img src="<?= htmlspecialchars($avatar) ?>" class="avatar-circle">
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2">
                    <li><a class="dropdown-item small" href="parametres.php"><i class="bi bi-gear me-2"></i>Paramètres</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item small text-danger" href="../../actions/logout.php"><i class="bi bi-power me-2"></i>Déconnexion</a></li>
                </ul>
            </div>
        </div>
    </header>

    <div class="main-container">
        
        <!-- TITRE PRINCIPAL -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold m-0 text-dark" >Tableau de bord</h4>
                <p class="text-muted small">Suivi budgétaire et opérationnel</p>
            </div>
        </div>
        
        <!-- BADGE BUDGET RESTANT AVEC ANIMATION -->
        <div id="budgetBadgeContainer" class="d-flex justify-content-end mb-4" style="position: fixed; top: 80px; right: 2rem; z-index: 999; opacity: 0; <!--transition: opacity 0.5s ease-in-out;-->">
            <span class="badge bg-white text-dark border p-3 shadow-lg fs-6 budget-badge-animated">
                <i class="bi bi-wallet2 me-2"></i>Budget Restant : <strong class="text-success"><?= number_format($budget_restant, 0) ?> DH</strong>
            </span>
        </div>

        <!-- 1. LIGNE DES KPI -->
        <div class="row g-4 mb-4">
            
            <!-- KPI 1 : ALERTES CRITIQUE (URGENT) -->
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="text-muted small mb-2">Alertes Critique</div>
                    <div class="stat-value <?= $kpi1_color_class ?>" style="color: <?= $kpi1_color ?> !important;"><?= $kpi_urgent ?></div>
                    <div class="text-muted small mt-2">> 10 jours depuis leur dépôt</div>
                </div>
            </div>

            <!-- KPI 2 : À VALIDER (FLUX NORMAL) -->
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="text-muted small mb-2">À Valider</div>
                    <div class="stat-value <?= $kpi2_color_class ?>" style="color: <?= $kpi2_color ?> !important;"><?= $kpi_attente ?></div>
                    <div class="text-muted small mt-2">En attente ce mois : <?= $kpi_attente_mois ?></div>
                </div>
            </div>

            <!-- KPI 3 : ÉVOLUTION DÉPENSES -->
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="text-muted small mb-2">Évolution (Mois)</div>
                    <div class="stat-value text-success" style="color: #10b981 !important;">
                        <?= $evolution_text ?> <i class="bi <?= $evolution_icon ?>" style="color: #10b981 !important;"></i>
                        </div>
                    <div class="text-muted small mt-2">
                        Ce mois: <strong><?= number_format($amount_this_month, 0) ?> DH</strong>
                        <span class="<?= $evolution_class ?> ms-2"><?= $diff_text ?></span>
                    </div>
                </div>
            </div>

            <!-- KPI 4 : BUDGET CONSOMMÉ -->
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="text-muted small mb-2">Conso. Budget</div>
                    <div class="stat-value <?= $kpi4_color_class ?>" style="color: <?= $kpi4_color ?> !important;"><?= $budget_percent ?>%</div>
                    <div class="progress mt-2" style="height: 6px;">
                        <div class="progress-bar" style="width: <?= $budget_percent ?>%; background-color: <?= $kpi4_color ?> !important;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. GRAPHIQUES -->
        <div class="row g-2" style="margin-bottom: 0rem;">
            <!-- Graphique 1 : Dépenses par mois (Line Chart) -->
            <div class="col-lg-8">
                <div class="card-widget p-0 overflow-hidden">
                    <div class="px-4 py-3 border-bottom bg-white d-flex justify-content-between align-items-center">
                        <span class="fw-bold text-muted small text-uppercase">Dépenses par mois</span>
                    </div>
                    <div class="p-4" style="height: 300px;">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Graphique 2 : Dépenses par catégorie (Doughnut) -->
            <div class="col-lg-4">
                <div class="card-widget p-0 overflow-hidden">
                    <div class="px-4 py-3 border-bottom bg-white">
                        <span class="fw-bold text-muted small text-uppercase">Dépenses par catégorie</span>
                    </div>
                    <div class="p-4 d-flex justify-content-center" style="height: 300px;">
                        <canvas id="doughnutChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Graphique 3 : Top dépenseurs (Bar Chart) -->
        <div class="row g-4 mb-4" style="margin-top: 1rem;">
            <div class="col-12">
                <div class="card-widget p-0 overflow-hidden">
                    <div class="px-4 py-3 border-bottom bg-white">
                        <span class="fw-bold text-muted small text-uppercase">Top Dépenseurs de l'Équipe</span>
                    </div>
                    <div class="p-4" style="height: 250px;">
                        <canvas id="topUsersChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. TABLEAU DES 6 DEMANDES LES PLUS RÉCENTES -->
        <div class="card-widget p-0">
            <div class="px-4 py-3 border-bottom bg-white d-flex justify-content-between align-items-center">
                <span class="fw-bold text-dark" style="font-size: 1.1rem;"><i class="bi bi-list-ul text-primary me-2"></i>6 Dernières Demandes Reçues</span>
                <a href="validation.php" class="text-decoration-none fw-bold text-primary" style="font-size: 1rem;">Voir tout <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">ID</th>
                            <th>Nom</th>
                            <th>Motif</th>
                            <th>Prix</th>
                            <th>Date</th>
                            <th>Statut</th>
                            <th class="text-end pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($recentList)): ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted small">Aucune demande reçue.</td></tr>
                        <?php else: ?>
                            <?php foreach($recentList as $r): ?>
                            <tr>
                                <td class="ps-4 font-monospace small text-muted">#<?= $r['id_dem'] ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if (!empty($r['avatar']) && $r['avatar'] != 'default.png'): ?>
                                            <img src="../../assets/img/<?= htmlspecialchars($r['avatar']) ?>" class="rounded-circle me-2" width="32" height="32" alt="">
                                        <?php else: ?>
                                            <div class="avatar-circle bg-light text-primary d-flex align-items-center justify-content-center me-2 small fw-bold" style="width:32px;height:32px;">
                                                <?= strtoupper(substr($r['user_name'], 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                        <span class="fw-medium"><?= htmlspecialchars($r['user_name']) ?></span>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($r['titre_dem'] ?? '—') ?></td>
                                <td class="fw-bold"><?= number_format($r['montant_total'], 2) ?> DH</td>
                                <td class="small text-muted"><?= date('d/m/Y', strtotime($r['date_dep'])) ?></td>
                                <td><?= getStatusBadge($r['status']) ?></td>
                                <td class="text-end pe-4">
                                    <a href="details_validation.php?id=<?= $r['id_dem'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> Détails
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
        // Attendre que le DOM soit chargé
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof Chart === 'undefined') {
                console.error('Chart.js is not loaded');
                return;
            }

        Chart.defaults.font.family = "'Inter', sans-serif";
            const isDarkMode = document.documentElement.getAttribute('data-theme') === 'dark';
            Chart.defaults.color = isDarkMode ? '#94a3b8' : '#64748b';

        // Graphique Dépenses par mois (Line Chart avec points)
            const ctxMonthlyEl = document.getElementById('monthlyChart');
            if (!ctxMonthlyEl) {
                console.error('Canvas monthlyChart not found');
                return;
            }
            const ctxMonthly = ctxMonthlyEl.getContext('2d');
        const gridColorMonthly = isDarkMode ? 'transparent' : '#e2e8f0';
        const borderColorMonthly = isDarkMode ? '#334155' : '#e2e8f0';
        new Chart(ctxMonthly, {
            type: 'line',
            data: {
                labels: <?= $monthlyLabels ?>,
                datasets: [{
                    label: 'Montant (DH)',
                    data: <?= $monthlyValues ?>,
                    borderColor: '#059669',
                    backgroundColor: 'rgba(5, 150, 105, 0.05)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: isDarkMode ? '#1e293b' : '#ffffff',
                    pointBorderColor: '#059669',
                    pointBorderWidth: 2,
                    pointHoverBackgroundColor: '#059669',
                    pointHoverBorderColor: isDarkMode ? '#1e293b' : '#ffffff',
                    pointHoverBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        titleFont: { size: 14, weight: '600' },
                        bodyFont: { size: 13 },
                        borderColor: '#059669',
                        borderWidth: 1,
                        cornerRadius: 8,
                        displayColors: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { display: false },
                        border: { display: false },
                        ticks: {
                            font: { size: 11 },
                            color: isDarkMode ? '#94a3b8' : '#64748b'
                        }
                    },
                    x: {
                        grid: { display: false },
                        border: { display: false },
                        ticks: {
                            font: { size: 11 },
                            color: isDarkMode ? '#94a3b8' : '#64748b'
                        }
                    }
                }
            }
        });

        // Graphique Dépenses par catégorie (Doughnut)
            const ctxDoughnutEl = document.getElementById('doughnutChart');
            if (!ctxDoughnutEl) {
                console.error('Canvas doughnutChart not found');
                return;
            }
            const ctxDoughnut = ctxDoughnutEl.getContext('2d');
        new Chart(ctxDoughnut, {
            type: 'doughnut',
            data: {
                labels: <?= $catLabels ?>,
                datasets: [{
                    data: <?= $catValues ?>,
                    backgroundColor: [
                        '#047857',  // Vert foncé
                        '#059669',  // Vert principal
                        '#10b981',  // Vert moyen
                        '#34d399',  // Vert clair
                        '#6ee7b7'   // Vert très clair
                    ],
                    borderWidth: 0,
                    hoverOffset: 8
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
                            padding: 15,
                            font: {
                                size: 12,
                                weight: '500',
                                family: "'Poppins', sans-serif"
                            },
                            color: isDarkMode ? '#94a3b8' : '#334155'
                        }
                    },
                    tooltip: {
                        backgroundColor: isDarkMode ? 'rgba(30, 41, 59, 0.95)' : 'rgba(0, 0, 0, 0.85)',
                        padding: 12,
                        titleFont: { 
                            size: 13, 
                            weight: '600',
                            family: "'Poppins', sans-serif"
                        },
                        bodyFont: { 
                            size: 12,
                            family: "'Poppins', sans-serif"
                        },
                        titleColor: isDarkMode ? '#f1f5f9' : '#ffffff',
                        bodyColor: isDarkMode ? '#f1f5f9' : '#ffffff',
                        borderColor: '#059669',
                        borderWidth: 1,
                        cornerRadius: 8,
                        displayColors: true,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                
                                // Pour les graphiques doughnut, récupérer la valeur directement depuis le dataset
                                const dataIndex = context.dataIndex;
                                const dataset = context.dataset.data;
                                
                                // Récupérer la valeur actuelle
                                let value = 0;
                                if (dataIndex !== undefined && dataset && dataset[dataIndex] !== undefined) {
                                    value = typeof dataset[dataIndex] === 'number' 
                                        ? dataset[dataIndex] 
                                        : parseFloat(dataset[dataIndex]) || 0;
                                } else if (typeof context.parsed === 'number') {
                                    value = context.parsed;
                                } else if (context.raw !== undefined) {
                                    value = typeof context.raw === 'number' ? context.raw : parseFloat(context.raw) || 0;
                                }
                                
                                // Calculer le total de toutes les valeurs du dataset
                                const total = dataset.reduce((sum, val) => {
                                    const numVal = typeof val === 'number' ? val : parseFloat(val) || 0;
                                    return sum + numVal;
                                }, 0);
                                
                                // Calculer le pourcentage
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';
                                label += context.formattedValue + ' DH (' + percentage + '%)';
                                return label;
                            }
                        }
                    }
                }
            }
        });

        // Graphique Top Dépenseurs (Bar Chart)
            const ctxTopEl = document.getElementById('topUsersChart');
            if (!ctxTopEl) {
                console.error('Canvas topUsersChart not found');
            } else {
                const ctxTop = ctxTopEl.getContext('2d');
                const gridColor = isDarkMode ? 'transparent' : '#f1f5f9';
                const textColor = isDarkMode ? '#94a3b8' : '#64748b';
                const barColor = isDarkMode ? '#059669' : '#0f172a';
            
        new Chart(ctxTop, {
            type: 'bar',
            data: {
                labels: <?= $topLabels ?>,
                datasets: [{
                    label: 'Total Dépensé (DH)',
                    data: <?= $topValues ?>,
                    backgroundColor: barColor,
                    borderRadius: 4,
                    barPercentage: 0.5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y', // Barres horizontales
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: { display: false },
                        border: { display: false },
                        ticks: { color: textColor }
                    },
                    y: {
                        grid: { display: false },
                        ticks: { color: textColor }
                    }
                }
            }
        });
            }

        // Gestion des notifications - Marquer comme lue au clic
        document.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', async function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const notificationId = this.getAttribute('data-notification-id');
                const notificationType = this.getAttribute('data-notification-type');
                const demandId = this.getAttribute('data-demand-id');
                
                // Marquer la notification comme lue
                if (notificationId && notificationType) {
                    try {
                        const response = await fetch('../../actions/mark_notification_read.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                id: notificationId,
                                type: notificationType
                            })
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            // Retirer la notification de la liste visuellement
                            this.style.opacity = '0.5';
                            this.style.pointerEvents = 'none';
                            
                            // Retirer la notification de la liste après un court délai
                            setTimeout(() => {
                                this.remove();
                            updateNotificationBadge();
                            }, 300);
                            
                            // Mettre à jour le badge immédiatement
                            updateNotificationBadge();
                        } else {
                            console.error('Erreur:', result.message);
                        }
                    } catch (error) {
                        console.error('Erreur lors de la mise à jour de la notification:', error);
                    }
                }
                
                // Rediriger si c'est une réclamation avec une demande
                if (demandId) {
                    setTimeout(() => {
                        window.location.href = 'details_validation.php?id=' + demandId;
                    }, 500);
                }
            });
        });

        // Fonction pour mettre à jour le badge de notification
        function updateNotificationBadge() {
            const notificationItems = document.querySelectorAll('.notification-item:not([style*="opacity: 0.5"]):not([style*="pointer-events: none"])');
            const badge = document.querySelector('.notification-badge');
            const count = notificationItems.length;
            
            if (badge) {
                if (count > 0) {
                    badge.textContent = count;
                    badge.style.display = 'flex';
                } else {
                    badge.style.display = 'none';
                }
            }
        }

        // Animation fade in/fade out pour le badge Budget Restant
        function animateBudgetBadge() {
            const budgetBadge = document.getElementById('budgetBadgeContainer');
            if (!budgetBadge) return;

            // Afficher avec fade in
            budgetBadge.style.opacity = '1';
            
            // Masquer après 3 secondes avec fade out
            setTimeout(() => {
                budgetBadge.style.opacity = '0';
            }, 3000);
        }

        // Exécuter l'animation au chargement de la page
        setTimeout(() => {
            animateBudgetBadge();
        }, 1000);

        // Répéter l'animation toutes les 10 secondes
        setInterval(() => {
            animateBudgetBadge();
        }, 10000);
        }); // Fin du DOMContentLoaded
    </script>
    <script src="../../assets/js/theme.js"></script>
</body>
</html>