<?php
// -------------------------------------------------------------------------
// PAGE DE DÉTAILS D'UNE DEMANDE - MANAGER
// -------------------------------------------------------------------------

session_start();
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../includes/security.php';

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

// Récupérer l'ID de la demande
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: dashboard.php');
    exit;
}

$demandeId = (int)$_GET['id'];

// Récupérer les détails de la demande
$sqlDemande = "SELECT d.*, u.nom as user_name, u.email, u.avatar as user_avatar, t.nom_team
               FROM demande d
               JOIN users u ON d.user_id = u.user_id
               LEFT JOIN teams t ON u.team_id = t.team_id
               WHERE d.id_dem = ? AND u.manager_id = ?";
$stmt = $pdo->prepare($sqlDemande);
$stmt->execute([$demandeId, $managerId]);
$demande = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$demande) {
    header('Location: dashboard.php');
    exit;
}

// Récupérer les lignes de dépenses (expense_line)
$sqlLines = "SELECT el.*, c.nom_categ, c.plafond_max 
             FROM expense_line el
             JOIN categories c ON el.id_categ = c.id_categ
             WHERE el.id_dem = ?
             ORDER BY el.date_depense ASC";
$stmt = $pdo->prepare($sqlLines);
$stmt->execute([$demandeId]);
$expenseLines = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fonction Badge
function getStatusBadge($status) {
    $badges = [
        'Brouillon' => '<span class="badge bg-secondary">Brouillon</span>',
        'Attente_Manager' => '<span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>À Valider</span>',
        'Attente_Admin' => '<span class="badge bg-info text-dark"><i class="bi bi-clock me-1"></i>En attente Admin</span>',
        'Valide' => '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Validé</span>',
        'Rejete' => '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Rejeté</span>',
        'Paye' => '<span class="badge bg-primary"><i class="bi bi-wallet me-1"></i>Payé</span>'
    ];
    return $badges[$status] ?? '<span class="badge bg-secondary">' . htmlspecialchars($status) . '</span>';
}

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
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Détails Demande #<?= $demandeId ?> | Rembourse Maroc</title>
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
        .proof-image {
            max-width: 100%;
            max-height: 500px;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.3s;
        }
        .proof-image:hover {
            transform: scale(1.05);
        }
        .expense-line-card {
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }
        .expense-line-card:hover {
            transform: translateX(4px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .proof-modal-img {
            max-width: 100%;
            max-height: 80vh;
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
                                'clarification' => 'bi-question-circle text-info',
                                'validation' => 'bi-check-circle text-success',
                                'rejet' => 'bi-x-circle text-danger',
                                default => 'bi-info-circle text-primary'
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
        
        <!-- Bouton Retour -->
        <div class="mb-4">
            <a href="dashboard.php" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left me-2"></i>Retour au Dashboard
            </a>
        </div>

        <!-- EN-TÊTE DE LA DEMANDE -->
        <div class="card-widget mb-4">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <h4 class="fw-bold m-0 text-dark">Demande #<?= $demandeId ?></h4>
                    <div class="text-muted small mt-1"><?= htmlspecialchars($demande['titre_dem'] ?? 'Sans titre') ?></div>
                </div>
                <div class="text-end">
                    <?= getStatusBadge($demande['status']) ?>
                </div>
            </div>

            <div class="row g-4 mt-2">
                <div class="col-md-6">
                    <div class="d-flex align-items-center mb-3">
                        <?php if (!empty($demande['user_avatar']) && $demande['user_avatar'] != 'default.png'): ?>
                            <img src="../../assets/img/<?= htmlspecialchars($demande['user_avatar']) ?>" class="rounded-circle me-3" width="50" height="50" alt="">
                        <?php else: ?>
                            <div class="avatar-circle bg-light text-primary d-flex align-items-center justify-content-center me-3 fw-bold" style="width:50px;height:50px;">
                                <?= strtoupper(substr($demande['user_name'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        <div>
                            <div class="fw-bold"><?= htmlspecialchars($demande['user_name']) ?></div>
                            <div class="text-muted small"><?= htmlspecialchars($demande['email']) ?></div>
                            <?php if ($demande['nom_team']): ?>
                                <div class="text-muted small"><i class="bi bi-people"></i> <?= htmlspecialchars($demande['nom_team']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="text-muted small">Date de dépôt</div>
                            <div class="fw-bold"><i class="bi bi-calendar me-2"></i><?= date('d/m/Y', strtotime($demande['date_dep'])) ?></div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small">Montant total</div>
                            <div class="fw-bold fs-5 text-primary"><?= number_format($demande['montant_total'], 2) ?> DH</div>
                        </div>
                        <?php if ($demande['motif_rejet']): ?>
                        <div class="col-12">
                            <div class="alert alert-danger">
                                <strong><i class="bi bi-exclamation-triangle me-2"></i>Motif de rejet :</strong><br>
                                <?= htmlspecialchars($demande['motif_rejet']) ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- LIGNES DE DÉPENSES -->
        <div class="card-widget mb-4">
            <div class="px-4 py-3 border-bottom bg-white d-flex justify-content-between align-items-center">
                <span class="fw-bold text-dark" style="font-size: 1.1rem;"><i class="bi bi-list-ul text-primary me-2"></i>Lignes de Dépenses</span>
                <span class="badge bg-primary"><?= count($expenseLines) ?> ligne(s)</span>
            </div>
            <div class="p-4">
                <?php if (empty($expenseLines)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                        <p>Aucune ligne de dépense trouvée.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($expenseLines as $line): 
                        $badgePlafond = '';
                        if ($line['montant'] > $line['plafond_max']) {
                            $badgePlafond = '<span class="badge bg-danger ms-2"><i class="bi bi-exclamation-triangle"></i> Dépassement Plafond</span>';
                        }
                        
                        $detailsHtml = '';
                        if ($line['details_specifiques']) {
                            $json = json_decode($line['details_specifiques'], true);
                            if ($json && is_array($json)) {
                                $detailsHtml = '<div class="mt-2 p-2 bg-light rounded small">';
                                foreach ($json as $k => $v) {
                                    $detailsHtml .= '<strong>' . ucfirst(str_replace('_', ' ', $k)) . ':</strong> ' . htmlspecialchars($v) . '<br>';
                                }
                                $detailsHtml .= '</div>';
                            }
                        }
                    ?>
                    <div class="expense-line-card card mb-3 border shadow-sm" data-proof="<?= htmlspecialchars($line['justificatif_path'] ?? '') ?>">
                        <div class="card-body p-4">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <div class="d-flex align-items-center mb-2">
                                        <h5 class="fw-bold m-0 me-2"><?= htmlspecialchars($line['nom_categ']) ?></h5>
                                        <?= $badgePlafond ?>
                                    </div>
                                    <div class="text-muted small mb-2">
                                        <i class="bi bi-calendar me-1"></i>Date : <?= date('d/m/Y', strtotime($line['date_depense'])) ?>
                                    </div>
                                    <?php if ($detailsHtml): ?>
                                        <?= $detailsHtml ?>
                                    <?php endif; ?>
                                    <div class="mt-2">
                                        <small class="text-muted">Plafond autorisé : <strong><?= number_format($line['plafond_max'], 2) ?> DH</strong></small>
                                    </div>
                                </div>
                                <div class="col-md-4 text-end">
                                    <div class="fw-bold fs-4 text-primary mb-2"><?= number_format($line['montant'], 2) ?> DH</div>
                                    <?php if ($line['justificatif_path'] && $line['justificatif_path'] != 'null'): ?>
                                        <button class="btn btn-sm btn-outline-primary" onclick="showProof('<?= htmlspecialchars($line['justificatif_path']) ?>')">
                                            <i class="bi bi-image me-1"></i>Voir la preuve
                                        </button>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Aucune preuve</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- Modal pour afficher les preuves -->
    <div class="modal fade" id="proofModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-image me-2"></i>Justificatif</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="proofImage" src="" alt="Justificatif" class="proof-modal-img">
                    <div id="noProofText" class="text-muted py-5" style="display: none;">
                        <i class="bi bi-image fs-1 d-block mb-3"></i>
                        Aucun justificatif disponible
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function showProof(proofPath) {
            const modal = new bootstrap.Modal(document.getElementById('proofModal'));
            const img = document.getElementById('proofImage');
            const noProof = document.getElementById('noProofText');
            
            if (proofPath && proofPath !== 'null' && proofPath !== '') {
                img.src = '../../uploads/proofs/' + proofPath;
                img.style.display = 'block';
                noProof.style.display = 'none';
            } else {
                img.style.display = 'none';
                noProof.style.display = 'block';
            }
            
            modal.show();
        }

        // Gestion des notifications - Marquer comme lue au clic
        document.addEventListener('DOMContentLoaded', function() {
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

