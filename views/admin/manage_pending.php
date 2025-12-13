<?php
// -------------------------------------------------------------------------
// VUE ADMIN : GESTION DES PAIEMENTS (DRAG & DROP ACTIVÉ)
// -------------------------------------------------------------------------
require_once __DIR__ . '/../../includes/session.php';
requireRole('admin');
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../includes/security.php';

$pdo = Database::getInstance()->getConnexion();

// Récupérer les infos utilisateur
$user_id = $_SESSION['user_id'] ?? $_SESSION['user']['user_id'] ?? null;
if (!$user_id) {
    header('Location: ../../views/auth/login.php');
    exit;
}

$stmt = $pdo->prepare("SELECT user_id, nom, prenom, email, avatar FROM users WHERE user_id = ? AND role = 'admin'");
$stmt->execute([$user_id]);
$userInfo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$userInfo) {
    header('Location: ../../views/auth/login.php');
    exit;
}

$user_name = $userInfo['nom'] . ' ' . ($userInfo['prenom'] ?? '');
$avatar_bdd = $userInfo['avatar'] ?? 'default.png';
$chemin_physique = __DIR__ . '/../../assets/img/' . $avatar_bdd;
$avatar = (file_exists($chemin_physique) && !empty($avatar_bdd)) ? '../../assets/img/' . $avatar_bdd : '../../assets/img/default.png';

$db = $pdo;
$message = "";
$logReport = [];
$successCount = 0;

// --- LOGIQUE D'IMPORT CSV ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];
    $fileError = $_FILES['csv_file']['error'];
    
    if ($fileError !== UPLOAD_ERR_OK) {
        $message = "Erreur lors de l'upload du fichier (Code: $fileError).";
    } elseif (!file_exists($file) || filesize($file) == 0) {
        $message = "Le fichier est vide ou introuvable.";
    } elseif (($handle = fopen($file, "r")) !== FALSE) {
        $row = 0;
        $successCount = 0;
        $totalRows = 0;
        $skippedRows = 0;
        $emailColIndex = null;
        $montantColIndex = null;
        
        // Détecter le séparateur (point-virgule ou virgule)
        $firstLine = fgets($handle);
        rewind($handle);
        $separator = (strpos($firstLine, ';') !== false) ? ';' : ',';
        
        // Lire la première ligne pour détecter les colonnes Email et Montant
        $headerRow = fgetcsv($handle, 1000, $separator);
        if ($headerRow && count($headerRow) > 0) {
            // Chercher la colonne Email
            foreach ($headerRow as $index => $header) {
                $headerClean = trim(strtolower($header));
                $headerClean = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $headerClean);
                $headerClean = trim($headerClean, " \t\n\r\0\x0B\"'");
                
                if (in_array($headerClean, ['email', 'e-mail', 'mail', 'adresse email', 'adresse e-mail', 'courriel'])) {
                    $emailColIndex = $index;
                }
                
                // Chercher la colonne Montant
                if (in_array($headerClean, ['montant', 'montant (dh)', 'montant(dh)', 'montant dh', 'amount', 'prix', 'total'])) {
                    $montantColIndex = $index;
                }
            }
            
            // Si les colonnes ne sont pas trouvées dans l'en-tête, utiliser les colonnes par défaut (0 et 1)
            if ($emailColIndex === null) {
                $emailColIndex = 0;
            }
            if ($montantColIndex === null) {
                // Chercher la dernière colonne numérique comme montant
                for ($i = count($headerRow) - 1; $i >= 0; $i--) {
                    if (is_numeric(str_replace([',', ' '], ['.', ''], trim($headerRow[$i] ?? '')))) {
                        $montantColIndex = $i;
                        break;
                    }
                }
                // Si toujours pas trouvé, utiliser la colonne 1 ou la dernière
                if ($montantColIndex === null) {
                    $montantColIndex = (count($headerRow) > 1) ? 1 : (count($headerRow) - 1);
                }
            }
        } else {
            // Pas d'en-tête, utiliser les colonnes par défaut
            $emailColIndex = 0;
            $montantColIndex = 1;
        }
        
        while (($data = fgetcsv($handle, 1000, $separator)) !== FALSE) {
            $row++;
            $totalRows++;
            
            // Ignorer les lignes vides
            if (empty($data) || count($data) < 2) {
                $skippedRows++;
                continue;
            }
            
            // Extraire l'email depuis la colonne détectée
            $email = isset($data[$emailColIndex]) ? trim($data[$emailColIndex]) : '';
            // Supprimer les caractères invisibles (BOM, espaces, etc.)
            $email = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $email);
            $email = trim($email, " \t\n\r\0\x0B\"'");
            
            // Vérifier que l'email est valide
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skippedRows++;
                continue;
            }
            
            // Extraire le montant depuis la colonne détectée
            $montantStr = isset($data[$montantColIndex]) ? trim($data[$montantColIndex]) : '';
            // Supprimer les espaces et remplacer virgule par point
            $montantStr = str_replace([' ', ','], ['', '.'], $montantStr);
            $montant = floatval($montantStr);
            
            if ($montant <= 0) {
                $skippedRows++;
                continue;
            }
            
            // Rechercher la demande avec une tolérance plus large pour les décimales
            $sqlFind = "SELECT d.id_dem, d.user_id, u.team_id, d.montant_total, d.status
                        FROM demande d 
                        JOIN users u ON d.user_id = u.user_id
                        WHERE u.email = ? 
                        AND ABS(d.montant_total - ?) < 0.01 
                        AND d.status = 'Attente_Admin'
                        LIMIT 1";
            $stmtFind = $db->prepare($sqlFind);
            $stmtFind->execute([$email, $montant]);
            $demande = $stmtFind->fetch(PDO::FETCH_ASSOC);

            if ($demande) {
                try {
                    $db->beginTransaction();
                    
                    // Mettre à jour le statut
                    $db->prepare("UPDATE demande SET status = 'Paye' WHERE id_dem = ?")->execute([$demande['id_dem']]);
                    
                    // Débiter le budget de l'équipe
                    if ($demande['team_id']) {
                        try {
                            $db->prepare("UPDATE teams SET budget_consomme = budget_consomme + ? WHERE team_id = ?")->execute([$montant, $demande['team_id']]);
                        } catch (PDOException $e) {
                            // Table teams n'existe pas, ignorer
                        }
                    }
                    
                    $db->commit();
                    $successCount++;
                } catch (Exception $e) {
                    $db->rollBack();
                    // Erreur silencieuse, on continue avec les autres lignes
                }
            }
            // Pas de message individuel pour chaque ligne, seulement le total à la fin
        }
        fclose($handle);
        
        if ($totalRows == 0) {
            $message = "Le fichier CSV est vide.";
        } elseif ($successCount == 0) {
            $message = "Aucun paiement validé. Vérifiez le format du fichier (Email;Montant) et que les demandes sont en statut 'Attente_Admin'.";
        } else {
            $message = "$successCount paiement(s) validé(s) avec succès sur $totalRows ligne(s) traitée(s).";
        }
    } else {
        $message = "Impossible d'ouvrir le fichier CSV.";
    }
}

// --- RÉCUPÉRATION DES DEMANDES ---
$sqlAll = "SELECT d.*, u.nom, u.email, u.avatar as user_avatar, t.nom_team 
           FROM demande d JOIN users u ON d.user_id = u.user_id 
           LEFT JOIN teams t ON u.team_id = t.team_id 
           WHERE d.status = 'Attente_Admin' ORDER BY d.created_at ASC";
$allPending = $db->query($sqlAll)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Paiements | Administration</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <style>
        :root {
            --app-bg: #f8fafc;
            --header-bg: #ffffff;
            --header-border: #e2e8f0;
            --primary: #059669;
            --primary-dark: #047857;
            --primary-light: #10b981;
            --text-main: #1e293b;
            --text-light: #64748b;
            --card-border: #e2e8f0;
            --radius: 16px;
        }

        [data-theme="dark"] {
            --app-bg: #0f172a;
            --header-bg: #1e293b;
            --header-border: #334155;
            --text-main: #f1f5f9;
            --text-light: #94a3b8;
            --card-border: #334155;
        }

        /* Styles pour le mode sombre - Cartes et Tableaux */
        [data-theme="dark"] .card-widget,
        [data-theme="dark"] .card,
        [data-theme="dark"] .table-custom td {
            background: #1e293b !important;
            color: var(--text-main) !important;
            border-color: var(--card-border) !important;
        }

        [data-theme="dark"] .table-custom tr:hover td {
            background-color: #334155 !important;
            border-color: #475569 !important;
        }

        [data-theme="dark"] .table-custom th {
            color: var(--text-light) !important;
        }

        [data-theme="dark"] .table-custom td:first-child,
        [data-theme="dark"] .table-custom td:last-child {
            border-color: var(--card-border) !important;
        }

        [data-theme="dark"] .drop-zone {
            background: #1e293b !important;
            border-color: var(--card-border) !important;
        }

        [data-theme="dark"] .drop-zone:hover {
            background: #334155 !important;
            border-color: var(--primary) !important;
        }

        /* Autres éléments en mode sombre */
        [data-theme="dark"] .modal-content,
        [data-theme="dark"] .dropdown-menu {
            background: #1e293b !important;
            border-color: var(--card-border) !important;
            color: var(--text-main) !important;
        }

        [data-theme="dark"] .form-control,
        [data-theme="dark"] .form-select {
            background: #1e293b !important;
            border-color: var(--card-border) !important;
            color: var(--text-main) !important;
        }

        [data-theme="dark"] .form-control:focus,
        [data-theme="dark"] .form-select:focus {
            background: #1e293b !important;
            border-color: var(--primary) !important;
            color: var(--text-main) !important;
        }

        /* Tous les éléments avec background white */
        [data-theme="dark"] *[style*="background: white"],
        [data-theme="dark"] *[style*="background-color: white"],
        [data-theme="dark"] *[style*="background:#fff"],
        [data-theme="dark"] *[style*="background-color:#fff"],
        [data-theme="dark"] .bg-white,
        [data-theme="dark"] .input-group-text,
        [data-theme="dark"] .modal-header,
        [data-theme="dark"] .modal-footer,
        [data-theme="dark"] .list-group-item,
        [data-theme="dark"] .badge {
            background: #1e293b !important;
            background-color: #1e293b !important;
            color: var(--text-main) !important;
            border-color: var(--card-border) !important;
        }

        [data-theme="dark"] .input-group-text {
            border-color: var(--card-border) !important;
        }

        /* Textes */
        [data-theme="dark"] .text-muted,
        [data-theme="dark"] .text-secondary,
        [data-theme="dark"] small {
            color: var(--text-light) !important;
        }

        [data-theme="dark"] .text-dark {
            color: var(--text-main) !important;
        }

        [data-theme="dark"] h1,
        [data-theme="dark"] h2,
        [data-theme="dark"] h3,
        [data-theme="dark"] h4,
        [data-theme="dark"] h5,
        [data-theme="dark"] h6 {
            color: var(--text-main) !important;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--app-bg);
            color: var(--text-main);
            padding-top: 70px;
            font-size: 0.875rem;
            transition: background-color 0.3s, color 0.3s;
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
            transition: background-color 0.3s, border-color 0.3s;
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

        .brand-logo:hover {
            animation: logo-bounce 1s infinite;
            background-color: #047857;
        }

        .app-header .d-flex span {
            font-weight: 700 !important;
        }

        .app-nav {
            display: flex;
            gap: 6px;
            height: 100%;
            margin-left: 20px;
        }
        
        .nav-item-link {
            color: var(--text-light);
            text-decoration: none;
            padding: 0 14px;
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
            background-color: transparent;
        }

        .avatar-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid #ecfdf5;
        }

        /* CARDS & TABLES */
        .card-widget {
            background: white;
            border-radius: var(--radius);
            padding: 24px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(145, 190, 150, 0.2);
            height: auto;
            overflow: hidden;
        }

        .table-custom {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
        }

        .table-custom th {
            font-size: 0.75rem;
            text-transform: uppercase;
            color: var(--text-light);
            font-weight: 700;
            padding: 0 16px 8px;
            border: none;
        }

        .table-custom td {
            background: white;
            padding: 16px;
            vertical-align: middle;
            border-top: 1px solid #f1f5f9;
            border-bottom: 1px solid #f1f5f9;
        }

        .table-custom td:first-child {
            border-left: 1px solid #f1f5f9;
            border-top-left-radius: 12px;
            border-bottom-left-radius: 12px;
        }

        .table-custom td:last-child {
            border-right: 1px solid #f1f5f9;
            border-top-right-radius: 12px;
            border-bottom-right-radius: 12px;
        }

        /* DRAG & DROP ZONE */
        .drop-zone {
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 40px 20px;
            text-align: center;
            background: #f8fafc;
            transition: all 0.2s ease-in-out;
            cursor: pointer;
            position: relative;
        }

        .drop-zone i {
            font-size: 2.5rem;
            color: #94a3b8;
            transition: 0.2s;
        }

        /* Effet au survol (souris) */
        .drop-zone:hover {
            border-color: var(--primary-light);
            background: #f0fdf4;
        }

        .drop-zone:hover i {
            color: var(--primary);
            transform: translateY(-5px);
        }

        /* Effet quand on glisse un fichier dessus (JS) */
        .drop-zone.drag-over {
            border-color: var(--primary);
            background-color: #d1fae5;
            transform: scale(1.02);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .drop-zone.drag-over i {
            color: var(--primary-dark);
            transform: scale(1.1);
        }

        /* LOGS */
        .log-item {
            padding: 10px 12px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .log-item:last-child {
            border: none;
        }

        .log-success {
            color: var(--primary);
            background: #ecfdf5;
            border-radius: 6px;
            margin-bottom: 4px;
        }

        .log-error {
            color: #dc2626;
            background: #fef2f2;
            border-radius: 6px;
            margin-bottom: 4px;
        }
    </style>
</head>

<body>

    <header class="app-header">
        <div class="d-flex align-items-center gap-2">
            <div class="brand-logo">RM</div> <span class="fw-bold text-dark">RembourseMaroc</span>
        </div>
        <!-- NAVBAR ADMIN UNIFIÉE -->
        <nav class="app-nav d-none d-md-flex">

            <!-- Lien 1: Dashboard -->
            <a href="dashboard.php"
                class="nav-item-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="bi bi-grid-fill me-2"></i>Dashboard
            </a>

            <!-- Lien 2: Pilotage Paiements -->
            <a href="manage_pending.php"
                class="nav-item-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_pending.php' ? 'active' : ''; ?>">
                <i class="bi bi-layers-fill me-2"></i>Paiements
            </a>

            <!-- Lien 3: Utilisateurs (Onglet par défaut) -->
            <!-- Active si la page est manage_data.php ET (tab est 'users' OU tab est vide) -->
            <a href="manage_data.php?tab=users"
                class="nav-item-link <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_data.php' && (!isset($_GET['tab']) || $_GET['tab'] == 'users')) ? 'active' : ''; ?>">
                <i class="bi bi-people me-2"></i>Utilisateurs
            </a>

            <!-- Lien 4: Équipes (Onglet spécifique) -->
            <!-- Active si la page est manage_data.php ET tab est 'teams' -->
            <a href="manage_data.php?tab=teams"
                class="nav-item-link <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_data.php' && (isset($_GET['tab']) && $_GET['tab'] == 'teams')) ? 'active' : ''; ?>">
                <i class="bi bi-diagram-3 me-2"></i>Équipes
            </a>

            <!-- Lien 5: Catégories -->
            <a href="manage_categories.php"
                class="nav-item-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_categories.php' ? 'active' : ''; ?>">
                <i class="bi bi-tags me-2"></i>Catégories
            </a>

            <a href="manage_reclamations.php" class="nav-item-link"><i
                    class="bi bi-life-preserver me-2"></i>Réclamations</a>
        </nav>
        <div class="dropdown">
            <a href="#" class="d-flex align-items-center gap-2 text-decoration-none" data-bs-toggle="dropdown">
                <div class="text-end d-none d-sm-block">
                    <div class="fw-bold text-dark small"><?= htmlspecialchars($user_name) ?></div>
                    <div class="text-muted" style="font-size: 0.65rem;">Administrateur</div>
                </div>
                <img src="<?= htmlspecialchars($avatar) ?>" class="avatar-circle">
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-3 p-2 rounded-3">
                <li><a class="dropdown-item rounded-2" href="profil.php">Mon Profil</a></li>
                <li>
                    <hr class="dropdown-divider">
                </li>
                <li><a class="dropdown-item rounded-2 text-danger" href="../../actions/logout.php">Déconnexion</a></li>
            </ul>
        </div>
    </header>

    <div class="container-fluid px-4 px-xl-5" style="max-width: 1600px; margin-top: 20px;">

        <div class="row mb-4 align-items-end">
            <div class="col-md-8">
                <h3 class="fw-bolder m-0" style="color: var(--primary-dark);">Pilotage Paiements</h3>
                <div class="text-muted">Synchronisation bancaire et validation finale des remboursements.</div>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <span
                    class="badge bg-warning bg-opacity-10 text-warning px-3 py-2 rounded-pill border border-warning border-opacity-25">
                    <i class="bi bi-hourglass-split me-1"></i> <?= count($allPending) ?> dossier(s) en attente
                </span>
            </div>
        </div>

        <div class="row g-4">

            <!-- LISTE -->
            <div class="col-lg-8">
                <div class="card-widget p-0 h-100">
                    <div
                        class="p-4 border-bottom bg-light bg-opacity-25 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold m-0 text-dark">
                            <i class="bi bi-wallet2 text-primary me-2"></i>Demandes à traiter
                        </h6>
                    </div>

                    <div class="p-3">
                        <table class="table-custom">
                            <thead>
                                <tr>
                                    <th>Collaborateur</th>
                                    <th>Ref / Mission</th>
                                    <th>Montant</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($allPending)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-5 text-muted">
                                            <i class="bi bi-check-circle fs-1 d-block mb-3 text-success"></i>
                                            <div class="fw-bold">Tout est à jour !</div>
                                            <div class="small">Aucune demande en attente de validation financière.</div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($allPending as $p):
                                        $u_img = !empty($p['user_avatar']) ? '../../assets/img/' . $p['user_avatar'] : '../../assets/img/default.png';
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-3">
                                                    <img src="<?= htmlspecialchars($u_img) ?>" class="rounded-circle" width="36"
                                                        height="36" style="object-fit:cover;">
                                                    <div>
                                                        <div class="fw-bold text-dark fs-6">
                                                            <?= htmlspecialchars($p['nom']) ?>
                                                        </div>
                                                        <div class="small text-muted">
                                                            <?= htmlspecialchars($p['nom_team']) ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-medium text-dark small">#<?= $p['id_dem'] ?> -
                                                    <?= htmlspecialchars($p['titre_dem']) ?></div>
                                                <div class="text-muted" style="font-size: 0.75rem;">
                                                    <i
                                                        class="bi bi-calendar3 me-1"></i><?= date('d/m/Y', strtotime($p['created_at'])) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="fw-bold text-dark"><?= number_format($p['montant_total'], 2) ?>
                                                    DH</span>
                                            </td>
                                            <td class="text-end">
                                                <a href="validate_demande.php?id=<?= $p['id_dem'] ?>"
                                                    class="btn btn-sm btn-outline-success rounded-pill px-3 fw-bold">Traiter</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- IMPORT DRAG & DROP -->
            <div class="col-lg-4">
                <div class="card-widget mb-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-file-earmark-spreadsheet me-2 text-success"></i>Import
                        CSV
                        Finance</h6>
                    <p class="text-muted small mb-4">
                        Glissez et déposez votre fichier CSV ici, ou cliquez pour parcourir.
                    </p>

                    <!-- Formulaire avec ID -->
                    <form method="POST" enctype="multipart/form-data" id="uploadForm">
                        <!-- Zone de Drop avec ID -->
                        <div class="drop-zone" id="dropZone">
                            <i class="bi bi-cloud-arrow-up"></i>
                            <div class="mt-3 fw-bold text-dark">Glisser-déposer le CSV</div>
                            <div class="small text-muted">ou cliquez pour sélectionner</div>
                            <div class="small text-muted mt-2 fst-italic py-1 bg-white border rounded">Format: Email
                                ;
                                Montant</div>

                            <input type="file" name="csv_file" id="csvInput" class="d-none" accept=".csv">
                        </div>
                    </form>
                </div>

                <!-- Rapport -->
                <?php if (!empty($message)): ?>
                    <div class="card-widget">
                        <h6 class="fw-bold mb-3">Rapport d'opération</h6>
                        <div class="alert <?= $successCount > 0 ? 'alert-success' : 'alert-warning' ?> d-flex align-items-center py-2 px-3 small border-0 <?= $successCount > 0 ? 'bg-success bg-opacity-10 text-success' : 'bg-warning bg-opacity-10 text-warning' ?> fw-bold">
                            <i class="bi <?= $successCount > 0 ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?> me-2"></i> 
                            <?= $message ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="my-5 text-center small text-muted">&copy; <?= date('Y') ?> RembourseMaroc Admin Panel</div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/admin_theme.js"></script>

    <!-- SCRIPT DRAG AND DROP -->
    <script>
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('csvInput');
        const uploadForm = document.getElementById('uploadForm');

        // Gestion du Clic (Garder le comportement classique)
        dropZone.addEventListener('click', () => {
            fileInput.click();
        });

        // Gestion du changement de fichier via clic
        fileInput.addEventListener('change', () => {
            if (fileInput.files.length > 0) {
                uploadForm.submit();
            }
        });

        // Empêcher le comportement par défaut (ouvrir le fichier) pour le Drag & Drop
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        // Ajouter la classe visuelle quand on survole avec un fichier
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, highlight, false);
        });

        // Retirer la classe quand on sort
        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, unhighlight, false);
        });

        function highlight() {
            dropZone.classList.add('drag-over');
        }

        function unhighlight() {
            dropZone.classList.remove('drag-over');
        }

        // Gérer le "Drop" (Lâcher le fichier)
        dropZone.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;

            if (files.length > 0) {
                // Associer le fichier lâché à l'input caché
                fileInput.files = files;
                // Soumettre le formulaire automatiquement
                uploadForm.submit();
            }
        }
    </script>
</body>

</html>