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

// Récupérer les missions à venir
$sqlUpcoming = "SELECT m.*, 
                GROUP_CONCAT(CONCAT(u.nom, ' ', u.prenom) SEPARATOR ', ') as participants_names,
                COUNT(DISTINCT mp.user_id) as nb_participants
                FROM missions m
                LEFT JOIN mission_participants mp ON m.id_mission = mp.id_mission
                LEFT JOIN users u ON mp.user_id = u.user_id
                WHERE m.manager_id = ? AND m.date_fin >= CURDATE()
                GROUP BY m.id_mission
                ORDER BY m.date_debut ASC";

$stmt = $pdo->prepare($sqlUpcoming);
$stmt->execute([$managerId]);
$upcomingMissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les missions passées
$sqlPast = "SELECT m.*, 
            GROUP_CONCAT(CONCAT(u.nom, ' ', u.prenom) SEPARATOR ', ') as participants_names,
            COUNT(DISTINCT mp.user_id) as nb_participants,
            COUNT(DISTINCT d.id_dem) as nb_demandes,
            COALESCE(SUM(d.montant_total), 0) as total_depenses
            FROM missions m
            LEFT JOIN mission_participants mp ON m.id_mission = mp.id_mission
            LEFT JOIN users u ON mp.user_id = u.user_id
            LEFT JOIN demande d ON d.user_id = mp.user_id 
                AND d.date_dep BETWEEN m.date_debut AND m.date_fin
                AND d.status != 'Rejete'
            WHERE m.manager_id = ? AND m.date_fin < CURDATE()
            GROUP BY m.id_mission
            ORDER BY m.date_fin DESC";

$stmt = $pdo->prepare($sqlPast);
$stmt->execute([$managerId]);
$pastMissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Notifications (même code)
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
?>

<!DOCTYPE html>
<html lang="<?= Lang::current() ?>">
<head>
    <meta charset="UTF-8">
    <title>Déplacements | Rembourse Maroc</title>
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
        .mission-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid #059669;
            transition: all 0.3s ease;
        }
        .mission-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        .mission-card.past {
            border-left-color: #64748b;
            opacity: 0.9;
        }
        .mission-card.upcoming {
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
            <a href="validation.php" class="nav-link"><i class="bi bi-check-circle"></i> Validation</a>
            <a href="deplacements.php" class="nav-link active"><i class="bi bi-airplane"></i> Déplacements</a>
            <a href="equipe.php" class="nav-link"><i class="bi bi-people"></i> Mon Équipe</a>
            <a href="historique.php" class="nav-link"><i class="bi bi-clock-history"></i> Historique</a>
        </nav>
        <div class="d-flex align-items-center gap-3">
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold m-0 text-dark">Gestion des Déplacements</h4>
                <div class="text-muted small">Suivez les missions de votre équipe et vérifiez la cohérence des demandes</div>
            </div>
            <a href="create_mission.php" class="btn btn-primary">
                <i class="bi bi-plus-circle me-2"></i>Nouveau Déplacement
            </a>
        </div>

        <!-- Prochains Déplacements -->
        <div class="mb-5">
            <h5 class="fw-bold mb-3 text-success">
                <i class="bi bi-calendar-check me-2"></i>Prochains Déplacements
            </h5>
            <?php if (empty($upcomingMissions)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>Aucun déplacement prévu
                </div>
            <?php else: ?>
                <?php foreach ($upcomingMissions as $mission): ?>
                <div class="mission-card upcoming">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <h6 class="fw-bold mb-2">
                                <i class="bi bi-briefcase me-2 text-primary"></i>
                                <?= htmlspecialchars($mission['titre']) ?>
                            </h6>
                            <div class="row g-3 mb-2">
                                <div class="col-md-4">
                                    <small class="text-muted d-block">Lieu</small>
                                    <strong><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($mission['lieu']) ?></strong>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted d-block">Date Début</small>
                                    <strong><i class="bi bi-calendar-event me-1"></i><?= date('d/m/Y', strtotime($mission['date_debut'])) ?></strong>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted d-block">Date Fin</small>
                                    <strong><i class="bi bi-calendar-x me-1"></i><?= date('d/m/Y', strtotime($mission['date_fin'])) ?></strong>
                                </div>
                            </div>
                            <div class="mt-2">
                                <small class="text-muted d-block mb-1">Participants (<?= $mission['nb_participants'] ?>)</small>
                                <span class="badge bg-primary-subtle text-primary"><?= htmlspecialchars($mission['participants_names'] ?? 'Aucun') ?></span>
                            </div>
                        </div>
                        <div class="ms-3 d-flex flex-column gap-2">
                            <a href="verifier_demandes.php?mission_id=<?= $mission['id_mission'] ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-search me-1"></i>Vérifier Demandes
                            </a>
                            <button class="btn btn-sm btn-outline-danger delete-mission-btn" data-mission-id="<?= $mission['id_mission'] ?>" data-mission-titre="<?= htmlspecialchars($mission['titre']) ?>">
                                <i class="bi bi-trash me-1"></i>Supprimer
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Historique des Déplacements -->
        <div>
            <h5 class="fw-bold mb-3 text-muted">
                <i class="bi bi-clock-history me-2"></i>Historique des Déplacements
            </h5>
            <?php if (empty($pastMissions)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>Aucun déplacement dans l'historique
                </div>
            <?php else: ?>
                <?php foreach ($pastMissions as $mission): ?>
                <div class="mission-card past">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <h6 class="fw-bold mb-2">
                                <i class="bi bi-briefcase me-2"></i>
                                <?= htmlspecialchars($mission['titre']) ?>
                            </h6>
                            <div class="row g-3 mb-2">
                                <div class="col-md-3">
                                    <small class="text-muted d-block">Lieu</small>
                                    <strong><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($mission['lieu']) ?></strong>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted d-block">Période</small>
                                    <strong><?= date('d/m/Y', strtotime($mission['date_debut'])) ?> - <?= date('d/m/Y', strtotime($mission['date_fin'])) ?></strong>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted d-block">Demandes</small>
                                    <strong class="text-info"><?= $mission['nb_demandes'] ?> demande(s)</strong>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted d-block">Total Dépenses</small>
                                    <strong class="text-success"><?= number_format($mission['total_depenses'], 2) ?> DH</strong>
                                </div>
                            </div>
                            <div class="mt-2">
                                <small class="text-muted d-block mb-1">Participants (<?= $mission['nb_participants'] ?>)</small>
                                <span class="badge bg-secondary"><?= htmlspecialchars($mission['participants_names'] ?? 'Aucun') ?></span>
                            </div>
                        </div>
                        <div class="ms-3 d-flex flex-column gap-2">
                            <a href="verifier_demandes.php?mission_id=<?= $mission['id_mission'] ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-search me-1"></i>Vérifier
                            </a>
                            <button class="btn btn-sm btn-outline-danger delete-mission-btn" data-mission-id="<?= $mission['id_mission'] ?>" data-mission-titre="<?= htmlspecialchars($mission['titre']) ?>">
                                <i class="bi bi-trash me-1"></i>Supprimer
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Gestion des notifications - Marquer comme lue au clic
            document.querySelectorAll('.notification-item').forEach(item => {
                item.addEventListener('click', async function(e) {
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
                                
                                // Mettre à jour le badge
                                updateNotificationBadge();
                            }
                        } catch (error) {
                            console.error('Erreur lors de la mise à jour de la notification:', error);
                        }
                    }
                    
                    // Rediriger si c'est une réclamation avec une demande
                    if (demandId) {
                        setTimeout(() => {
                            window.location.href = 'details_validation.php?id=' + demandId;
                        }, 200);
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

            // Gestion de la suppression des missions
            document.querySelectorAll('.delete-mission-btn').forEach(btn => {
                btn.addEventListener('click', async function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const missionId = this.getAttribute('data-mission-id');
                    const missionTitre = this.getAttribute('data-mission-titre');
                    
                    // Confirmation
                    if (!confirm(`Êtes-vous sûr de vouloir supprimer le déplacement "${missionTitre}" ?\n\nCette action est irréversible.`)) {
                        return;
                    }
                    
                    // Désactiver le bouton pendant la requête
                    const originalHTML = this.innerHTML;
                    this.disabled = true;
                    this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Suppression...';
                    
                    try {
                        const formData = new FormData();
                        formData.append('mission_id', missionId);
                        
                        const response = await fetch('../../actions/delete_mission.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            // Afficher un message de succès
                            alert('Déplacement supprimé avec succès !');
                            
                            // Supprimer la carte de la page
                            const missionCard = this.closest('.mission-card');
                            if (missionCard) {
                                missionCard.style.transition = 'opacity 0.3s ease';
                                missionCard.style.opacity = '0';
                                setTimeout(() => {
                                    missionCard.remove();
                                    
                                    // Vérifier s'il reste des missions
                                    const remainingMissions = document.querySelectorAll('.mission-card');
                                    if (remainingMissions.length === 0) {
                                        location.reload();
                                    }
                                }, 300);
                            } else {
                                location.reload();
                            }
                        } else {
                            alert('Erreur : ' + (result.message || 'Impossible de supprimer le déplacement'));
                            this.disabled = false;
                            this.innerHTML = originalHTML;
                        }
                    } catch (error) {
                        console.error('Erreur lors de la suppression:', error);
                        alert('Erreur lors de la suppression du déplacement. Veuillez réessayer.');
                        this.disabled = false;
                        this.innerHTML = originalHTML;
                    }
                });
            });
        });
    </script>
    <script src="../../assets/js/theme.js"></script>
</body>
</html>

