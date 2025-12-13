<?php

// -------------------------------------------------------------------------

// MES FRAIS - STYLE DESKTOP APP (AVEC TRI)

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

// --- GESTION FILTRES, RECHERCHE ET TRI ---

$filter = $_GET['filter'] ?? 'all';

$search = $_GET['search'] ?? '';



// Paramètres de tri

$sort = $_GET['sort'] ?? 'created_at'; // Colonne par défaut

$dir  = $_GET['dir'] ?? 'desc';        // Direction par défaut



// Liste blanche des colonnes autorisées pour le tri (Sécurité)

$allowed_sorts = [

    'id_dem'        => 'id_dem',

    'titre_dem'     => 'titre_dem',

    'date_dep'      => 'date_dep',

    'montant_total' => 'montant_total',

    'status'        => 'status',

    'created_at'    => 'created_at'

];



// Validation du tri

if (!array_key_exists($sort, $allowed_sorts)) {

    $sort = 'created_at';

}

$dir = (strtolower($dir) === 'asc') ? 'ASC' : 'DESC';



// Construction de la requête

$sql = "SELECT * FROM demande WHERE user_id = ?";

$params = [$user_id];



// Filtre par statut

switch ($filter) {

    case 'pending':

        $sql .= " AND status IN ('Attente_Manager', 'Attente_Admin')";

        break;

    case 'approved':

        $sql .= " AND status IN ('Valide', 'Paye')";

        break;

    case 'rejected':

        $sql .= " AND status = 'Rejete'";

        break;

    case 'draft':

        $sql .= " AND status = 'Brouillon'";

        break;

}



// Recherche par texte

if (!empty($search)) {

    $sql .= " AND (titre_dem LIKE ? OR id_dem LIKE ?)";

    $params[] = "%$search%";

    $params[] = "%$search%";

}



// Application du tri dynamique

$sql .= " ORDER BY $sort $dir";



$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);



// --- FONCTIONS HELPER ---



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



// Fonction pour générer les liens de tri dans le TH

function sortLink($column, $label, $currentSort, $currentDir)

{

    global $filter, $search;



    // Déterminer la prochaine direction

    $nextDir = ($currentSort === $column && $currentDir === 'ASC') ? 'desc' : 'asc';



    // Icône

    $icon = '';

    if ($currentSort === $column) {

        $icon = ($currentDir === 'ASC')

            ? '<i class="bi bi-caret-up-fill ms-1 text-primary"></i>'

            : '<i class="bi bi-caret-down-fill ms-1 text-primary"></i>';

    } else {

        // Icône fantôme pour indiquer que c'est triable

        $icon = '<i class="bi bi-arrow-down-up ms-1 text-muted opacity-25" style="font-size:0.7em"></i>';

    }



    // Construction URL

    $params = [

        'filter' => $filter,

        'search' => $search,

        'sort' => $column,

        'dir' => $nextDir

    ];

    $url = '?' . http_build_query($params);



    return '<a href="' . $url . '" class="sortable-link">' . $label . $icon . '</a>';

}

?>



<!DOCTYPE html>

<html lang="fr">



<head>

    <meta charset="UTF-8">

    <title>Mes Frais | Rembourse Maroc</title>

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

        }

        .app-header .d-flex span {
            font-weight: 700 !important;
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

            background-color: #047857;

            color: white;

        }



        /* --- TOOLBAR --- */

        .toolbar {

            display: flex;

            justify-content: space-between;

            align-items: center;

            background: white;

            border: 1px solid var(--card-border);

            border-radius: var(--radius);

            padding: 8px;

            margin-bottom: 16px;

            flex-wrap: wrap;

            gap: 10px;

        }



        .filter-group {

            display: flex;

            gap: 4px;

        }



        .filter-btn {

            padding: 6px 12px;

            border-radius: 6px;

            font-size: 0.85rem;

            font-weight: 500;

            color: var(--text-light);

            text-decoration: none;

            transition: 0.2s;

        }



        .filter-btn:hover {

            background: #f1f5f9;

            color: var(--text-main);

        }



        .filter-btn.active {

            background: #ecfdf5;

            color: var(--primary);

            font-weight: 600;

        }



        .search-box {

            position: relative;

            width: 250px;

        }



        .search-box input {

            width: 100%;

            padding: 6px 10px 6px 32px;

            border-radius: 6px;

            border: 1px solid var(--card-border);

            font-size: 0.85rem;

            outline: none;

        }



        .search-box input:focus {

            border-color: var(--primary);

            box-shadow: 0 0 0 2px rgba(5, 150, 105, 0.1);

        }



        .search-box i {

            position: absolute;

            left: 10px;

            top: 50%;

            transform: translateY(-50%);

            color: var(--text-light);

            font-size: 0.9rem;

        }



        /* --- TABLEAU --- */

        .table-container {

            background: white;

            border: 1px solid var(--card-border);

            border-radius: var(--radius);

            overflow: hidden;

        }



        .table-app {

            width: 100%;

            border-collapse: separate;

            border-spacing: 0;

        }



        /* STYLE EN-TETE TRIABLE */

        .table-app th {

            background: #f8fafc;

            font-weight: 600;

            font-size: 0.75rem;

            text-transform: uppercase;

            padding: 12px 16px;

            border-bottom: 1px solid var(--card-border);

            text-align: left;

            user-select: none;

            /* Empêche la sélection de texte lors du clic rapide */

        }



        /* Lien de tri */

        .sortable-link {

            color: var(--text-light);

            text-decoration: none;

            display: flex;

            align-items: center;

            cursor: pointer;

            transition: color 0.2s;

        }



        .sortable-link:hover {

            color: var(--text-main);

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



        /* Actions */

        .action-group {

            display: flex;

            gap: 6px;

            justify-content: flex-end;

        }



        .btn-icon {

            width: 32px;

            height: 32px;

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

            color: var(--primary);

            border-color: var(--card-border);

        }



        .btn-icon.pdf:hover {

            color: #dc2626;

            background: #fef2f2;

        }



        .btn-icon.warn {

            color: #dc2626;

            background: #fef2f2;

            border-color: #fecaca;

        }



        .btn-icon.warn:hover {

            background: #fee2e2;

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



    <!-- MAIN CONTENT -->

    <div class="main-container">



        <div class="page-header">

            <div>

                <h4 class="fw-bold m-0 text-dark">Mes Notes de Frais</h4>

                <div class="text-muted small">Consultez et suivez vos demandes de remboursement</div>

            </div>

                <a href="nouvelle_demande.php" class="btn-action">

                    <i class="bi bi-plus-lg"></i> Déclarer des frais

                </a>

        </div>



        <!-- BARRE D'OUTILS : FILTRES + RECHERCHE -->

        <div class="toolbar">

            <div class="filter-group">

                <!-- Les liens de filtre doivent réinitialiser le tri ou le conserver ? Ici on réinitialise pour simplicité, ou on garde search -->

                <a href="?filter=all&search=<?= htmlspecialchars($search) ?>"

                    class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">Tout</a>

                <a href="?filter=pending&search=<?= htmlspecialchars($search) ?>"

                    class="filter-btn <?= $filter === 'pending' ? 'active' : '' ?>">En cours</a>

                <a href="?filter=approved&search=<?= htmlspecialchars($search) ?>"

                    class="filter-btn <?= $filter === 'approved' ? 'active' : '' ?>">Validés</a>

                <a href="?filter=rejected&search=<?= htmlspecialchars($search) ?>"

                    class="filter-btn <?= $filter === 'rejected' ? 'active' : '' ?>">Rejetés</a>

                <a href="?filter=draft&search=<?= htmlspecialchars($search) ?>"

                    class="filter-btn <?= $filter === 'draft' ? 'active' : '' ?>">Brouillons</a>

            </div>



            <form method="GET" class="search-box">

                <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">

                <!-- On garde le tri lors d'une nouvelle recherche -->

                <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">

                <input type="hidden" name="dir" value="<?= htmlspecialchars($dir) ?>">



                <i class="bi bi-search"></i>

                <input type="text" name="search" placeholder="Rechercher (N° ou Titre)..."

                    value="<?= htmlspecialchars($search) ?>">

            </form>

        </div>



        <!-- TABLEAU DES DONNEES AVEC TRI -->

        <div class="table-container">

            <table class="table-app">

                <thead>

                    <tr>

                        <th style="width: 100px;">

                            <?= sortLink('id_dem', 'Réf.', $sort, $dir) ?>

                        </th>

                        <th>

                            <?= sortLink('titre_dem', 'Intitulé / Mission', $sort, $dir) ?>

                        </th>

                        <th style="width: 130px;">

                            <?= sortLink('date_dep', 'Date', $sort, $dir) ?>

                        </th>

                        <th style="width: 150px;">

                            <?= sortLink('montant_total', 'Montant', $sort, $dir) ?>

                        </th>

                        <th style="width: 150px;">

                            <?= sortLink('status', 'Statut', $sort, $dir) ?>

                        </th>

                        <th style="width: 140px;" class="text-end">Actions</th>

                    </tr>

                </thead>

                <tbody>

                    <?php if (empty($demandes)): ?>

                        <tr>

                            <td colspan="6" class="text-center py-5">

                                <i class="bi bi-inbox fs-1 text-muted opacity-25 d-block mb-2"></i>

                                <span class="text-muted small">Aucun dossier trouvé pour cette sélection.</span>

                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach ($demandes as $d): ?>

                            <tr>

                                <td>

                                    <span

                                        class="font-monospace small text-muted bg-light px-2 py-1 rounded">#<?= str_pad($d['id_dem'], 4, '0', STR_PAD_LEFT) ?></span>

                                </td>

                                <td>

                                    <div class="fw-medium text-dark"><?= htmlspecialchars($d['titre_dem']) ?></div>

                                </td>

                                <td class="text-secondary small">

                                    <?= date('d/m/Y', strtotime($d['date_dep'])) ?>

                                </td>

                                <td>

                                    <span class="fw-bold text-dark"><?= number_format($d['montant_total'], 2) ?></span> <span

                                        class="small text-muted">DH</span>

                                </td>

                                <td>

                                    <?= getStatusBadge($d['status']) ?>

                                </td>

                                <td>

                                    <div class="action-group">

                                        <!-- ALERTE REJET -->

                                        <?php if ($d['status'] === 'Rejete' && !empty($d['motif_rejet'])): ?>

                                            <button type="button" class="btn-icon warn" data-bs-toggle="popover"

                                                data-bs-trigger="hover focus" data-bs-title="Motif du rejet"

                                                data-bs-content="<?= htmlspecialchars($d['motif_rejet']) ?>">

                                                <i class="bi bi-exclamation-triangle-fill"></i>

                                            </button>

                                        <?php endif; ?>



                                        <!-- PDF -->

                                        <?php if ($d['status'] !== 'Brouillon'): ?>

                                            <a href="../../actions/export_pdf.php?id=<?= $d['id_dem'] ?>" target="_blank"

                                                class="btn-icon pdf" title="Télécharger PDF">

                                                <i class="bi bi-file-earmark-pdf"></i>

                                            </a>

                                        <?php endif; ?>



                                        <!-- DETAILS -->

                                        <?php if ($d['status'] === 'Brouillon'): ?>

                                            <a href="modifier_brouillon.php?id=<?= $d['id_dem'] ?>" class="btn-icon"

                                                title="Modifier">

                                                <i class="bi bi-pencil-square"></i>

                                            </a>

                                        <?php else: ?>

                                            <a href="details_demande.php?id=<?= $d['id_dem'] ?>" class="btn-icon"

                                                title="Voir détails">

                                                <i class="bi bi-eye"></i>

                                            </a>

                                        <?php endif; ?>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>



    <!-- SCRIPTS -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>

        const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]');

        const popoverList = [...popoverTriggerList].map(popoverTriggerEl => new bootstrap.Popover(popoverTriggerEl));

    </script>

</body>

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
</html>
