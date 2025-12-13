<?php
// -------------------------------------------------------------------------
// PAGE TOUTES LES NOTIFICATIONS - MANAGER
// -------------------------------------------------------------------------
require_once __DIR__ . '/../../includes/session.php';
requireRole('manager');
require_once __DIR__ . '/../../config/Database.php';

$pdo = Database::getInstance()->getConnexion();
$managerId = $_SESSION['user_id'];
$userName = $_SESSION['user']['nom'] ?? 'Manager';

// Avatar
$stmt = $pdo->prepare("SELECT avatar FROM users WHERE user_id = ?");
$stmt->execute([$managerId]);
$userParams = $stmt->fetch();
$avatarFile = $userParams['avatar'] ?? 'default.png'; 
$avatar = '../../assets/img/' . $avatarFile;

// Récupérer toutes les réclamations non résolues
try {
    $sqlReclamations = "SELECT r.*, d.titre_dem, u.nom as employee_name
                       FROM reclamations r
                       JOIN demande d ON r.id_dem = d.id_dem
                       JOIN users u ON d.user_id = u.user_id
                       WHERE u.manager_id = ? AND r.statut = 'en_attente'
                       ORDER BY r.created_at DESC";
    $stmt = $pdo->prepare($sqlReclamations);
    $stmt->execute([$managerId]);
    $reclamations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $reclamations = [];
}

// Récupérer toutes les notifications (lues et non lues)
try {
    $sqlNotifications = "SELECT n.*, u.nom as from_user_name
                       FROM notifications n
                       LEFT JOIN users u ON n.user_id = u.user_id
                       WHERE u.manager_id = ?
                       AND n.user_id != ?
                       AND n.type NOT IN ('validation', 'rejet')
                       ORDER BY n.created_at DESC";
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
        'statut' => $rec['statut'],
        'is_read' => 0
    ];
}
foreach ($otherNotifications as $notif) {
    $allNotifications[] = [
        'id' => $notif['id'],
        'type' => $notif['type'],
        'title' => $notif['title'],
        'message' => $notif['message'],
        'created_at' => $notif['created_at'],
        'from_user_name' => $notif['from_user_name'] ?? null,
        'is_read' => $notif['is_read'] ?? 0
    ];
}

// Trier par date décroissante
usort($allNotifications, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

$notifications = $allNotifications;
$notificationCount = count(array_filter($notifications, function($n) { return $n['is_read'] == 0; }));
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Toutes les notifications - RembourseMaroc</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/dark-theme.css">
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
                    <?php 
                    // Récupérer les 10 dernières notifications non lues pour le dropdown
                    $recentNotifications = array_slice(array_filter($allNotifications, function($n) { return ($n['is_read'] ?? 0) == 0; }), 0, 10);
                    if (empty($recentNotifications)): ?>
                        <li class="px-3 py-4 text-center text-muted">
                            <i class="bi bi-bell-slash fs-4 d-block mb-2"></i>
                            <small>Aucune notification</small>
                        </li>
                    <?php else: ?>
                        <?php foreach ($recentNotifications as $notif): 
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
                <h3 class="fw-bolder m-0">Toutes les notifications</h3>
                <div class="text-muted mt-1"><?= count($notifications) ?> notification(s) au total</div>
            </div>
            <div>
                <button type="button" class="btn btn-outline-primary btn-sm" id="toggleSelectMode">
                    <i class="bi bi-check-square"></i> Sélectionner
                </button>
            </div>
        </div>

        <!-- Toolbar de sélection -->
        <div class="selection-toolbar" id="selectionToolbar" style="display: none; background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; align-items: center; justify-content: space-between;">
            <div>
                <span id="selectedCount" class="fw-semibold">0</span> notification(s) sélectionnée(s)
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="selectAllBtn">
                    <i class="bi bi-check-all"></i> Tout sélectionner
                </button>
                <button type="button" class="btn btn-sm btn-danger" id="deleteSelectedBtn" disabled>
                    <i class="bi bi-trash"></i> Supprimer
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="cancelSelectBtn">
                    Annuler
                </button>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <?php if (empty($notifications)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-bell-slash fs-1 text-muted d-block mb-3"></i>
                        <p class="text-muted">Aucune notification</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($notifications as $notif): 
                            $iconClass = match($notif['type']) {
                                'reclamation' => 'bi-exclamation-triangle text-warning',
                                'clarification' => 'bi-question-circle',
                                'validation' => 'bi-check-circle text-success',
                                'rejet' => 'bi-x-circle text-danger',
                                default => 'bi-info-circle'
                            };
                            
                            $isRead = $notif['is_read'] ?? 0;
                            $readClass = $isRead ? 'opacity-50' : '';
                            
                            // Afficher le nom de l'employé pour les réclamations
                            $employeeInfo = '';
                            if ($notif['type'] === 'reclamation' && isset($notif['employee_name'])) {
                                $employeeInfo = '<div class="text-primary small mt-1"><i class="bi bi-person"></i> ' . htmlspecialchars($notif['employee_name']) . '</div>';
                            }
                        ?>
                        <div class="list-group-item notification-item <?= $readClass ?>" 
                            style="cursor: pointer; <?= $isRead ? 'background-color: #f8f9fa;' : '' ?>"
                            data-notification-id="<?= $notif['id'] ?>"
                            data-notification-type="<?= $notif['type'] ?>"
                            <?php if ($notif['type'] === 'reclamation' && isset($notif['id_dem'])): ?>
                                data-demand-id="<?= $notif['id_dem'] ?>"
                            <?php endif; ?>>
                            <div class="d-flex align-items-start">
                                <input type="checkbox" class="form-check-input notification-checkbox" 
                                       value="<?= $notif['id'] ?>" style="display: none; margin-right: 12px; margin-top: 4px;">
                                <div class="notification-icon me-3">
                                    <i class="bi <?= $iconClass ?> fs-5"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <div class="fw-semibold"><?= $notif['title'] ?></div>
                                            <?= $employeeInfo ?>
                                            <div class="text-muted small mt-1"><?= $notif['message'] ?></div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if (!$isRead): ?>
                                                <span class="badge bg-primary">Nouveau</span>
                                            <?php endif; ?>
                                            <?php if ($notif['type'] === 'reclamation' && isset($notif['id_dem'])): ?>
                                                <a href="details_validation.php?id=<?= $notif['id_dem'] ?>" class="btn btn-sm btn-primary action-button">
                                                    <i class="bi bi-eye"></i> Voir la demande
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="text-muted" style="font-size: 0.75rem; margin-top: 8px;">
                                        <i class="bi bi-clock"></i> <?= date('d/m/Y à H:i', strtotime($notif['created_at'])) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let selectMode = false;

        document.addEventListener('DOMContentLoaded', function() {
            const toggleSelectBtn = document.getElementById('toggleSelectMode');
            const selectionToolbar = document.getElementById('selectionToolbar');
            const selectAllBtn = document.getElementById('selectAllBtn');
            const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
            const cancelSelectBtn = document.getElementById('cancelSelectBtn');
            const selectedCountSpan = document.getElementById('selectedCount');
            const checkboxes = document.querySelectorAll('.notification-checkbox');

            // Activer/désactiver le mode sélection
            toggleSelectBtn.addEventListener('click', function() {
                selectMode = !selectMode;
                selectionToolbar.style.display = selectMode ? 'flex' : 'none';
                checkboxes.forEach(cb => {
                    cb.style.display = selectMode ? 'block' : 'none';
                });
                document.querySelectorAll('.action-button').forEach(btn => {
                    btn.style.display = selectMode ? 'none' : 'inline-block';
                });
                toggleSelectBtn.innerHTML = selectMode 
                    ? '<i class="bi bi-x-square"></i> Annuler' 
                    : '<i class="bi bi-check-square"></i> Sélectionner';
                
                if (!selectMode) {
                    checkboxes.forEach(cb => cb.checked = false);
                    updateSelection();
                }
            });

            // Sélectionner tout
            selectAllBtn.addEventListener('click', function() {
                const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                checkboxes.forEach(cb => cb.checked = !allChecked);
                updateSelection();
            });

            // Annuler la sélection
            cancelSelectBtn.addEventListener('click', function() {
                toggleSelectBtn.click();
            });

            // Mettre à jour le compteur et le bouton supprimer
            function updateSelection() {
                const selected = Array.from(checkboxes).filter(cb => cb.checked);
                const count = selected.length;
                selectedCountSpan.textContent = count;
                deleteSelectedBtn.disabled = count === 0;
                
                // Ajouter/retirer la classe selected
                document.querySelectorAll('.notification-item').forEach(item => {
                    const checkbox = item.querySelector('.notification-checkbox');
                    if (checkbox && checkbox.checked) {
                        item.style.borderLeft = '3px solid #059669';
                        item.style.backgroundColor = '#f0fdf4';
                    } else {
                        item.style.borderLeft = '';
                        const isRead = item.classList.contains('opacity-50');
                        item.style.backgroundColor = isRead ? '#f8f9fa' : '';
                    }
                });
            }

            // Écouter les changements de checkboxes
            checkboxes.forEach(cb => {
                cb.addEventListener('change', updateSelection);
            });

            // Supprimer les notifications sélectionnées
            deleteSelectedBtn.addEventListener('click', function() {
                const selected = Array.from(checkboxes)
                    .filter(cb => cb.checked)
                    .map(cb => {
                        const item = document.querySelector(`[data-notification-id="${cb.value}"]`);
                        const type = item ? item.getAttribute('data-notification-type') : null;
                        return { id: cb.value, type: type };
                    });
                
                if (selected.length === 0) {
                    alert('Aucune notification sélectionnée.');
                    return;
                }

                // Filtrer les réclamations (ne peuvent pas être supprimées)
                const reclamations = selected.filter(s => s.type === 'reclamation');
                const notifications = selected.filter(s => s.type !== 'reclamation');
                
                if (reclamations.length > 0 && notifications.length === 0) {
                    alert('Les réclamations ne peuvent pas être supprimées depuis cette page.');
                    return;
                }

                let confirmMsg = `Êtes-vous sûr de vouloir supprimer ${notifications.length} notification(s) ?`;
                if (reclamations.length > 0) {
                    confirmMsg += `\n\nNote: ${reclamations.length} réclamation(s) sélectionnée(s) ne seront pas supprimées (non autorisé).`;
                }

                if (!confirm(confirmMsg)) {
                    return;
                }

                // Afficher un indicateur de chargement
                deleteSelectedBtn.disabled = true;
                deleteSelectedBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Suppression...';

                // Envoyer uniquement les IDs des notifications normales
                const formData = new FormData();
                notifications.forEach(n => formData.append('notification_ids[]', n.id));

                fetch('../../actions/delete_notifications_manager.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Supprimer visuellement les notifications supprimées
                        notifications.forEach(n => {
                            const item = document.querySelector(`[data-notification-id="${n.id}"]`);
                            if (item) {
                                item.style.transition = 'opacity 0.3s';
                                item.style.opacity = '0';
                                setTimeout(() => item.remove(), 300);
                            }
                        });
                        
                        // Désactiver le mode sélection si plus de notifications
                        if (document.querySelectorAll('.notification-item').length === 0) {
                            toggleSelectBtn.click();
                        }
                        
                        let message = data.message;
                        if (reclamations.length > 0) {
                            message += ` ${reclamations.length} réclamation(s) n'ont pas été supprimées.`;
                        }
                        alert(message);
                    } else {
                        alert('Erreur: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Une erreur est survenue lors de la suppression.');
                })
                .finally(() => {
                    deleteSelectedBtn.disabled = false;
                    deleteSelectedBtn.innerHTML = '<i class="bi bi-trash"></i> Supprimer';
                    updateSelection();
                });
            });

            // Gestion des clics sur les notifications (hors mode sélection)
            document.querySelectorAll('.notification-item').forEach(item => {
                item.addEventListener('click', async function(e) {
                    if (selectMode || e.target.closest('.notification-checkbox') || e.target.closest('.action-button')) {
                        return;
                    }
                    
                    const notificationId = this.getAttribute('data-notification-id');
                    const notificationType = this.getAttribute('data-notification-type');
                    const demandId = this.getAttribute('data-demand-id');
                    
                    // Marquer la notification comme lue
                    if (notificationId && notificationType && !this.classList.contains('opacity-50')) {
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
                                // Marquer visuellement comme lue
                                this.classList.add('opacity-50');
                                this.style.backgroundColor = '#f8f9fa';
                                
                                // Retirer le badge "Nouveau"
                                const badge = this.querySelector('.badge');
                                if (badge) {
                                    badge.remove();
                                }
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
                const notificationItems = document.querySelectorAll('.notification-item:not([style*="opacity: 0.5"]):not(.opacity-50)');
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

