<?php

// -------------------------------------------------------------------------

// VUE : DÉTAILS D'UNE DEMANDE (STYLE UNIFIÉ + LOGO ANIMÉ)

// -------------------------------------------------------------------------

require_once __DIR__ . '/../../includes/session.php';

requireRole('employee');

require_once __DIR__ . '/../../config/Database.php';



// Vérification ID

if (!isset($_GET['id']) || empty($_GET['id'])) {

    header("Location: dashboard.php");

    exit();

}



$id_dem = intval($_GET['id']);

$user_id = $_SESSION['user']['user_id'];

$user_name = $_SESSION['user']['nom'];

// Gestion Avatar

$avatar_bdd = $_SESSION['user']['avatar'] ?? 'default.png';

$chemin_physique = __DIR__ . '/../../assets/img/' . $avatar_bdd;

$avatar = (file_exists($chemin_physique) && !empty($avatar_bdd)) ? '../../assets/img/' . $avatar_bdd : '../../assets/img/default.png';

$user_role_raw = $_SESSION['user']['role'];

$role_display = ($user_role_raw === 'employee') ? 'Collaborateur' : ucfirst($user_role_raw);



$db = Database::getInstance()->getConnexion();

// --- NOTIFICATIONS ---
require_once __DIR__ . '/../../includes/employee_notifications.php';
$notifications = getEmployeeNotifications($db, $user_id);
$notificationCount = count($notifications);



// -------------------------------------------------------------------------

// RÉCUPÉRATION DEMANDE + AVANCE

// -------------------------------------------------------------------------

$sql = "

    SELECT d.*, a.montant as montant_avance

    FROM demande d

    LEFT JOIN avances a ON d.avance_id = a.id_avance

    WHERE d.id_dem = ? AND d.user_id = ?

";

$stmt = $db->prepare($sql);

$stmt->execute([$id_dem, $user_id]);

$demande = $stmt->fetch(PDO::FETCH_ASSOC);



if (!$demande) {

    $_SESSION['error'] = "Demande introuvable ou accès refusé.";

    header("Location: dashboard.php");

    exit();

}



// -------------------------------------------------------------------------

// RÉCUPÉRATION LIGNES DE FRAIS

// -------------------------------------------------------------------------

$sqlLines = "

    SELECT el.*, c.nom_categ 

    FROM expense_line el

    JOIN categories c ON el.id_categ = c.id_categ

    WHERE el.id_dem = ?

";

$stmtLines = $db->prepare($sqlLines);

$stmtLines->execute([$id_dem]);

$lignes = $stmtLines->fetchAll(PDO::FETCH_ASSOC);



// Calculs pour l'affichage

$total_frais = 0;

foreach ($lignes as $l) {

    $total_frais += $l['montant'] ?? 0;

}



$montant_avance = $demande['montant_avance'] ?? 0;

$net_a_payer = $total_frais - $montant_avance;



// Fonction helper pour les badges (Même style que mes_frais.php)

function getStatusBadge($status)

{

    $styles = [

        'Valide' => ['bg' => '#dcfce7', 'text' => '#15803d', 'icon' => 'bi-check-circle-fill', 'label' => 'Validé'],

        'Paye' => ['bg' => '#e0f2fe', 'text' => '#0369a1', 'icon' => 'bi-wallet-fill', 'label' => 'Remboursé'],

        'Rejete' => ['bg' => '#fee2e2', 'text' => '#b91c1c', 'icon' => 'bi-x-circle-fill', 'label' => 'Rejeté'],

        'Attente_Manager' => ['bg' => '#fef3c7', 'text' => '#b45309', 'icon' => 'bi-hourglass-split', 'label' => 'Valid. Manager'],

        'Attente_Admin' => ['bg' => '#ffedd5', 'text' => '#c2410c', 'icon' => 'bi-building', 'label' => 'Valid. Finance'],

        'Brouillon' => ['bg' => '#f1f5f9', 'text' => '#475569', 'icon' => 'bi-pencil', 'label' => 'Brouillon']

    ];

    $s = $styles[$status] ?? $styles['Brouillon'];

    return sprintf('<span class="status-pill" style="background:%s; color:%s"><i class="bi %s me-1"></i>%s</span>', $s['bg'], $s['text'], $s['icon'], $s['label']);

}

?>



<!DOCTYPE html>

<html lang="fr">



<head>

    <meta charset="UTF-8">

    <title>Détails #<?= $id_dem ?> | Rembourse Maroc</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>

        :root {

            --app-bg: #f8fafc;

            --header-bg: #ffffff;

            --header-border: #e2e8f0;

            --primary: #059669;

            --primary-dark: #047857;

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



        /* --- HEADER & LOGO ANIMATION --- */

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



        /* Logo Container */

        .logo-container {

            display: flex;

            align-items: center;

            gap: 10px;

            text-decoration: none;

        }



        /* Effet 3D sur le logo */

        .brand-logo {

            width: 36px;

            height: 36px;

            background: var(--primary);

            color: white;

            border-radius: 8px;

            display: flex;

            align-items: center;

            justify-content: center;

            font-weight: 800;

            font-size: 1rem;

            box-shadow: 0 4px 0 var(--primary-dark);

            /* L'effet 3D */

            transition: transform 0.1s, box-shadow 0.1s;

            position: relative;

            z-index: 2;

            /* Au-dessus du texte */

        }

        .app-header .d-flex span {
            font-weight: 700 !important;
        }



        /* Petit effet clic sur le logo */

        .logo-container:active .brand-logo {

            transform: translateY(2px);

            box-shadow: 0 2px 0 var(--primary-dark);

        }



        /* Wrapper pour le texte qui glisse */

        .brand-text-wrapper {

            overflow: hidden;

            width: 0;

            /* Caché au départ */

            opacity: 0;

            white-space: nowrap;

            animation: slideTextOut 0.8s cubic-bezier(0.25, 1, 0.5, 1) forwards;

            animation-delay: 0.3s;

            /* Petit délai après chargement */

        }



        .brand-text {

            font-weight: 700;

            font-size: 1.1rem;

            color: var(--text-main);

            padding-right: 10px;

        }



        /* Animation Keyframes */

        @keyframes slideTextOut {

            from {

                width: 0;

                opacity: 0;

                transform: translateX(-20px);

            }



            to {

                width: 160px;

                /* Largeur suffisante pour le texte */

                opacity: 1;

                transform: translateX(0);

            }

        }



        /* --- NAVIGATION --- */

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



        /* --- LAYOUT & CARDS --- */

        .main-container {

            max-width: 1200px;

            margin: 0 auto;

            padding: 24px;

        }



        .card-detail {

            background: white;

            border: 1px solid var(--card-border);

            border-radius: var(--radius);

            margin-bottom: 24px;

            overflow: hidden;

            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);

        }



        .card-header-custom {

            padding: 16px 20px;

            border-bottom: 1px solid var(--card-border);

            background-color: #fff;

            display: flex;

            justify-content: space-between;

            align-items: center;

        }



        .section-title {

            font-weight: 600;

            margin: 0;

            color: var(--text-main);

            display: flex;

            align-items: center;

            gap: 8px;

            font-size: 0.95rem;

        }



        /* --- TABLES --- */

        .table-app {

            width: 100%;

            border-collapse: separate;

            border-spacing: 0;

        }



        .table-app th {

            font-size: 0.75rem;

            text-transform: uppercase;

            color: var(--text-light);

            font-weight: 600;

            background: #f8fafc;

            border-bottom: 1px solid var(--card-border);

            padding: 12px 20px;

            text-align: left;

        }



        .table-app td {

            padding: 12px 20px;

            vertical-align: middle;

            border-bottom: 1px solid var(--card-border);

            color: var(--text-main);

        }



        .table-app tr:last-child td {

            border-bottom: none;

        }



        .btn-back {

            color: var(--text-light);

            text-decoration: none;

            display: inline-flex;

            align-items: center;

            gap: 6px;

            margin-bottom: 16px;

            font-weight: 500;

            transition: color 0.2s;

        }



        .btn-back:hover {

            color: var(--primary);

        }



        .status-pill {

            padding: 4px 12px;

            border-radius: 20px;

            font-size: 0.8rem;

            font-weight: 600;

            display: inline-flex;

            align-items: center;

        }



        .summary-row {

            display: flex;

            justify-content: space-between;

            padding: 10px 0;

            font-size: 0.9rem;

            color: var(--text-light);

        }



        .summary-row.total {

            border-top: 2px dashed var(--card-border);

            margin-top: 10px;

            padding-top: 16px;

            font-weight: 700;

            color: var(--text-main);

            font-size: 1.1rem;

        }



        .avatar-circle {

            width: 32px;

            height: 32px;

            border-radius: 50%;

            object-fit: cover;

            border: 1px solid #cbd5e1;

        }



        .info-label {

            display: block;

            font-size: 0.75rem;

            text-transform: uppercase;

            color: var(--text-light);

            font-weight: 700;

            margin-bottom: 4px;

        }



        .info-value {

            font-weight: 500;

            color: var(--text-main);

            font-size: 0.95rem;

        }

    </style>

</head>



<body>



    <!-- TOP NAV (Identique à Mes Frais avec Logo Animé) -->

    <header class="app-header">

        <div class="d-flex align-items-center gap-2">
            <div class="brand-logo">RM</div> <span class="fw-bold text-dark">RembourseMaroc</span>
        </div>



        <nav class="app-nav">

            <a href="dashboard.php" class="nav-item-link"><i class="bi bi-grid-fill"></i> Tableau de bord</a>

            <a href="nouvelle_demande.php" class="nav-item-link"><i class="bi bi-plus-circle"></i> Nouvelle demande</a>

            <!-- Active car on est dans le détail d'un frais -->

            <a href="mes_frais.php" class="nav-item-link active"><i class="bi bi-receipt"></i> Mes frais</a>

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



    <div class="main-container">



        <a href="mes_frais.php" class="btn-back"><i class="bi bi-arrow-left"></i> Retour à mes frais</a>



        <!-- EN-TÊTE PAGE -->

        <div class="d-flex justify-content-between align-items-start mb-4">

            <div>

                <h2 class="fw-bold mb-1 text-dark">Demande #<?= str_pad($id_dem, 5, '0', STR_PAD_LEFT) ?></h2>

                <div class="text-muted small">Créée le <?= date('d/m/Y à H:i', strtotime($demande['created_at'])) ?>

                </div>

            </div>

            <div class="text-end">

                <?= getStatusBadge($demande['status']) ?>

                <div class="mt-3 d-flex gap-2 justify-content-end">

                    <button class="btn btn-sm btn-white border shadow-sm" onclick="window.print()">

                        <i class="bi bi-printer me-1"></i> Imprimer

                    </button>

                    <?php if ($demande['status'] !== 'Brouillon'): ?>

                        <a href="../../actions/export_pdf.php?id=<?= $id_dem ?>" target="_blank"

                            class="btn btn-sm btn-outline-primary shadow-sm bg-white">

                            <i class="bi bi-file-earmark-pdf me-1"></i> PDF

                        </a>

                    <?php endif; ?>

                    <?php if ($demande['status'] === 'Brouillon' || $demande['status'] === 'Rejete'): ?>

                        <a href="../../actions/delete_demande.php?id=<?= $id_dem ?>&source=mes_frais"

                            class="btn btn-sm btn-outline-danger shadow-sm bg-white"

                            onclick="return confirm('Supprimer définitivement ce dossier ?')">

                            <i class="bi bi-trash me-1"></i> Supprimer

                        </a>

                    <?php endif; ?>

                </div>

            </div>

        </div>



        <div class="row g-4">

            <!-- COLONNE GAUCHE (Info + Lignes) -->

            <div class="col-lg-8">



                <!-- INFO MISSION -->

                <div class="card-detail">

                    <div class="card-header-custom">

                        <h6 class="section-title"><i class="bi bi-info-circle text-primary"></i> Informations Mission

                        </h6>

                    </div>

                    <div class="p-4">

                        <div class="row g-4">

                            <div class="col-12">

                                <span class="info-label">Objet / Intitulé</span>

                                <div class="info-value fs-6"><?= htmlspecialchars($demande['titre_dem']) ?></div>

                            </div>

                            <div class="col-md-6">

                                <span class="info-label">Date de début</span>

                                <div class="info-value"><?= date('d/m/Y', strtotime($demande['date_dep'])) ?></div>

                            </div>

                            <div class="col-md-6">

                                <span class="info-label">Date de fin</span>

                                <div class="info-value">

                                    <?php

                                    if ($demande['date_fin'] && $demande['date_fin'] != $demande['date_dep']) {

                                        echo date('d/m/Y', strtotime($demande['date_fin']));

                                    } else {

                                        echo '<span class="text-muted fst-italic">Même jour</span>';

                                    }

                                    ?>

                                </div>

                            </div>



                            <!-- Bloc Rejet si existant -->

                            <?php if (!empty($demande['motif_rejet'])): ?>

                                <div class="col-12 mt-2">

                                    <div class="p-3 bg-danger-subtle text-danger rounded border border-danger-subtle">

                                        <label class="small fw-bold mb-1"><i

                                                class="bi bi-exclamation-triangle-fill me-1"></i> Motif du rejet :</label>

                                        <div class="small"><?= nl2br(htmlspecialchars($demande['motif_rejet'])) ?></div>

                                    </div>

                                </div>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>



                <!-- TABLEAU LIGNES DE FRAIS -->

                <div class="card-detail">

                    <div class="card-header-custom">

                        <h6 class="section-title"><i class="bi bi-receipt text-primary"></i> Lignes de frais</h6>

                        <span class="badge bg-light text-dark border"><?= count($lignes) ?> élément(s)</span>

                    </div>

                    <div class="table-responsive">

                        <table class="table-app">

                            <thead>

                                <tr>

                                    <th>Date</th>

                                    <th>Catégorie / Description</th>

                                    <th>Justificatif</th>

                                    <th class="text-end">Montant</th>

                                </tr>

                            </thead>

                            <tbody>

                                <?php if (empty($lignes)): ?>

                                    <tr>

                                        <td colspan="4" class="text-center text-muted py-4">Aucune ligne de frais saisie.

                                        </td>

                                    </tr>

                                <?php else: ?>

                                    <?php foreach ($lignes as $line):

                                        $description = '';

                                        if (!empty($line['details_specifiques'])) {

                                            $details = json_decode($line['details_specifiques'], true);

                                            $description = $details['description'] ?? '';

                                        }

                                    ?>

                                        <tr>

                                            <td class="text-nowrap small"><?= date('d/m/Y', strtotime($line['date_depense'])) ?>

                                            </td>

                                            <td>

                                                <div class="fw-medium text-dark"><?= htmlspecialchars($line['nom_categ']) ?>

                                                </div>

                                                <?php if (!empty($description)): ?>

                                                    <div class="small text-muted mt-1"><?= htmlspecialchars($description) ?></div>

                                                <?php endif; ?>

                                            </td>

                                            <td>

                                                <?php if (!empty($line['justificatif_path'])): 
                                                    // Gérer les deux formats : avec ou sans préfixe uploads/proofs/
                                                    $proofPath = $line['justificatif_path'];
                                                    if (strpos($proofPath, 'uploads/proofs/') === 0) {
                                                        $proofPath = str_replace('uploads/proofs/', '', $proofPath);
                                                    }
                                                    // Nettoyer aussi si le chemin contient uploads/justificatifs/
                                                    if (strpos($proofPath, 'uploads/justificatifs/') === 0) {
                                                        $proofPath = str_replace('uploads/justificatifs/', '', $proofPath);
                                                    }
                                                ?>
                                                    <a href="../../uploads/proofs/<?= htmlspecialchars($proofPath) ?>"

                                                        target="_blank" class="btn btn-sm btn-light border text-primary"

                                                        style="font-size: 0.75rem;">

                                                        <i class="bi bi-paperclip"></i> Voir

                                                    </a>

                                                <?php else: ?>

                                                    <span class="text-muted small fst-italic">Aucun</span>

                                                <?php endif; ?>

                                            </td>

                                            <td class="text-end fw-bold text-dark"><?= number_format($line['montant'], 2) ?> DH

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>



            <!-- COLONNE DROITE (Résumé) -->

            <div class="col-lg-4">

                <div class="card-detail sticky-top" style="top: 80px; z-index: 1;">

                    <div class="card-header-custom bg-light">

                        <h6 class="section-title"><i class="bi bi-calculator text-primary"></i> Résumé Financier</h6>

                    </div>

                    <div class="p-4">

                        <div class="summary-row">

                            <span>Total Frais</span>

                            <span class="fw-medium"><?= number_format($total_frais, 2) ?> DH</span>

                        </div>



                        <?php if ($montant_avance > 0): ?>

                            <div class="summary-row text-warning">

                                <span><i class="bi bi-arrow-return-right me-1"></i> Avance reçue</span>

                                <span>- <?= number_format($montant_avance, 2) ?> DH</span>

                            </div>

                        <?php endif; ?>



                        <div class="summary-row total">

                            <span>Net à Payer</span>

                            <span class="<?= $net_a_payer < 0 ? 'text-danger' : 'text-success' ?>">

                                <?= number_format($net_a_payer, 2) ?> DH

                            </span>

                        </div>

                        <?php if ($net_a_payer < 0): ?>

                            <div class="p-2 mt-3 bg-danger-subtle rounded text-danger small text-center">

                                <strong>Note :</strong> Vous devez rembourser la différence de

                                <?= number_format(abs($net_a_payer), 2) ?> DH à l'entreprise.

                            </div>

                        <?php else: ?>

                            <div class="p-2 mt-3 bg-success-subtle rounded text-success small text-center">

                                Montant à vous rembourser par l'entreprise.

                            </div>

                        <?php endif; ?>

                    </div>

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
