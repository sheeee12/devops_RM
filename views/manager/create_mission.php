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

// Récupérer l'équipe
$stmt = $pdo->prepare("SELECT user_id, nom, prenom FROM users WHERE manager_id = ?");
$stmt->execute([$managerId]);
$employes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Notifications (même code que dashboard)
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
    <title>Nouveau Déplacement | Rembourse Maroc</title>
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
        body {
            position: relative;
            overflow-x: hidden;
        }
        
        /* Background avec blur */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 50%, #cbd5e1 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            z-index: -2;
        }
        
        body::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><defs><pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse"><path d="M 40 0 L 0 0 0 40" fill="none" stroke="rgba(5,150,105,0.1)" stroke-width="1"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            opacity: 0.3;
            z-index: -1;
            filter: blur(2px);
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .mission-form-container {
            position: relative;
            z-index: 1;
        }
        
        .mission-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            overflow: hidden;
        }
        
        .mission-header {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: white;
            padding: 30px;
            border-radius: 20px 20px 0 0;
        }
        
        .form-section {
            background: rgba(248, 250, 252, 0.6);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid rgba(226, 232, 240, 0.8);
        }
        
        .form-label {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e2e8f0;
            padding: 12px 16px;
            transition: all 0.3s ease;
            background: white;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #059669;
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
            background: white;
        }
        
        .participant-card {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .participant-card:hover {
            border-color: #059669;
            background: #f0fdf4;
            transform: translateX(4px);
        }
        
        .participant-card input[type="checkbox"]:checked + label {
            color: #059669;
            font-weight: 600;
        }
        
        .participant-card input[type="checkbox"]:checked ~ .participant-card {
            border-color: #059669;
            background: #f0fdf4;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            border: none;
            border-radius: 12px;
            padding: 14px 30px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(5, 150, 105, 0.3);
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(5, 150, 105, 0.4);
        }
        
        .icon-box {
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
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
            <a href="deplacements.php" class="nav-link"><i class="bi bi-airplane"></i> Déplacements</a>
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

    <div class="main-container mission-form-container">
        <div class="mb-4">
            <a href="deplacements.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i>Retour
            </a>
        </div>
        
        <div class="mission-card mx-auto" style="max-width: 900px;">
            <div class="mission-header">
                <div class="d-flex align-items-center">
                    <div class="icon-box">
                        <i class="bi bi-briefcase fs-3"></i>
                    </div>
                    <div>
                        <h3 class="mb-1 fw-bold">Créer un Ordre de Mission</h3>
                        <p class="mb-0 opacity-75">Planifiez un nouveau déplacement pour votre équipe</p>
                    </div>
                </div>
        </div>
            
            <div class="p-4">
            <form action="../../actions/add_mission_action.php" method="POST">
                
                    <!-- Informations de la mission -->
                    <div class="form-section">
                        <h6 class="fw-bold mb-3 d-flex align-items-center">
                            <i class="bi bi-info-circle me-2 text-primary"></i>
                            Informations de la Mission
                        </h6>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">
                                    <i class="bi bi-tag me-1"></i>Titre de la mission
                                </label>
                                <input type="text" name="titre" class="form-control" placeholder="Ex: Mission commerciale Casablanca" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">
                                    <i class="bi bi-geo-alt me-1"></i>Lieu
                                </label>
                                <input type="text" name="lieu" class="form-control" placeholder="Ex: Casablanca, Maroc" required>
                    </div>
                    </div>
                </div>

                    <!-- Dates -->
                    <div class="form-section">
                        <h6 class="fw-bold mb-3 d-flex align-items-center">
                            <i class="bi bi-calendar-range me-2 text-primary"></i>
                            Période du Déplacement
                        </h6>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">
                                    <i class="bi bi-calendar-event me-1"></i>Date de Début
                                </label>
                        <input type="date" name="date_debut" class="form-control" required>
                    </div>
                            <div class="col-md-6">
                                <label class="form-label">
                                    <i class="bi bi-calendar-x me-1"></i>Date de Fin
                                </label>
                        <input type="date" name="date_fin" class="form-control" required>
                    </div>
                </div>
                    </div>

                    <!-- Participants -->
                    <div class="form-section">
                        <h6 class="fw-bold mb-3 d-flex align-items-center">
                            <i class="bi bi-people me-2 text-primary"></i>
                            Participants
                        </h6>
                        
                        <?php if (empty($employes)): ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                Aucun employé dans votre équipe
                            </div>
                        <?php else: ?>
                            <div class="row g-2">
                            <?php foreach($employes as $emp): ?>
                                <div class="col-md-4">
                                    <div class="participant-card">
                                <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="participants[]" 
                                                   value="<?= $emp['user_id'] ?>" 
                                                   id="user_<?= $emp['user_id'] ?>">
                                            <label class="form-check-label w-100" for="user_<?= $emp['user_id'] ?>" style="cursor: pointer;">
                                                <i class="bi bi-person-circle me-2"></i>
                                                <?= htmlspecialchars($emp['nom'] . ' ' . ($emp['prenom'] ?? '')) ?>
                                    </label>
                                        </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                </div>

                    <div class="d-flex gap-3 mt-4">
                        <a href="deplacements.php" class="btn btn-outline-secondary flex-grow-1">
                            <i class="bi bi-x-circle me-2"></i>Annuler
                        </a>
                        <button type="submit" class="btn btn-submit text-white flex-grow-1">
                            <i class="bi bi-check-circle me-2"></i>Enregistrer la Mission
                        </button>
                    </div>
            </form>
        </div>
    </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validation des dates
        document.querySelector('input[name="date_debut"]').addEventListener('change', function() {
            const dateFin = document.querySelector('input[name="date_fin"]');
            if (dateFin.value && this.value > dateFin.value) {
                alert('La date de début doit être antérieure à la date de fin');
                this.value = '';
            }
        });
        
        document.querySelector('input[name="date_fin"]').addEventListener('change', function() {
            const dateDebut = document.querySelector('input[name="date_debut"]');
            if (dateDebut.value && this.value < dateDebut.value) {
                alert('La date de fin doit être postérieure à la date de début');
                this.value = '';
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
    </script>
    <script src="../../assets/js/theme.js"></script>
</body>
</html>
