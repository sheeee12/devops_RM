<?php
session_start();
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../config/Lang.php';

Lang::init();
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
// NOTIFICATIONS
// =========================================================================
// Récupérer les réclamations depuis la table reclamations existante
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

// Fonction Badge
function getStatusBadge($status) {
    $badges = [
        'Brouillon' => '<span class="badge bg-secondary">Brouillon</span>',
        'Attente_Manager' => '<span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>'.Lang::get('table.status_pending').'</span>',
        'Attente_Admin' => '<span class="badge bg-info text-dark"><i class="bi bi-clock me-1"></i>Admin</span>',
        'Valide' => '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Validé</span>',
        'Rejete' => '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Rejeté</span>',
        'Paye' => '<span class="badge bg-primary"><i class="bi bi-wallet me-1"></i>Payé</span>'
    ];
    return $badges[$status] ?? '<span class="badge bg-secondary">' . htmlspecialchars($status) . '</span>';
}

// Récupérer les demandes en attente (Les plus anciennes en premier)
$sql = "SELECT d.*, u.nom as user_name, u.avatar, t.nom_team
        FROM demande d
        JOIN users u ON d.user_id = u.user_id
        JOIN teams t ON u.team_id = t.team_id
        WHERE u.manager_id = ? AND d.status = 'Attente_Manager'
        ORDER BY d.created_at ASC"; // FIFO (First In First Out)

$stmt = $pdo->prepare($sql);
$stmt->execute([$managerId]);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="<?= Lang::current() ?>">
<head>
    <meta charset="UTF-8">
    <title>Validation | Rembourse Maroc</title>
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
        /* --- STYLES AUDIT IA AMÉLIORÉS --- */
        .ai-report-box {
            background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
            border: 2px solid #86efac;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            display: none;
            animation: slideInUp 0.5s ease-out;
            box-shadow: 0 4px 20px rgba(16, 185, 129, 0.15);
            position: relative;
            overflow: hidden;
        }
        
        .ai-report-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #10b981, #34d399, #6ee7b7);
        }
        
        .ai-report-box h6 {
            font-size: 1.1rem;
            color: #065f46;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .ai-report-box h6 i {
            font-size: 1.3rem;
            animation: pulse 2s infinite;
        }
        
        .ai-report-box ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .ai-report-box li {
            background: white;
            border-left: 4px solid #10b981;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        
        .ai-report-box li:hover {
            transform: translateX(4px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .ai-report-box li:last-child {
            margin-bottom: 0;
        }
        
        .ai-report-box .text-success {
            color: #059669 !important;
            font-weight: 600;
        }
        
        .ai-report-box .text-danger {
            color: #dc2626 !important;
            font-weight: 600;
        }
        
        .ai-report-box .text-warning {
            color: #d97706 !important;
            font-weight: 600;
        }
        
        .ai-loading {
            display: none;
            text-align: center;
            padding: 40px 20px;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 16px;
            margin-bottom: 24px;
            border: 2px dashed #cbd5e1;
        }
        
        .ai-loading .spinner-border {
            width: 3rem;
            height: 3rem;
            border-width: 4px;
        }
        
        .ai-loading p {
            margin-top: 16px;
            color: #64748b;
            font-size: 0.95rem;
        }
        
        .proof-img {
            max-height: 100px;
            cursor: pointer;
            border-radius: 8px;
            border: 1px solid #ddd;
            transition: all 0.3s ease;
        }
        
        .proof-img:hover {
            opacity: 0.8;
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        /* Modal améliorée avec backdrop blur */
        .modal-backdrop {
            background-color: rgba(0, 0, 0, 0.5) !important;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            transition: backdrop-filter 0.3s ease, background-color 0.3s ease;
        }
        
        .modal-backdrop.show {
            opacity: 1 !important;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        
        .modal-backdrop.fade {
            transition: opacity 0.15s linear, backdrop-filter 0.3s ease;
        }
        
        .modal-content {
            border-radius: 16px;
            border: none;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        /* Assurer que le body ne scroll pas quand la modale est ouverte */
        body.modal-open {
            overflow: hidden;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: white;
            border-radius: 16px 16px 0 0;
            padding: 20px 24px;
            border-bottom: none;
        }
        
        .modal-header .modal-title {
            color: white;
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
            opacity: 0.9;
        }
        
        .modal-header .btn-close:hover {
            opacity: 1;
        }
        
        .modal-body {
            padding: 24px;
        }
        
        /* Cartes de frais améliorées */
        #expenseLinesContainer .card {
            transition: all 0.3s ease;
            border-left: 4px solid #059669;
        }
        
        #expenseLinesContainer .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12) !important;
        }
        
        /* Zone de preuve améliorée */
        #zoomProof {
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
            transition: transform 0.3s ease;
        }
        
        #zoomProof:hover {
            transform: scale(1.02);
        }
        
        /* Boutons améliorés */
        .btn-dark {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            border: none;
            padding: 12px 20px;
            font-weight: 600;
            border-radius: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(30, 41, 59, 0.3);
        }
        
        .btn-dark:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(30, 41, 59, 0.4);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border: none;
            padding: 12px 20px;
            font-weight: 600;
            border-radius: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            border: none;
            padding: 12px 20px;
            font-weight: 600;
            border-radius: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(239, 68, 68, 0.4);
        }
        
        /* Animations */
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.6;
            }
        }
        
        /* Zone de preuve améliorée */
        .card-header {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%) !important;
            border-bottom: 2px solid #e2e8f0;
            font-weight: 600;
            color: #1e293b;
        }
        
        /* Styles pour le contenu HTML généré par l'IA */
        #aiContent ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        #aiContent li {
            background: white;
            border-left: 4px solid #10b981;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        
        #aiContent li:hover {
            transform: translateX(4px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        #aiContent .text-success {
            color: #059669 !important;
            font-weight: 600;
        }
        
        #aiContent .text-danger {
            color: #dc2626 !important;
            font-weight: 600;
        }
        
        #aiContent .text-warning {
            color: #d97706 !important;
            font-weight: 600;
        }
        
        #aiContent i {
            margin-right: 6px;
        }
        
        /* Animation pour les cartes de frais au hover */
        #expenseLinesContainer .card:hover {
            border-left-color: #10b981;
        }
    </style>
</head>
<body>

    <header class="app-header" style="background-color: #059669;">
        <div class="d-flex align-items-center">
            <div class="brand-logo">
                <div class="logo-rm">RM</div> RembourseMaroc
            </div>
        </div>
        <nav class="app-nav">
            <a href="dashboard.php" class="nav-link"><i class="bi bi-speedometer2"></i> Tableau de bord</a>
            <a href="validation.php" class="nav-link active"><i class="bi bi-check-circle"></i> Validation</a>
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
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold m-0 text-dark">Centre de Validation</h4>
                <div class="text-muted small">Auditez et validez les frais de votre équipe.</div>
            </div>
        </div>

        <div class="card-widget p-0">
            <div class="table-responsive">
                <table class="table table-app table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Date Dépôt</th>
                            <th>Collaborateur</th>
                            <th>Mission</th>
                            <th>Montant Total</th>
                            <th>Ancienneté</th>
                            <th class="text-end pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($requests)): ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">Aucune demande en attente.</td></tr>
                        <?php else: ?>
                            <?php foreach ($requests as $r): 
                                $days = (new DateTime($r['created_at']))->diff(new DateTime())->days;
                                $badgeClass = $days > 5 ? 'bg-danger-subtle text-danger' : 'bg-warning-subtle text-warning';
                            ?>
                            <tr>
                                <td class="ps-4"><?= date('d/m/Y', strtotime($r['created_at'])) ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar-circle bg-primary text-white d-flex justify-content-center align-items-center" style="width:32px;height:32px;font-size:0.8rem;">
                                            <?= strtoupper(substr($r['user_name'], 0, 1)) ?>
                                        </div>
                                        <span class="fw-bold"><?= htmlspecialchars($r['user_name']) ?></span>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($r['titre_dem']) ?></td>
                                <td class="fw-bold"><?= number_format($r['montant_total'], 2) ?> DH</td>
                                <td><span class="badge <?= $badgeClass ?>"><?= $days ?> jours</span></td>
                                <td class="text-end pe-4">
                                    <button class="btn btn-sm btn-primary" onclick="openValidationModal(<?= $r['id_dem'] ?>)">
                                        Examiner
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- --- MODALE D'AUDIT & VALIDATION --- -->
    <div class="modal fade" id="validationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable"> <!-- Largeur XL -->
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-search"></i> Audit de la demande #<span id="modalRef"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body bg-light">
                    
                    <div class="row">
                        <!-- COLONNE GAUCHE : DÉTAILS & LIGNES -->
                        <div class="col-lg-7">
                            
                            <!-- Zone de Rapport IA -->
                            <div class="ai-report-box" id="aiReport">
                                <h6 class="fw-bold"><i class="bi bi-robot"></i> Rapport d'Analyse IA</h6>
                                <div id="aiContent"></div>
                            </div>
                            
                            <!-- Spinner Chargement IA -->
                            <div class="ai-loading" id="aiLoader">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Chargement...</span>
                                </div>
                                <p class="text-muted mt-3">
                                    <i class="bi bi-hourglass-split me-2"></i>
                                    L'agent IA analyse les prix sur le marché et la politique...
                                </p>
                            </div>

                            <!-- Liste des frais -->
                            <div id="expenseLinesContainer">
                                <!-- Rempli par AJAX -->
                            </div>
                        </div>

                        <!-- COLONNE DROITE : ACTIONS & PREUVE -->
                        <div class="col-lg-5">
                            <div class="card border-0 shadow-lg mb-3" style="border-radius: 16px; overflow: hidden;">
                                <div class="card-body p-4">
                                    <h6 class="fw-bold mb-4 d-flex align-items-center">
                                        <i class="bi bi-gear-fill me-2 text-primary"></i>
                                        Actions Manager
                                    </h6>
                                    <button class="btn btn-dark w-100 mb-3" onclick="runAIAudit()" style="font-size: 1rem;">
                                        <i class="bi bi-robot  me-2" style="color: white;"></i> 
                                        Lancer l'Audit IA
                                    </button>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-success flex-grow-1" onclick="validateRequest()">
                                            <i class="bi bi-check-lg me-2"></i> Valider
                                        </button>
                                        <button class="btn btn-danger flex-grow-1" onclick="rejectRequest()">
                                            <i class="bi bi-x-lg me-2"></i> Rejeter
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Zone visualisation Preuve (Zoom) -->
                            <div class="card border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
                                <div class="card-header fw-bold d-flex align-items-center">
                                    <i class="bi bi-receipt me-2 text-primary"></i>
                                    Justificatif (Aperçu)
                                </div>
                                <div class="card-body text-center bg-dark" style="min-height: 200px; display: flex; align-items: center; justify-content: center; flex-direction: column;">
                                    <img id="zoomProof" src="" class="img-fluid" style="max-height: 400px; max-width: 100%; border-radius: 12px; display:none;">
                                    <p id="noProofText" class="text-white-50 small mt-2" style="display: block;">
                                        <i class="bi bi-image me-2"></i>
                                        Sélectionnez une ligne pour voir la preuve.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        let currentDemandeId = 0;

        // OUVRIR LA MODALE ET CHARGER LES DÉTAILS
        function openValidationModal(id) {
            currentDemandeId = id;
            document.getElementById('modalRef').textContent = id;
            
            // Reset Interface
            document.getElementById('aiReport').style.display = 'none';
            document.getElementById('aiContent').innerHTML = '';
            document.getElementById('expenseLinesContainer').innerHTML = '<div class="text-center p-4"><div class="spinner-border text-secondary"></div></div>';
            
            const modal = new bootstrap.Modal(document.getElementById('validationModal'));
            modal.show();

            // Appel AJAX pour récupérer les lignes
            fetch(`../../actions/get_expense_lines.php?id=${id}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('expenseLinesContainer').innerHTML = html;
                });
        }

        // AFFICHER UNE PREUVE (Appelé au clic sur une ligne)
        function showProof(imgUrl) {
            const img = document.getElementById('zoomProof');
            const txt = document.getElementById('noProofText');
            if(imgUrl && imgUrl !== 'null') {
                img.src = '../../uploads/proofs/' + imgUrl;
                img.style.display = 'block';
                txt.style.display = 'none';
            } else {
                img.style.display = 'none';
                txt.style.display = 'block';
                txt.textContent = "Aucun justificatif pour cette ligne.";
            }
        }

        // LANCER L'AUDIT IA (LE COEUR DU SUJET)
        function runAIAudit() {
            document.getElementById('aiLoader').style.display = 'block';
            document.getElementById('aiReport').style.display = 'none';

            fetch('../../actions/ai_audit.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${currentDemandeId}`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Erreur HTTP: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                document.getElementById('aiLoader').style.display = 'none';
                document.getElementById('aiReport').style.display = 'block';
                
                // Affichage du rapport
                if(data.success) {
                    document.getElementById('aiContent').innerHTML = data.report_html;
                } else {
                    const errorMsg = data.message || 'Erreur inconnue';
                    const errorType = data.error_type || 'unknown';
                    const errorCode = data.error_code || 0;
                    
                    // Message spécifique pour l'erreur 429 (rate limiting)
                    let solutionsHtml = '';
                    if (errorType === 'rate_limit' || errorCode === 429) {
                        solutionsHtml = `
                            <strong>Limite de requêtes atteinte :</strong><br>
                            • L'API GitHub Models limite le nombre de requêtes par période<br>
                            • Veuillez patienter <strong>2-5 minutes</strong> avant de réessayer<br>
                            • Cette limitation est normale pour les comptes gratuits ou avec quotas limités<br>
                            • Vous pouvez continuer à utiliser l'application normalement, seule l'audit IA est temporairement indisponible
                        `;
                    } else if (errorCode === 401 || errorCode === 403) {
                        solutionsHtml = `
                            <strong>Problème d'authentification :</strong><br>
                            • Vérifiez que le fichier <code>config/api_credentials.php</code> existe<br>
                            • Vérifiez que votre token GitHub API est valide et non expiré<br>
                            • Pour obtenir un nouveau token : <a href="https://github.com/settings/tokens" target="_blank">https://github.com/settings/tokens</a>
                        `;
                    } else if (errorCode >= 500) {
                        solutionsHtml = `
                            <strong>Service temporairement indisponible :</strong><br>
                            • Le service IA est actuellement surchargé ou en maintenance<br>
                            • Veuillez réessayer dans quelques minutes<br>
                            • Vérifiez votre connexion internet
                        `;
                    } else {
                        solutionsHtml = `
                            <strong>Solutions possibles :</strong><br>
                            • Vérifiez votre connexion internet<br>
                            • Vérifiez que le fichier <code>config/api_credentials.php</code> existe et contient un token valide<br>
                            • Réessayez dans quelques instants
                        `;
                    }
                    
                    const alertClass = errorType === 'rate_limit' ? 'alert-warning' : 'alert-danger';
                    const iconClass = errorType === 'rate_limit' ? 'bi-hourglass-split' : 'bi-exclamation-triangle-fill';
                    
                    // Ajouter un bouton "Réessayer" pour l'erreur 429
                    let retryButton = '';
                    if (errorType === 'rate_limit' || errorCode === 429) {
                        retryButton = `
                            <div class="mt-3">
                                <button class="btn btn-warning btn-sm" onclick="setTimeout(() => runAIAudit(), 3000)">
                                    <i class="bi bi-arrow-clockwise me-2"></i>Réessayer dans 3 secondes
                                </button>
                                <button class="btn btn-outline-secondary btn-sm ms-2" onclick="runAIAudit()">
                                    <i class="bi bi-arrow-clockwise me-2"></i>Réessayer maintenant
                                </button>
                            </div>
                        `;
                    }
                    
                    document.getElementById('aiContent').innerHTML = `
                        <div class="alert ${alertClass}" role="alert">
                            <h6 class="alert-heading"><i class="bi ${iconClass}"></i> ${errorType === 'rate_limit' ? 'Limite de requêtes atteinte' : 'Erreur de communication avec l\'IA'}</h6>
                            <p class="mb-0">${errorMsg}</p>
                            <hr>
                            <p class="mb-0 small">
                                ${solutionsHtml}
                            </p>
                            ${retryButton}
                        </div>
                    `;
                }
            })
            .catch(err => {
                document.getElementById('aiLoader').style.display = 'none';
                document.getElementById('aiReport').style.display = 'block';
                document.getElementById('aiContent').innerHTML = `
                    <div class="alert alert-danger" role="alert">
                        <h6 class="alert-heading"><i class="bi bi-exclamation-triangle-fill"></i> Erreur de communication avec l'IA</h6>
                        <p class="mb-0">Impossible de contacter le service d'audit IA.</p>
                        <hr>
                        <p class="mb-0 small">
                            <strong>Détails :</strong> ${err.message}<br>
                            <strong>Solutions :</strong> Vérifiez votre connexion internet et réessayez.
                        </p>
                    </div>
                `;
            });
        }

        // ACTIONS VALIDATION / REJET
        function validateRequest() {
            if(!currentDemandeId) {
                alert("Erreur : Aucune demande sélectionnée");
                return;
            }
            
            if(confirm("⚠️ Confirmer la validation de cette demande ?\n\nLe budget de l'équipe sera impacté et la demande passera en attente d'approbation administrative.")) {
                // Désactiver le bouton pendant le traitement
                const btn = document.querySelector('button[onclick="validateRequest()"]');
                if(btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Traitement...';
                }
                
                window.location.href = `../../actions/validate_request.php?id=${currentDemandeId}`;
            }
        }
        
        function rejectRequest() {
            if(!currentDemandeId) {
                alert("Erreur : Aucune demande sélectionnée");
                return;
            }
            
            // Créer une modale pour le motif de rejet
            const reason = prompt(" Motif du rejet :\n\n(Veuillez indiquer la raison du rejet de cette demande)");
            
            if(reason && reason.trim() !== '') {
                // Désactiver le bouton pendant le traitement
                const btn = document.querySelector('button[onclick="rejectRequest()"]');
                if(btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Traitement...';
                }
                
                window.location.href = `../../actions/reject_request.php?id=${currentDemandeId}&reason=${encodeURIComponent(reason.trim())}`;
            } else if(reason !== null) {
                alert("⚠️ Le motif du rejet est obligatoire. Veuillez indiquer une raison.");
            }
        }
        
        // Afficher les messages de succès/erreur depuis l'URL
        window.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const success = urlParams.get('success');
            const error = urlParams.get('error');
            
            if(success === 'validated') {
                // Fermer la modale si elle est ouverte
                const modal = bootstrap.Modal.getInstance(document.getElementById('validationModal'));
                if(modal) modal.hide();
                
                // Afficher un message de succès
                setTimeout(() => {
                    alert('Demande validée avec succès !\n\nLa demande a été transmise à l\'administration pour approbation finale.');
                }, 300);
            }
            
            if(success === 'rejected') {
                // Fermer la modale si elle est ouverte
                const modal = bootstrap.Modal.getInstance(document.getElementById('validationModal'));
                if(modal) modal.hide();
                
                // Afficher un message de succès
                setTimeout(() => {
                    alert(' Demande rejetée.\n\nL\'employé a été notifié du rejet avec le motif indiqué.');
                }, 300);
            }
            
            if(error) {
                let errorMsg = '';
                switch(error) {
                    case 'invalid_id':
                        errorMsg = 'Erreur : ID de demande invalide';
                        break;
                    case 'unauthorized':
                        errorMsg = 'Erreur : Vous n\'êtes pas autorisé à traiter cette demande';
                        break;
                    case 'db_error':
                        errorMsg = 'Erreur : Problème de base de données. Veuillez réessayer.';
                        break;
                    default:
                        errorMsg = 'Une erreur est survenue';
                }
                alert('!' + errorMsg);
            }
        });

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
            const notificationItems = document.querySelectorAll('.notification-item:not([style*="opacity: 0.5"])');
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
    </script>
    <script src="../../assets/js/theme.js"></script>
</body>
</html>