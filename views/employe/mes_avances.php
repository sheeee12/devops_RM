<?php

// -------------------------------------------------------------------------

// VUE : MES AVANCES (STYLE DESKTOP APP)

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

$db = Database::getInstance()->getConnexion();

// --- NOTIFICATIONS ---
require_once __DIR__ . '/../../includes/employee_notifications.php';
$notifications = getEmployeeNotifications($db, $user_id);
$notificationCount = count($notifications);

// Récupération de l'historique des avances

$stmt = $db->prepare("SELECT * FROM avances WHERE user_id = ? ORDER BY created_at DESC");

$stmt->execute([$user_id]);

$avances = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper pour les badges de statut

function getAdvanceStatusBadge($status) {

    $styles = [

        'En_Attente' => ['bg' => '#fef3c7', 'text' => '#b45309', 'icon' => 'bi-hourglass-split', 'label' => 'Validation Manager'],

        'Valide'     => ['bg' => '#dcfce7', 'text' => '#15803d', 'icon' => 'bi-check-circle', 'label' => 'Validé (En attente virement)'],

        'Paye'       => ['bg' => '#e0f2fe', 'text' => '#0369a1', 'icon' => 'bi-wallet-fill', 'label' => 'Virement Effectué'],

        'Rejete'     => ['bg' => '#fee2e2', 'text' => '#b91c1c', 'icon' => 'bi-x-circle', 'label' => 'Refusé']

    ];

    $s = $styles[$status] ?? $styles['En_Attente'];

    return sprintf('<span class="status-pill" style="background:%s; color:%s"><i class="bi %s me-1"></i>%s</span>', $s['bg'], $s['text'], $s['icon'], $s['label']);

}

?>

<!DOCTYPE html>

<html lang="fr">

<head>

    <meta charset="UTF-8">

    <title>Mes Avances | Rembourse Maroc</title>

    <!-- Bootstrap & Icons -->

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <!-- Font Inter -->

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

        /* Espace pour le header fixe */

    }

    /* --- HEADER NAVIGATION --- */

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

    /* --- CONTENU --- */

    .main-container {

        max-width: 1200px;

        margin: 0 auto;

        padding: 24px;

    }

    .page-header {

        display: flex;

        justify-content: space-between;

        align-items: center;

        margin-bottom: 24px;

    }

    .btn-action {

        background-color: var(--primary);

        color: white;

        padding: 8px 16px;

        border-radius: 6px;

        font-weight: 500;

        text-decoration: none;

        border: none;

        display: inline-flex;

        align-items: center;

        gap: 8px;

        font-size: 0.85rem;

        transition: 0.2s;

    }

    .btn-action:hover {

        background-color: #047857;

        color: white;

        transform: translateY(-1px);

    }

    /* --- TABLEAU --- */

    .table-container {

        background: white;

        border: 1px solid var(--card-border);

        border-radius: var(--radius);

        overflow: hidden;

        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);

    }

    .table-app {

        width: 100%;

        border-collapse: separate;

        border-spacing: 0;

    }

    .table-app th {

        background: #f8fafc;

        color: var(--text-light);

        font-weight: 600;

        font-size: 0.75rem;

        text-transform: uppercase;

        padding: 12px 16px;

        border-bottom: 1px solid var(--card-border);

        text-align: left;

    }

    .table-app td {

        padding: 12px 16px;

        border-bottom: 1px solid var(--card-border);

        vertical-align: middle;

        color: var(--text-main);

    }

    .table-app tr:last-child td {

        border-bottom: none;

    }

    .table-app tr:hover td {

        background-color: #f8fafc;

    }

    .status-pill {

        padding: 4px 10px;

        border-radius: 12px;

        font-size: 0.75rem;

        font-weight: 600;

        display: inline-flex;

        align-items: center;

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
            <a href="mes_avances.php" class="nav-item-link active"><i class="bi bi-cash-stack"></i> Avances</a>
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

        <!-- HEADER PAGE -->

        <div class="page-header">

            <div>

                <h4 class="fw-bold m-0 text-dark">Avances sur frais</h4>

                <div class="text-muted small">Consultez l'historique et demandez des avances de trésorerie.</div>

            </div>

            <button class="btn-action" data-bs-toggle="modal" data-bs-target="#newAdvanceModal">

                <i class="bi bi-plus-lg"></i> Demander une avance

            </button>

        </div>

        <!-- NOTIFICATIONS -->

        <?php if (isset($_SESSION['success'])): ?>

        <div class="alert alert-success border-0 shadow-sm mb-4 small bg-success-subtle text-success">

            <i class="bi bi-check-circle-fill me-2"></i><?= $_SESSION['success']; unset($_SESSION['success']); ?>

        </div>

        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>

        <div class="alert alert-danger border-0 shadow-sm mb-4 small bg-danger-subtle text-danger">

            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?>

        </div>

        <?php endif; ?>

        <!-- TABLEAU DES DONNÉES -->

        <div class="table-container">

            <table class="table-app">

                <thead>

                    <tr>

                        <th style="width: 15%;">Date Demande</th>

                        <th style="width: 35%;">Motif / Mission</th>

                        <th style="width: 15%;">Date Besoin</th>

                        <th style="width: 15%;">Montant</th>

                        <th style="width: 20%;">Statut</th>

                    </tr>

                </thead>

                <tbody>

                    <?php if (empty($avances)): ?>

                    <tr>

                        <td colspan="5" class="text-center py-5">

                            <i class="bi bi-wallet2 fs-1 text-muted opacity-25 d-block mb-2"></i>

                            <span class="text-muted small">Aucune avance enregistrée pour le moment.</span>

                        </td>

                    </tr>

                    <?php else: ?>

                    <?php foreach ($avances as $av): ?>

                    <tr>

                        <td class="text-secondary small">

                            <?= date('d/m/Y', strtotime($av['created_at'])) ?>

                        </td>

                        <td>

                            <span class="fw-medium text-dark"><?= htmlspecialchars($av['motif']) ?></span>

                        </td>

                        <td class="small">

                            <i class="bi bi-calendar4-event me-1 text-muted"></i>

                            <?= date('d/m/Y', strtotime($av['date_besoin'])) ?>

                        </td>

                        <td>

                            <span class="fw-bold text-dark"><?= number_format($av['montant'], 2) ?></span> <small

                                class="text-secondary">DH</small>

                        </td>

                        <td>

                            <?= getAdvanceStatusBadge($av['status']) ?>

                        </td>

                    </tr>

                    <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

    <!-- MODAL NOUVELLE AVANCE -->

    <div class="modal fade" id="newAdvanceModal" tabindex="-1">

        <div class="modal-dialog modal-dialog-centered">

            <div class="modal-content border-0 shadow-lg rounded-3">

                <div class="modal-header border-bottom-0">

                    <h5 class="modal-title fw-bold">Nouvelle demande d'avance</h5>

                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>

                </div>

                <div class="modal-body pt-0">

                    <form action="../../actions/submit_avance.php" method="POST">

                        <div class="alert alert-info border-0 bg-info-subtle small text-info mb-3">

                            <i class="bi bi-info-circle-fill me-2"></i>

                            Ce montant sera <strong>déduit</strong> de votre prochaine note de frais lors de la

                            régularisation.

                        </div>

                        <div class="mb-3">

                            <label class="form-label small fw-bold text-secondary">Montant souhaité (DH) <span

                                    class="text-danger">*</span></label>

                            <div class="input-group">

                                <input type="number" name="montant" class="form-control" placeholder="Ex: 2000" min="1"

                                    step="0.01" required>

                                <span class="input-group-text bg-light text-muted">DH</span>

                            </div>

                        </div>

                        <div class="mb-3">

                            <label class="form-label small fw-bold text-secondary">Date du besoin <span

                                    class="text-danger">*</span></label>

                            <input type="date" name="date_besoin" class="form-control"

                                value="<?= date('Y-m-d', strtotime('+7 days')) ?>" required>

                            <div class="form-text small">Prévoyez un délai de traitement de 48h.</div>

                        </div>

                        <div class="mb-4">

                            <label class="form-label small fw-bold text-secondary">Motif de la demande <span

                                    class="text-danger">*</span></label>

                            <textarea name="motif" class="form-control" rows="3"

                                placeholder="Ex: Réservation billets d'avion pour conférence Paris..."

                                required></textarea>

                        </div>

                        <div class="d-grid">

                            <button type="submit" class="btn btn-action justify-content-center py-2">

                                <i class="bi bi-send-fill me-2"></i>Soumettre la demande

                            </button>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Script pour gérer les notifications -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.notification-item').forEach(function(item) {
                item.addEventListener('click', function() {
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
