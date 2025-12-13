<?php
/**
 * Fonction pour récupérer les notifications d'un employé
 * À inclure dans toutes les pages employé
 */
function getEmployeeNotifications($pdo, $user_id) {
    // Créer la table notifications si elle n'existe pas
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            type ENUM('clarification', 'validation', 'rejet', 'payment', 'reclamation_reply') NOT NULL,
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

    // Récupérer les notifications de l'employé
    try {
        $sqlNotifications = "SELECT n.*, d.titre_dem, d.status as demande_status
                            FROM notifications n
                            LEFT JOIN demande d ON n.related_id = d.id_dem
                            WHERE n.user_id = ?
                            AND n.is_read = 0
                            ORDER BY n.created_at DESC
                            LIMIT 10";
        $stmt = $pdo->prepare($sqlNotifications);
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Fonction pour afficher la barre de notifications dans le header
 */
function renderNotificationBell($notifications) {
    $notificationCount = count($notifications);
    ob_start();
    ?>
    <!-- Barre de notifications -->
    <div class="dropdown position-relative">
        <a href="#" class="notification-bell position-relative text-decoration-none" data-bs-toggle="dropdown" id="notificationDropdown" style="color: var(--text-light); font-size: 1.25rem;">
            <i class="bi bi-bell"></i>
            <?php if ($notificationCount > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem; min-width: 18px; height: 18px; display: flex; align-items: center; justify-content: center;">
                    <?= $notificationCount > 9 ? '9+' : $notificationCount ?>
                </span>
            <?php endif; ?>
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
                        'clarification' => 'bi-question-circle text-warning',
                        'validation' => 'bi-check-circle text-success',
                        'rejet' => 'bi-x-circle text-danger',
                        'payment' => 'bi-wallet text-primary',
                        'reclamation_reply' => 'bi-chat-dots text-info',
                        default => 'bi-info-circle text-info'
                    };
                ?>
                <li class="notification-item px-3 py-2 border-bottom" style="cursor: pointer;" 
                    data-notification-id="<?= $notif['id'] ?>"
                    data-notification-type="<?= $notif['type'] ?>"
                    <?php if (isset($notif['related_id']) && $notif['related_id']): ?>
                        <?php if ($notif['type'] === 'reclamation_reply'): ?>
                            data-reclamation-id="<?= $notif['related_id'] ?>"
                        <?php else: ?>
                            data-demand-id="<?= $notif['related_id'] ?>"
                        <?php endif; ?>
                    <?php endif; ?>>
                    <div class="d-flex align-items-start">
                        <div class="notification-icon me-3">
                            <i class="bi <?= $iconClass ?> fs-5"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-semibold small"><?= htmlspecialchars($notif['title']) ?></div>
                            <div class="text-muted small mt-1"><?= htmlspecialchars($notif['message']) ?></div>
                            <div class="text-muted" style="font-size: 0.7rem; margin-top: 4px;">
                                <i class="bi bi-clock"></i> <?= date('d/m/Y H:i', strtotime($notif['created_at'])) ?>
                            </div>
                        </div>
                    </div>
                </li>
                <?php endforeach; ?>
            <?php endif; ?>
            <li class="px-3 py-2 border-top bg-light text-center">
                <a href="notifications.php" class="small text-primary text-decoration-none fw-semibold">
                    <i class="bi bi-list-ul me-1"></i>Voir toutes les notifications
                </a>
            </li>
        </ul>
    </div>
    <?php
    return ob_get_clean();
}
?>






