<?php
// -------------------------------------------------------------------------
// PAGE : TOUTES LES NOTIFICATIONS EMPLOYÉ
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

$pdo = Database::getInstance()->getConnexion();

// --- NOTIFICATIONS ---
require_once __DIR__ . '/../../includes/employee_notifications.php';
$notifications = getEmployeeNotifications($pdo, $user_id);
$notificationCount = count($notifications);

// Récupérer TOUTES les notifications (lues et non lues)
$sqlAllNotifications = "SELECT n.*, d.titre_dem, d.status as demande_status
                        FROM notifications n
                        LEFT JOIN demande d ON n.related_id = d.id_dem
                        WHERE n.user_id = ?
                        ORDER BY n.created_at DESC
                        LIMIT 100";
$stmt = $pdo->prepare($sqlAllNotifications);
$stmt->execute([$user_id]);
$allNotifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Traitement : Marquer comme lu si demandé
if (isset($_POST['mark_read']) && is_numeric($_POST['mark_read'])) {
    $notifId = (int)$_POST['mark_read'];
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$notifId, $user_id]);
    header("Location: notifications.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mes Notifications | Rembourse Maroc</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --app-bg: #f8fafc;
            --header-bg: #ffffff;
            --header-border: #e2e8f0;
            --primary: #059669;
            --text-main: #1e293b;
            --text-light: #64748b;
            --card-border: #e2e8f0;
            --radius: 8px;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--app-bg);
            color: var(--text-main);
            font-size: 0.875rem;
            padding-top: 60px;
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

        .app-header .d-flex span {
            font-weight: 700 !important;
        }

        .brand-logo:hover {
            animation: logo-bounce 1s infinite;
            background-color: #047857;
        }

        .app-nav {
            display: flex;
            gap: 4px;
            height: 100%;
        }

        .nav-item-link {
            color: var(--text-light);
            text-decoration: none;
            padding: 0 16px;
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
            background-color: rgba(5, 150, 105, 0.04);
        }

        .avatar-circle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid #cbd5e1;
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

        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px;
        }

        .notification-card {
            background: white;
            border: 1px solid var(--card-border);
            border-radius: var(--radius);
            padding: 16px;
            margin-bottom: 12px;
            transition: all 0.2s;
        }

        .notification-card:hover {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .notification-card.unread {
            background: #f0fdf4;
            border-left: 3px solid var(--primary);
        }

        .notification-card.read {
            opacity: 0.7;
        }

        .selection-toolbar {
            background: white;
            border: 1px solid var(--card-border);
            border-radius: var(--radius);
            padding: 12px 16px;
            margin-bottom: 16px;
            display: none;
            align-items: center;
            justify-content: space-between;
        }

        .selection-toolbar.active {
            display: flex;
        }

        .notification-card.selected {
            border-color: var(--primary);
            background: #f0fdf4;
        }

        .notification-checkbox {
            margin-right: 12px;
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
            <a href="dashboard.php" class="nav-item-link"><i class="bi bi-grid-fill"></i> Tableau de bord</a>
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

    <!-- MAIN CONTENT -->
    <div class="main-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold m-0 text-dark">Mes Notifications</h4>
                <div class="text-muted small">Historique complet de toutes vos notifications</div>
            </div>
            <div>
                <button type="button" class="btn btn-outline-primary btn-sm" id="toggleSelectMode">
                    <i class="bi bi-check-square"></i> Sélectionner
                </button>
            </div>
        </div>

        <!-- Toolbar de sélection -->
        <div class="selection-toolbar" id="selectionToolbar">
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

        <?php if (empty($allNotifications)): ?>
            <div class="text-center py-5">
                <i class="bi bi-bell-slash fs-1 text-muted opacity-25 d-block mb-2"></i>
                <span class="text-muted small">Aucune notification pour le moment.</span>
            </div>
        <?php else: ?>
            <?php foreach ($allNotifications as $notif): 
                $iconClass = match($notif['type']) {
                    'clarification' => 'bi-question-circle text-warning',
                    'validation' => 'bi-check-circle text-success',
                    'rejet' => 'bi-x-circle text-danger',
                    'payment' => 'bi-wallet text-primary',
                    default => 'bi-info-circle text-info'
                };
                $isRead = (bool)$notif['is_read'];
            ?>
            <div class="notification-card <?= $isRead ? 'read' : 'unread' ?>" data-notification-id="<?= $notif['id'] ?>">
                <div class="d-flex align-items-start">
                    <input type="checkbox" class="form-check-input notification-checkbox" 
                           value="<?= $notif['id'] ?>" style="display: none;">
                    <div class="notification-icon me-3">
                        <i class="bi <?= $iconClass ?> fs-4"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="fw-semibold"><?= htmlspecialchars($notif['title']) ?></div>
                                <div class="text-muted small mt-1"><?= htmlspecialchars($notif['message']) ?></div>
                                <div class="text-muted" style="font-size: 0.7rem; margin-top: 4px;">
                                    <i class="bi bi-clock"></i> <?= date('d/m/Y à H:i', strtotime($notif['created_at'])) ?>
                                </div>
                            </div>
                            <div class="d-flex gap-2 action-buttons">
                                <?php if (!$isRead): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="mark_read" value="<?= $notif['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-check"></i> Marquer comme lu
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <?php if (isset($notif['related_id']) && $notif['related_id']): ?>
                                    <a href="details_demande.php?id=<?= $notif['related_id'] ?>" class="btn btn-sm btn-primary">
                                        <i class="bi bi-eye"></i> Voir la demande
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Script pour gérer les notifications -->
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
                selectionToolbar.classList.toggle('active', selectMode);
                checkboxes.forEach(cb => {
                    cb.style.display = selectMode ? 'block' : 'none';
                });
                document.querySelectorAll('.action-buttons').forEach(btn => {
                    btn.style.display = selectMode ? 'none' : 'flex';
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
                document.querySelectorAll('.notification-card').forEach(card => {
                    const checkbox = card.querySelector('.notification-checkbox');
                    if (checkbox && checkbox.checked) {
                        card.classList.add('selected');
                    } else {
                        card.classList.remove('selected');
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
                    .map(cb => cb.value);
                
                if (selected.length === 0) {
                    alert('Aucune notification sélectionnée.');
                    return;
                }

                if (!confirm(`Êtes-vous sûr de vouloir supprimer ${selected.length} notification(s) ?`)) {
                    return;
                }

                // Afficher un indicateur de chargement
                deleteSelectedBtn.disabled = true;
                deleteSelectedBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Suppression...';

                // Envoyer la requête
                const formData = new FormData();
                selected.forEach(id => formData.append('notification_ids[]', id));

                fetch('../../actions/delete_notifications_employee.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Supprimer les cartes sélectionnées
                        selected.forEach(id => {
                            const card = document.querySelector(`[data-notification-id="${id}"]`);
                            if (card) {
                                card.style.transition = 'opacity 0.3s';
                                card.style.opacity = '0';
                                setTimeout(() => card.remove(), 300);
                            }
                        });
                        
                        // Désactiver le mode sélection si plus de notifications
                        if (document.querySelectorAll('.notification-card').length === 0) {
                            toggleSelectBtn.click();
                        }
                        
                        alert(data.message);
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
            document.querySelectorAll('.notification-item').forEach(function(item) {
                item.addEventListener('click', function() {
                    if (selectMode) return;
                    
                    const notificationId = this.getAttribute('data-notification-id');
                    const notificationType = this.getAttribute('data-notification-type');
                    const demandId = this.getAttribute('data-demand-id');
                    
                    if (notificationId) {
                        fetch('../../actions/mark_notification_read_employee.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({id: notificationId, type: notificationType})
                        }).then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                this.style.opacity = '0.5';
                                if (demandId) {
                                    setTimeout(function() {
                                        window.location.href = 'details_demande.php?id=' + demandId;
                                    }, 300);
                                }
                            }
                        });
                    } else if (demandId) {
                        window.location.href = 'details_demande.php?id=' + demandId;
                    }
                });
            });
        });
    </script>
</body>
</html>

