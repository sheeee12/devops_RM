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

// Notifications (simplifié pour cette page)
try {
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
} catch (PDOException $e) {}

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

usort($allNotifications, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

$notifications = array_slice($allNotifications, 0, 10);
$notificationCount = count($notifications);

if (!isset($_GET['mission_id']) || !is_numeric($_GET['mission_id'])) {
    header('Location: deplacements.php');
    exit;
}

$missionId = (int)$_GET['mission_id'];

// Récupérer les détails de la mission
$sqlMission = "SELECT m.*, 
               GROUP_CONCAT(CONCAT(u.user_id, ':', u.nom, ' ', u.prenom) SEPARATOR '|') as participants_data
               FROM missions m
               LEFT JOIN mission_participants mp ON m.id_mission = mp.id_mission
               LEFT JOIN users u ON mp.user_id = u.user_id
               WHERE m.id_mission = ? AND m.manager_id = ?
               GROUP BY m.id_mission";

$stmt = $pdo->prepare($sqlMission);
$stmt->execute([$missionId, $managerId]);
$mission = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$mission) {
    header('Location: deplacements.php');
    exit;
}

// Récupérer les participants
$participants = [];
if (!empty($mission['participants_data'])) {
    foreach (explode('|', $mission['participants_data']) as $part) {
        list($userId, $name) = explode(':', $part, 2);
        $participants[$userId] = $name;
    }
}

// Récupérer les demandes liées à cette mission (par date et participants)
$participantIds = array_keys($participants);
$demandes = [];

if (!empty($participantIds)) {
    $placeholders = str_repeat('?,', count($participantIds) - 1) . '?';
    $sqlDemandes = "SELECT d.*, u.nom, u.prenom, u.user_id,
                    CASE 
                        WHEN d.date_dep BETWEEN ? AND ? AND d.user_id IN ($placeholders) THEN 'COHÉRENT'
                        ELSE 'INCOHÉRENT'
                    END as coherence
                    FROM demande d
                    JOIN users u ON d.user_id = u.user_id
                    WHERE u.manager_id = ?
                    AND (d.date_dep BETWEEN ? AND ? OR d.user_id IN ($placeholders))
                    ORDER BY d.date_dep ASC";

    $params = array_merge(
        [$mission['date_debut'], $mission['date_fin']],
        $participantIds,
        [$managerId, $mission['date_debut'], $mission['date_fin']],
        $participantIds
    );

    $stmt = $pdo->prepare($sqlDemandes);
    $stmt->execute($params);
    $demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Si aucun participant, chercher juste par date
    $sqlDemandes = "SELECT d.*, u.nom, u.prenom, u.user_id, 'INCOHÉRENT' as coherence
                    FROM demande d
                    JOIN users u ON d.user_id = u.user_id
                    WHERE u.manager_id = ?
                    AND d.date_dep BETWEEN ? AND ?
                    ORDER BY d.date_dep ASC";
    
    $stmt = $pdo->prepare($sqlDemandes);
    $stmt->execute([$managerId, $mission['date_debut'], $mission['date_fin']]);
    $demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="<?= Lang::current() ?>">
<head>
    <meta charset="UTF-8">
    <title>Vérification Demandes | Rembourse Maroc</title>
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
        .mission-info-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08), 0 1px 3px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(0, 0, 0, 0.06);
            margin-bottom: 24px;
        }
        .info-item {
            padding: 12px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }
        .info-item:last-child {
            border-bottom: none;
        }
        .info-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            color: #64748b;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .info-value {
            font-size: 1rem;
            font-weight: 600;
            color: #1e293b;
        }
        .coherence-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .table-app tbody tr {
            transition: all 0.2s ease;
        }
        .table-app tbody tr:hover {
            background-color: #f8fafc;
            transform: scale(1.01);
        }
        [data-theme="dark"] .table-app tbody tr:hover {
            background-color: #1e293b;
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
            <a href="validation.php" class="nav-link"><i class="bi bi-check-circle"></i> Validation</a>
            <a href="deplacements.php" class="nav-link active"><i class="bi bi-airplane"></i> Déplacements</a>
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
        <!-- Titre et bouton retour -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold m-0 text-dark">Vérification des Demandes</h4>
                <p class="text-muted small mb-0">Analyse de cohérence des demandes avec la mission</p>
            </div>
            <a href="deplacements.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i>Retour aux déplacements
            </a>
        </div>
        
        <!-- Carte d'information de la mission -->
        <div class="mission-info-card">
            <div class="d-flex align-items-center mb-3">
                <h5 class="fw-bold mb-0"><?= htmlspecialchars($mission['titre']) ?></h5>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="info-item">
                        <div class="info-label"><i class="bi bi-geo-alt me-1"></i>Lieu</div>
                        <div class="info-value"><?= htmlspecialchars($mission['lieu']) ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-item">
                        <div class="info-label"><i class="bi bi-calendar-range me-1"></i>Période</div>
                        <div class="info-value">
                            <?= date('d/m/Y', strtotime($mission['date_debut'])) ?> - <?= date('d/m/Y', strtotime($mission['date_fin'])) ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-item">
                        <div class="info-label"><i class="bi bi-people me-1"></i>Participants</div>
                        <div class="info-value"><?= count($participants) ?> personne(s)</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tableau des demandes -->
        <div class="card-widget p-0">
            <div class="card-header-custom d-flex justify-content-between align-items-center">
                <h6 class="section-title mb-0"><i class="bi bi-list-check text-primary me-2"></i>Demandes associées</h6>
                <span class="badge bg-light text-dark border"><?= count($demandes) ?> demande(s)</span>
            </div>
            <div class="table-responsive">
                <table class="table table-app mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>Date</th>
                            <th>Employé</th>
                            <th>Titre</th>
                            <th class="text-end">Montant</th>
                            <th>Statut</th>
                            <th class="text-center">Cohérence</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($demandes)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                    <div>Aucune demande trouvée pour cette mission</div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($demandes as $dem): ?>
                            <tr>
                                <td>
                                    <div class="fw-medium"><?= date('d/m/Y', strtotime($dem['date_dep'])) ?></div>
                                    <small class="text-muted"><?= date('H:i', strtotime($dem['date_dep'])) ?></small>
                                </td>
                                <td>
                                    <div class="fw-medium"><?= htmlspecialchars($dem['nom'] . ' ' . $dem['prenom']) ?></div>
                                </td>
                                <td>
                                    <div class="fw-medium"><?= htmlspecialchars($dem['titre_dem']) ?></div>
                                </td>
                                <td class="text-end">
                                    <span class="fw-bold text-success"><?= number_format($dem['montant_total'], 2) ?> DH</span>
                                </td>
                                <td>
                                    <?php
                                    $badges = [
                                        'Valide' => ['class' => 'bg-success', 'icon' => 'bi-check-circle'],
                                        'Rejete' => ['class' => 'bg-danger', 'icon' => 'bi-x-circle'],
                                        'Attente_Manager' => ['class' => 'bg-warning', 'icon' => 'bi-hourglass-split'],
                                        'Attente_Admin' => ['class' => 'bg-info', 'icon' => 'bi-building']
                                    ];
                                    $badge = $badges[$dem['status']] ?? ['class' => 'bg-secondary', 'icon' => 'bi-circle'];
                                    ?>
                                    <span class="badge <?= $badge['class'] ?>">
                                        <i class="bi <?= $badge['icon'] ?> me-1"></i><?= $dem['status'] ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if ($dem['coherence'] === 'COHÉRENT'): ?>
                                        <span class="coherence-badge bg-success text-white">
                                            <i class="bi bi-check-circle-fill"></i>Coherent
                                        </span>
                                    <?php else: ?>
                                        <span class="coherence-badge bg-danger text-white">
                                            <i class="bi bi-exclamation-triangle-fill"></i>Incohérent
                                        </span>
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/theme.js"></script>
    <script>
        // Gestion des notifications
        document.addEventListener('DOMContentLoaded', function() {
            const notificationItems = document.querySelectorAll('.notification-item');
            
            notificationItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const notificationId = this.dataset.notificationId;
                    const notificationType = this.dataset.notificationType;
                    const demandId = this.dataset.demandId;
                    
                    // Marquer comme lu
                    fetch('../../actions/mark_notification_read.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            notification_id: notificationId,
                            type: notificationType,
                            demand_id: demandId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            this.style.opacity = '0.5';
                            setTimeout(() => {
                                this.remove();
                                updateNotificationBadge();
                            }, 300);
                        }
                    })
                    .catch(error => console.error('Error:', error));
                    
                    // Redirection si nécessaire
                    if (demandId) {
                        window.location.href = 'details_validation.php?id=' + demandId;
                    }
                });
            });
            
            function updateNotificationBadge() {
                const badge = document.querySelector('.notification-badge');
                const items = document.querySelectorAll('.notification-item');
                const count = items.length;
                
                if (badge) {
                    if (count > 0) {
                        badge.textContent = count;
                        badge.style.display = 'flex';
                    } else {
                        badge.style.display = 'none';
                    }
                }
            }
        });
    </script>
</body>
</html>

