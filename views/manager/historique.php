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

// Statistiques globales
$sqlGlobal = "SELECT 
                COUNT(DISTINCT d.id_dem) as total_demandes,
                COUNT(DISTINCT CASE WHEN d.status = 'Valide' OR d.status = 'Paye' THEN d.id_dem END) as total_validees,
                COUNT(DISTINCT CASE WHEN d.status = 'Rejete' THEN d.id_dem END) as total_rejetees,
                COALESCE(SUM(CASE WHEN d.status != 'Rejete' THEN d.montant_total ELSE 0 END), 0) as total_global
              FROM users u
              LEFT JOIN demande d ON u.user_id = d.user_id
              WHERE u.manager_id = ?";

$stmt = $pdo->prepare($sqlGlobal);
$stmt->execute([$managerId]);
$globalStats = $stmt->fetch(PDO::FETCH_ASSOC);

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

// Récupérer tous les employés pour le filtre
$sqlEmployees = "SELECT user_id, nom, prenom FROM users WHERE manager_id = ? ORDER BY nom ASC";
$stmt = $pdo->prepare($sqlEmployees);
$stmt->execute([$managerId]);
$allEmployees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les paramètres de filtrage
$filterDate = $_GET['date'] ?? '';
$filterEmployee = isset($_GET['employee']) && $_GET['employee'] !== '' ? (int)$_GET['employee'] : null;
$filterStatus = $_GET['status'] ?? '';

// Construire la requête pour toutes les demandes avec filtres
$sqlDemandes = "SELECT d.*, u.nom, u.prenom, u.user_id as employee_id
                FROM demande d
                JOIN users u ON d.user_id = u.user_id
                WHERE u.manager_id = ?";

$params = [$managerId];

if ($filterDate) {
    $sqlDemandes .= " AND DATE(d.date_dep) = ?";
    $params[] = $filterDate;
}

if ($filterEmployee !== null) {
    $sqlDemandes .= " AND d.user_id = ?";
    $params[] = $filterEmployee;
}

if ($filterStatus) {
    $sqlDemandes .= " AND d.status = ?";
    $params[] = $filterStatus;
}

$sqlDemandes .= " ORDER BY d.created_at DESC";

$stmt = $pdo->prepare($sqlDemandes);
$stmt->execute($params);
$allDemandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="<?= Lang::current() ?>">
<head>
    <meta charset="UTF-8">
    <title>Historique | Rembourse Maroc</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/dark-theme.css">
    <style>
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #059669;
        }
        .filter-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .filter-card .form-control,
        .filter-card .form-select {
            border-radius: 8px;
            border: 2px solid #e2e8f0;
            padding: 10px 15px;
        }
        .filter-card .form-control:focus,
        .filter-card .form-select:focus {
            border-color: #059669;
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
        }
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
        .form-check-input {
            cursor: pointer;
            width: 18px;
            height: 18px;
        }
        .form-check-input:checked {
            background-color: #059669;
            border-color: #059669;
        }
        #btnDeleteSelected {
            transition: all 0.3s ease;
        }
        #btnDeleteSelected:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(220, 53, 69, 0.3);
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
            <a href="historique.php" class="nav-link active"><i class="bi bi-clock-history"></i> Historique</a>
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
                <h4 class="fw-bold m-0 text-dark">Historique des Demandes</h4>
                <div class="text-muted small">Statistiques détaillées de votre équipe</div>
            </div>
            <a href="export_bilan.php" class="btn btn-primary">
                <i class="bi bi-file-earmark-pdf me-2"></i>Exporter le Bilan PDF
            </a>
        </div>

        <!-- Statistiques Globales -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="text-muted small mb-2">Total Demandes</div>
                    <div class="stat-value"><?= $globalStats['total_demandes'] ?? 0 ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="text-muted small mb-2">Validées</div>
                    <div class="stat-value text-success"><?= $globalStats['total_validees'] ?? 0 ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="text-muted small mb-2">Rejetées</div>
                    <div class="stat-value text-danger"><?= $globalStats['total_rejetees'] ?? 0 ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="text-muted small mb-2">Total Remboursé</div>
                    <div class="stat-value"><?= number_format($globalStats['total_global'] ?? 0, 2) ?> DH</div>
                </div>
            </div>
        </div>

        <!-- Filtres de recherche -->
        <div class="filter-card">
            <h6 class="fw-bold mb-3 d-flex align-items-center">
                <i class="bi bi-funnel me-2 text-primary"></i>
                Rechercher et Filtrer les Demandes
            </h6>
            <form method="GET" action="historique.php" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Date</label>
                    <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($filterDate) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Employé</label>
                    <select name="employee" class="form-select">
                        <option value="">Tous les employés</option>
                        <?php foreach($allEmployees as $emp): ?>
                            <option value="<?= $emp['user_id'] ?>" <?= $filterEmployee === $emp['user_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($emp['nom'] . ' ' . $emp['prenom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Statut</label>
                    <select name="status" class="form-select">
                        <option value="">Tous les statuts</option>
                        <option value="Brouillon" <?= $filterStatus === 'Brouillon' ? 'selected' : '' ?>>Brouillon</option>
                        <option value="Attente_Manager" <?= $filterStatus === 'Attente_Manager' ? 'selected' : '' ?>>En attente Manager</option>
                        <option value="Attente_Admin" <?= $filterStatus === 'Attente_Admin' ? 'selected' : '' ?>>En attente Admin</option>
                        <option value="Valide" <?= $filterStatus === 'Valide' ? 'selected' : '' ?>>Validé</option>
                        <option value="Rejete" <?= $filterStatus === 'Rejete' ? 'selected' : '' ?>>Rejeté</option>
                        <option value="Paye" <?= $filterStatus === 'Paye' ? 'selected' : '' ?>>Payé</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="bi bi-search me-2"></i>Rechercher
                    </button>
                    <a href="historique.php" class="btn btn-outline-secondary" title="Réinitialiser">
                        <i class="bi bi-x-circle"></i>
                    </a>
                </div>
            </form>
        </div>

        <!-- Liste de toutes les demandes filtrées -->
        <div class="mb-5">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold m-0">
                <i class="bi bi-list-ul me-2 text-primary"></i>
                Toutes les Demandes <?= ($filterDate || $filterEmployee !== null || $filterStatus) ? '<span class="badge bg-info ms-2">' . count($allDemandes) . ' résultat(s)</span>' : '' ?>
            </h5>
                <button id="btnDeleteSelected" class="btn btn-danger" style="display: none;">
                    <i class="bi bi-trash me-2"></i>Supprimer les demandes sélectionnées
                </button>
            </div>
            <div class="card-widget p-0">
                <div class="table-responsive">
                    <table class="table table-app table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4" style="width: 40px;">
                                    <input type="checkbox" id="selectAll" class="form-check-input" title="Sélectionner tout">
                                </th>
                                <th>ID</th>
                                <th>Date Dépôt</th>
                                <th>Employé</th>
                                <th>Titre</th>
                                <th>Montant</th>
                                <th>Statut</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($allDemandes)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                        Aucune demande trouvée
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($allDemandes as $dem): ?>
                                <tr>
                                    <td class="ps-4">
                                        <input type="checkbox" class="form-check-input demand-checkbox" value="<?= $dem['id_dem'] ?>" data-id="<?= $dem['id_dem'] ?>">
                                    </td>
                                    <td><?= $dem['id_dem'] ?></td>
                                    <td><?= date('d/m/Y', strtotime($dem['date_dep'])) ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="avatar-circle bg-primary text-white d-flex justify-content-center align-items-center" style="width:32px;height:32px;font-size:0.8rem;">
                                                <?= strtoupper(substr($dem['nom'], 0, 1)) ?>
                                            </div>
                                            <span class="fw-bold"><?= htmlspecialchars($dem['nom'] . ' ' . $dem['prenom']) ?></span>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($dem['titre_dem']) ?></td>
                                    <td class="fw-bold"><?= number_format($dem['montant_total'], 2) ?> DH</td>
                                    <td>
                                        <?php
                                        $badges = [
                                            'Valide' => 'bg-success',
                                            'Paye' => 'bg-primary',
                                            'Rejete' => 'bg-danger',
                                            'Attente_Manager' => 'bg-warning text-dark',
                                            'Attente_Admin' => 'bg-info text-dark',
                                            'Brouillon' => 'bg-secondary'
                                        ];
                                        $badgeClass = $badges[$dem['status']] ?? 'bg-secondary';
                                        $statusLabels = [
                                            'Valide' => 'Validé',
                                            'Paye' => 'Payé',
                                            'Rejete' => 'Rejeté',
                                            'Attente_Manager' => 'En attente Manager',
                                            'Attente_Admin' => 'En attente Admin',
                                            'Brouillon' => 'Brouillon'
                                        ];
                                        $statusLabel = $statusLabels[$dem['status']] ?? $dem['status'];
                                        ?>
                                        <span class="badge <?= $badgeClass ?>"><?= $statusLabel ?></span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <a href="details_employe.php?id=<?= $dem['employee_id'] ?>" class="btn btn-sm btn-outline-primary" title="Voir détails">
                                            <i class="bi bi-eye"></i>
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

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Gestion de la sélection multiple
            const selectAllCheckbox = document.getElementById('selectAll');
            const demandCheckboxes = document.querySelectorAll('.demand-checkbox');
            const btnDeleteSelected = document.getElementById('btnDeleteSelected');
            
            // Sélectionner/désélectionner tout
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    demandCheckboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                    updateDeleteButton();
                });
            }
            
            // Mettre à jour le bouton de suppression et la case "tout sélectionner"
            function updateDeleteButton() {
                const selected = document.querySelectorAll('.demand-checkbox:checked');
                if (selected.length > 0) {
                    btnDeleteSelected.style.display = 'block';
                    btnDeleteSelected.innerHTML = `<i class="bi bi-trash me-2"></i>Supprimer ${selected.length} demande(s) sélectionnée(s)`;
                } else {
                    btnDeleteSelected.style.display = 'none';
                }
                
                // Mettre à jour la case "tout sélectionner"
                if (selectAllCheckbox) {
                    const total = demandCheckboxes.length;
                    const checked = selected.length;
                    selectAllCheckbox.checked = total > 0 && checked === total;
                    selectAllCheckbox.indeterminate = checked > 0 && checked < total;
                }
            }
            
            // Écouter les changements sur chaque case
            demandCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateDeleteButton);
            });
            
            // Suppression des demandes sélectionnées
            btnDeleteSelected.addEventListener('click', async function() {
                const selected = Array.from(document.querySelectorAll('.demand-checkbox:checked')).map(cb => cb.value);
                
                if (selected.length === 0) {
                    alert('Aucune demande sélectionnée.');
                    return;
                }
                
                if (!confirm(`Êtes-vous sûr de vouloir supprimer ${selected.length} demande(s) ? Cette action est irréversible.`)) {
                    return;
                }
                
                // Désactiver le bouton pendant le traitement
                this.disabled = true;
                this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Suppression...';
                
                try {
                    const response = await fetch('../../actions/delete_demandes_manager.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'ids=' + encodeURIComponent(JSON.stringify(selected))
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        alert(result.message);
                        // Recharger la page pour afficher les changements
                        window.location.reload();
                    } else {
                        alert('Erreur : ' + result.message);
                        this.disabled = false;
                        this.innerHTML = `<i class="bi bi-trash me-2"></i>Supprimer ${selected.length} demande(s) sélectionnée(s)`;
                    }
                } catch (error) {
                    console.error('Erreur:', error);
                    alert('Une erreur est survenue lors de la suppression.');
                    this.disabled = false;
                    this.innerHTML = `<i class="bi bi-trash me-2"></i>Supprimer ${selected.length} demande(s) sélectionnée(s)`;
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
        });
    </script>
    <script src="../../assets/js/theme.js"></script>
</body>
</html>

