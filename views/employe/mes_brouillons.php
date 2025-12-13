<?php

// -------------------------------------------------------------------------

// MES BROUILLONS - STYLE DESKTOP APP

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

// Récupération des brouillons

$stmt = $pdo->prepare("

    SELECT * FROM demande 

    WHERE user_id = ? AND status = 'Brouillon' 

    ORDER BY created_at DESC

");

$stmt->execute([$user_id]);

$brouillons = $stmt->fetchAll(PDO::FETCH_ASSOC);



// Calculs pour les KPIs (Total dormant)

$total_dormant = 0;

foreach ($brouillons as $b) {

    $total_dormant += $b['montant_total'];

}



// Helper pour simuler la complétion du dossier (pour l'UX)

function calculateCompletion($d)

{

    $score = 10; // Créé

    if (!empty($d['titre_dem'])) $score += 20;

    if ($d['montant_total'] > 0) $score += 30;

    if (!empty($d['piece_justificative'])) $score += 40;

    return $score;

}

?>



<!DOCTYPE html>

<html lang="fr">



<head>

    <meta charset="UTF-8">

    <title>Mes Brouillons | Rembourse Maroc</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">



    <style>

        :root {

            --app-bg: #f8fafc;

            --header-bg: #ffffff;

            --header-border: #e2e8f0;

            --primary: #059669;

            --primary-hover: #047857;

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



        /* --- HEADER --- */

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



        /* --- CONTENT --- */

        .main-container {

            max-width: 1440px;

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

            display: inline-flex;

            align-items: center;

            gap: 8px;

            font-size: 0.85rem;

        }



        .btn-action:hover {

            background-color: var(--primary-hover);

            color: white;

        }



        /* --- DRAFT CARD LIST --- */

        .draft-list {

            display: flex;

            flex-direction: column;

            gap: 12px;

        }



        .draft-card {

            background: white;

            border: 1px solid var(--card-border);

            border-radius: var(--radius);

            padding: 16px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            transition: transform 0.2s, box-shadow 0.2s;

        }



        .draft-card:hover {

            transform: translateY(-2px);

            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);

            border-color: #cbd5e1;

        }



        .draft-icon {

            width: 48px;

            height: 48px;

            background: #f1f5f9;

            color: var(--text-light);

            border-radius: 8px;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 1.5rem;

            flex-shrink: 0;

        }



        .draft-info {

            flex-grow: 1;

            margin: 0 20px;

        }



        .draft-title {

            font-weight: 600;

            color: var(--text-main);

            font-size: 1rem;

            margin-bottom: 4px;

        }



        .draft-meta {

            font-size: 0.8rem;

            color: var(--text-light);

            display: flex;

            gap: 15px;

            align-items: center;

        }



        .progress-mini {

            width: 100px;

            height: 6px;

            border-radius: 3px;

            background: #e2e8f0;

            overflow: hidden;

            display: inline-block;

            vertical-align: middle;

            margin-right: 8px;

        }



        .progress-bar-mini {

            height: 100%;

            background: #fbbf24;

            border-radius: 3px;

        }



        .draft-actions {

            display: flex;

            gap: 8px;

            align-items: center;

        }



        .btn-icon {

            width: 36px;

            height: 36px;

            border-radius: 6px;

            border: 1px solid transparent;

            display: flex;

            align-items: center;

            justify-content: center;

            color: var(--text-light);

            transition: 0.2s;

            background: white;

            text-decoration: none;

        }



        .btn-icon:hover {

            background: #f1f5f9;

            color: var(--text-main);

        }



        .btn-icon.delete:hover {

            background: #fee2e2;

            color: #dc2626;

        }



        .btn-resume {

            background: var(--text-main);

            color: white;

            padding: 6px 14px;

            border-radius: 6px;

            font-size: 0.8rem;

            font-weight: 500;

            text-decoration: none;

        }



        .btn-resume:hover {

            background: #0f172a;

            color: white;

        }



        /* --- WIDGETS RIGHT --- */

        .widget-box {

            background: white;

            border: 1px solid var(--card-border);

            border-radius: var(--radius);

            padding: 20px;

            margin-bottom: 20px;

        }



        .widget-title {

            font-weight: 600;

            font-size: 0.9rem;

            margin-bottom: 12px;

            color: var(--text-main);

        }



        .amount-display {

            font-size: 1.5rem;

            font-weight: 700;

            color: var(--primary);

        }

    </style>

</head>



<body>



    <!-- TOP NAV -->

    <header class="app-header">

        <div class="d-flex align-items-center gap-2">

            <div class="brand-logo">RM</div> <span class="fw-bold text-dark">RembourseMaroc</span>

        </div>

        <nav class="app-nav">

            <a href="dashboard.php" class="nav-item-link"><i class="bi bi-grid-fill"></i> Tableau de bord</a>

            <a href="nouvelle_demande.php" class="nav-item-link"><i class="bi bi-plus-circle"></i> Nouvelle demande</a>

            <a href="mes_frais.php" class="nav-item-link"><i class="bi bi-receipt"></i> Mes frais</a>

            <a href="mes_brouillons.php" class="nav-item-link active"><i class="bi bi-file-earmark"></i> Brouillons</a>

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



        <div class="page-header">

            <div>

                <h4 class="fw-bold m-0 text-dark">Mes Brouillons</h4>

                <div class="text-muted small">Reprenez vos demandes là où vous les avez laissées.</div>

            </div>

            <a href="nouvelle_demande.php" class="btn-action">

                <i class="bi bi-plus-lg"></i> Nouveau

            </a>

        </div>



        <div class="row g-4">



            <!-- LISTE PRINCIPALE -->

            <div class="col-lg-8">



                <?php if (empty($brouillons)): ?>

                    <!-- EMPTY STATE -->

                    <div class="text-center py-5 bg-white border rounded">

                        <i class="bi bi-file-earmark-check fs-1 text-muted opacity-25"></i>

                        <h6 class="fw-bold text-dark mt-3">Tout est propre !</h6>

                        <p class="text-muted small">Vous n'avez aucun brouillon en attente.</p>

                        <a href="nouvelle_demande.php" class="btn btn-outline-primary btn-sm mt-2">Commencer une demande</a>

                    </div>

                <?php else: ?>



                    <div class="draft-list">

                        <?php foreach ($brouillons as $b):

                            $percent = calculateCompletion($b);

                            $date = new DateTime($b['created_at']);

                        ?>

                            <div class="draft-card">

                                <div class="d-flex align-items-center flex-grow-1">

                                    <div class="draft-icon">

                                        <i class="bi bi-pencil-square"></i>

                                    </div>

                                    <div class="draft-info">

                                        <div class="draft-title">

                                            <?= !empty($b['titre_dem']) ? htmlspecialchars($b['titre_dem']) : '<span class="text-muted fst-italic">Sans titre...</span>' ?>

                                        </div>

                                        <div class="draft-meta">

                                            <span><i class="bi bi-calendar3 me-1"></i> Créé le

                                                <?= $date->format('d/m/Y') ?></span>

                                            <span>

                                                <div class="progress-mini">

                                                    <div class="progress-bar-mini" style="width: <?= $percent ?>%"></div>

                                                </div>

                                                <span class="small text-muted"><?= $percent ?>% complété</span>

                                            </span>

                                        </div>

                                    </div>

                                </div>



                                <div class="text-end me-4 border-end pe-4 d-none d-md-block">

                                    <div class="fw-bold text-dark fs-6"><?= number_format($b['montant_total'], 2) ?> <span

                                            class="small text-muted fw-normal">DH</span></div>

                                    <div class="small text-muted">Estimé</div>

                                </div>



                                <div class="draft-actions">

                                    <a href="modifier_brouillon.php?id=<?= $b['id_dem'] ?>" class="btn-resume">

                                        Reprendre

                                    </a>

                                    <a href="../../actions/delete_demande.php?id=<?= $b['id_dem'] ?>" class="btn-icon delete"

                                        title="Supprimer" onclick="return confirm('Supprimer ce brouillon ?');">

                                        <i class="bi bi-trash3"></i>

                                    </a>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>



                <?php endif; ?>

            </div>



            <!-- COLONNE DROITE (INFO & STATS) -->

            <div class="col-lg-4">



                <!-- Résumé Financier -->

                <div class="widget-box">

                    <div class="widget-title"><i class="bi bi-wallet2 me-2"></i>Montant en attente</div>

                    <div class="amount-display"><?= number_format($total_dormant, 2) ?> <small

                            class="text-muted fs-6">DH</small></div>

                    <p class="small text-muted mt-2 mb-0">

                        Total des frais saisis dans vos brouillons mais non encore soumis au remboursement.

                    </p>

                </div>



                <!-- Conseils -->

                <div class="widget-box bg-light border-0">

                    <div class="widget-title text-secondary">Le saviez-vous ?</div>

                    <ul class="list-unstyled small text-muted mb-0 d-flex flex-column gap-2">

                        <li><i class="bi bi-check-circle-fill text-success me-2"></i> Vos brouillons sont sauvegardés

                            automatiquement.</li>

                        <li><i class="bi bi-check-circle-fill text-success me-2"></i> N'oubliez pas de joindre vos reçus

                            avant de valider.</li>

                        <li><i class="bi bi-check-circle-fill text-success me-2"></i> Un brouillon de plus de 30 jours

                            peut être archivé.</li>

                    </ul>

                </div>



            </div>

        </div>



    </div>



    <!-- SCRIPTS -->

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
