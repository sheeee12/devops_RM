<?php
// -------------------------------------------------------------------------
// PROFIL EMPLOYÉ - DESIGN PRO & ADAPTÉ BDD
// -------------------------------------------------------------------------
require_once __DIR__ . '/../../includes/session.php';
requireRole('employee');
require_once __DIR__ . '/../../config/Database.php';

$user_id = $_SESSION['user']['user_id'];
$pdo = Database::getInstance()->getConnexion();

// --- NOTIFICATIONS ---
require_once __DIR__ . '/../../includes/employee_notifications.php';
$notifications = getEmployeeNotifications($pdo, $user_id);
$notificationCount = count($notifications);

// RÉCUPÉRATION DES DONNÉES SELON VOTRE SCHÉMA
// On récupère l'utilisateur (u), son équipe (t) et son manager (m)
$sql = "
    SELECT 
        u.*, 
        t.nom_team, 
        m.nom AS nom_manager,
        m.email AS email_manager,
        m.avatar AS avatar_manager
    FROM users u
    LEFT JOIN teams t ON u.team_id = t.team_id
    LEFT JOIN users m ON u.manager_id = m.user_id
    WHERE u.user_id = ?
";

// Fonction pour charger les données utilisateur
function loadUserData($pdo, $user_id) {
    $sql = "
        SELECT 
            u.*, 
            t.nom_team, 
            m.nom AS nom_manager,
            m.email AS email_manager,
            m.avatar AS avatar_manager
        FROM users u
        LEFT JOIN teams t ON u.team_id = t.team_id
        LEFT JOIN users m ON u.manager_id = m.user_id
        WHERE u.user_id = ?
    ";
    
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Erreur de récupération du profil : " . $e->getMessage());
    }
}

// Fonction pour construire le chemin de l'avatar
function getAvatarPath($avatar_bdd) {
    if (empty($avatar_bdd) || $avatar_bdd === 'default.png') {
        $avatar_bdd = 'default.png';
    }
    
    $chemin_physique = __DIR__ . '/../../assets/img/' . $avatar_bdd;
    
    // Vérifier si le fichier existe - réessayer plusieurs fois si on vient d'un succès
    $maxRetries = 3;
    $retryCount = 0;
    $fileExists = false;
    
    while ($retryCount < $maxRetries && !$fileExists) {
        $fileExists = file_exists($chemin_physique);
        if (!$fileExists && $retryCount < $maxRetries - 1) {
            usleep(200000); // Attendre 0.2 seconde entre les tentatives
        }
        $retryCount++;
    }
    
    // Vérifier si le fichier existe
    if ($fileExists && !empty($avatar_bdd) && $avatar_bdd !== 'default.png') {
        $avatar = '../../assets/img/' . $avatar_bdd;
        // Ajouter un timestamp basé sur la date de modification pour forcer le rechargement
        $mtime = @filemtime($chemin_physique);
        if ($mtime !== false) {
            $avatar .= '?v=' . $mtime;
        } else {
            // Si filemtime échoue, utiliser le timestamp actuel
            $avatar .= '?v=' . time();
        }
        return $avatar;
    } else {
        // Si le fichier n'existe pas, retourner default.png
        $avatar = '../../assets/img/default.png';
        $defaultPath = __DIR__ . '/../../assets/img/default.png';
        if (file_exists($defaultPath)) {
            $mtime = @filemtime($defaultPath);
            if ($mtime !== false) {
                $avatar .= '?v=' . $mtime;
            } else {
                $avatar .= '?v=' . time();
            }
        } else {
            $avatar .= '?v=' . time();
        }
        return $avatar;
    }
}

// Charger les données utilisateur
$infos = loadUserData($pdo, $user_id);

// Si on revient avec un succès d'avatar, recharger les données pour obtenir le nouvel avatar
if (isset($_GET['success']) && $_GET['success'] === 'avatar') {
    // Attendre un peu pour s'assurer que le fichier est complètement écrit
    usleep(200000); // 0.2 seconde
    $infos = loadUserData($pdo, $user_id);
    
    // Vérifier que l'avatar a bien été mis à jour
    $avatar_bdd = $infos['avatar'] ?? 'default.png';
    $chemin_physique = __DIR__ . '/../../assets/img/' . $avatar_bdd;
    
    // Si le fichier n'existe pas encore, attendre un peu plus
    if (!file_exists($chemin_physique) && $avatar_bdd !== 'default.png') {
        usleep(300000); // 0.3 seconde de plus
        $infos = loadUserData($pdo, $user_id);
        $avatar_bdd = $infos['avatar'] ?? 'default.png';
    }
}

// Variables d'affichage (Sécurisées avec ??)
$user_name = $infos['nom'];
$user_email = $infos['email'];
$nom_team = $infos['nom_team'] ?? 'Non assigné'; // Correspond à la colonne nom_team
$nom_manager = $infos['nom_manager'] ?? 'Aucun';
$email_manager = $infos['email_manager'] ?? '';
$role_display = ($infos['role'] === 'employee') ? 'Collaborateur' : ucfirst($infos['role']);

// Gestion Avatar Utilisateur - IMPORTANT: Toujours utiliser les données les plus récentes
$avatar_bdd = $infos['avatar'] ?? 'default.png';
$avatar = getAvatarPath($avatar_bdd);

// Gestion Avatar Manager
$avatar_m_bdd = $infos['avatar_manager'] ?? 'default.png';
$chemin_m_physique = __DIR__ . '/../../assets/img/' . $avatar_m_bdd;
$avatar_manager = (file_exists($chemin_m_physique) && !empty($avatar_m_bdd)) ? '../../assets/img/' . $avatar_m_bdd : '../../assets/img/default.png';
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Mon Profil | Rembourse Maroc</title>
    <!-- Bootstrap 5 & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
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
        :root {
            --app-bg: #f8fafc;
            --header-bg: #ffffff;
            --header-border: #e2e8f0;
            --primary: #059669;
            --primary-dark: #047857;
            --text-main: #1e293b;
            --text-light: #64748b;
            --card-border: #e2e8f0;
            --radius: 12px;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--app-bg);
            color: var(--text-main);
            font-size: 0.875rem;
            padding-top: 70px;
            overflow-x: hidden;
        }

        /* --- HEADER & LOGO ANIMÉ --- */
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

        .brand-logo:hover {
            animation: logo-bounce 1s infinite;
            background-color: #047857;
        }

        .app-header .d-flex span {
            font-weight: 700 !important;
        }

        .brand-text-wrapper {
            overflow: hidden;
            width: 0;
            opacity: 0;
            white-space: nowrap;
            animation: slideTextOut 0.8s cubic-bezier(0.25, 1, 0.5, 1) forwards;
            animation-delay: 0.3s;
        }

        .brand-text {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--text-main);
            letter-spacing: -0.5px;
        }

        @keyframes slideTextOut {
            to {
                width: 160px;
                opacity: 1;
            }
        }

        /* --- NAVIGATION --- */
        .app-nav {
            display: flex !important;
            gap: 6px !important;
            height: 100% !important;
            margin-left: 20px !important;
            flex-wrap: nowrap !important;
            align-items: center !important;
            justify-content: flex-start !important;
            flex: 0 0 auto !important;
            max-width: none !important;
        }
        
        .nav-item-link {
            color: var(--text-light) !important;
            text-decoration: none !important;
            padding: 0 14px !important;
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            font-weight: 500 !important;
            height: 100% !important;
            border-bottom: 2px solid transparent !important;
            transition: all 0.2s !important;
            font-size: 0.9rem !important;
            border-radius: 0 !important;
            position: relative !important;
        }

        .nav-item-link::before {
            display: none !important;
        }

        .nav-item-link:hover {
            background-color: #f1f5f9 !important;
            color: var(--primary) !important;
            transform: none !important;
            box-shadow: none !important;
        }

        .nav-item-link.active {
            color: var(--primary) !important;
            border-bottom-color: var(--primary) !important;
            background: linear-gradient(to bottom, transparent 90%, rgba(5, 150, 105, 0.1)) !important;
            font-weight: 500 !important;
            box-shadow: none !important;
            border: none !important;
        }
        
        /* --- PROFILE --- */
        .user-area {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .avatar-circle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }

        .avatar-circle:hover {
            transform: scale(1.1);
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

        /* --- STYLES DE LA PAGE PROFIL --- */
        
        .page-container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        /* Cartes */
        .card-pro {
            background: white;
            border: none;
            border-radius: var(--card-radius);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06);
            overflow: hidden;
            margin-bottom: 24px;
        }

        /* Colonne Gauche : Identité */
        .profile-cover {
            height: 120px;
            background: linear-gradient(135deg, #059669 0%, #34d399 100%);
            position: relative;
        }

        .profile-avatar-wrapper {
            position: relative;
            margin-top: -60px;
            text-align: center;
            margin-bottom: 20px;
            z-index: 1;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid white;
            object-fit: cover;
            background: white;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            display: block;
            margin: 0 auto;
        }

        .btn-edit-avatar {
            position: absolute;
            bottom: 5px;
            right: 50%;
            transform: translateX(45px);
            width: 36px;
            height: 36px;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-medium);
            cursor: pointer;
            transition: var(--transition);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .btn-edit-avatar:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        /* Avatar Preview dans la section Photo de Profil */
        .avatar-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #059669;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .avatar-preview:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        }

        /* Colonne Droite : Sections */
        .section-header {
            padding: 20px 24px;
            border-bottom: 1px solid #f3f4f6;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title {
            font-weight: 600;
            font-size: 1.05rem;
            margin: 0;
            color: var(--text-dark);
        }

        .section-icon {
            color: var(--primary);
            background: #ecfdf5;
            padding: 6px;
            border-radius: 6px;
        }

        .info-grid {
            padding: 24px;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
        }

        .info-item label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--text-medium);
            margin-bottom: 6px;
            letter-spacing: 0.5px;
        }

        .info-item div {
            font-size: 0.95rem;
            font-weight: 500;
            color: var(--text-dark);
            border-bottom: 1px solid #f3f4f6;
            padding-bottom: 8px;
        }

        /* Widget Manager */
        .manager-widget {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px;
            background: #f9fafb;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }

        .manager-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
        }

        /* Formulaires */
        .form-control-pro {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 0.95rem;
        }

        .form-control-pro:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
            outline: none;
        }

        .btn-pro {
            background: var(--primary);
            color: white;
            font-weight: 500;
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            transition: var(--transition);
        }

        .btn-pro:hover {
            background: var(--primary-hover);
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

    <!-- CONTENT -->
    <div class="page-container">

        <!-- ALERTS DE SUCCESS/ERREUR -->
        <?php 
        // Gérer les messages de succès/erreur depuis GET (redirection depuis update_avatar_employee.php)
        $successMsg = '';
        $errorMsg = '';
        
        if (isset($_GET['success']) && $_GET['success'] === 'avatar') {
            $successMsg = 'Photo de profil mise à jour avec succès !';
            // Les données ont déjà été rechargées au début du script, juste mettre à jour l'avatar
            $avatar_bdd = $infos['avatar'] ?? 'default.png';
            $avatar = getAvatarPath($avatar_bdd);
        }
        
        if (isset($_GET['error'])) {
            switch ($_GET['error']) {
                case 'upload':
                    $errorMsg = 'Erreur lors du téléchargement de la photo.';
                    break;
                case 'format':
                    $errorMsg = 'Format de fichier non supporté. Formats acceptés : JPG, PNG.';
                    break;
                case 'size':
                    $errorMsg = 'Le fichier est trop volumineux. Taille maximale : 2MB.';
                    break;
                case 'session':
                    $errorMsg = 'Erreur de session. Veuillez vous reconnecter.';
                    break;
                default:
                    $errorMsg = 'Une erreur est survenue.';
            }
        }
        
        // Aussi vérifier les messages de session
        if (isset($_SESSION['success'])) {
            $successMsg = $_SESSION['success'];
            unset($_SESSION['success']);
        }
        if (isset($_SESSION['error'])) {
            $errorMsg = $_SESSION['error'];
            unset($_SESSION['error']);
        }
        ?>
        
        <?php if ($successMsg): ?>
            <div class="alert alert-success border-0 shadow-sm d-flex align-items-center gap-2 mb-4">
                <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($successMsg) ?>
            </div>
        <?php endif; ?>
        <?php if ($errorMsg): ?>
            <div class="alert alert-danger border-0 shadow-sm d-flex align-items-center gap-2 mb-4">
                <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($errorMsg) ?>
            </div>
        <?php endif; ?>

        <div class="row g-4">

            <!-- GAUCHE : IDENTITÉ -->
            <div class="col-lg-4">
                <div class="card-pro text-center pb-4">
                    <!-- Banner -->
                    <div class="profile-cover"></div>

                    <!-- Avatar Display (read-only in sidebar) -->
                       <!-- <div class="profile-avatar-wrapper">
                        <img src="<?= htmlspecialchars($avatar) ?>" class="profile-avatar" alt="Avatar" data-avatar="<?= htmlspecialchars($avatar_bdd) ?>" id="sidebarAvatar">
                        </div> -->

                    <!-- User Info -->
                    <div class="px-4" style="margin-top: 10px; padding-top: 20px;">
                        <h4 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($infos['nom']) ?></h4>
                        <p class="text-secondary mb-3"><?= htmlspecialchars($infos['email']) ?></p>

                        <div class="d-flex justify-content-center gap-2 mb-4">
                            <span
                                class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-10 px-3 py-2 rounded-pill fw-medium">
                                <?= $role_display ?>
                            </span>
                        </div>

                        <hr class="border-light">

                        <!-- Stats or Meta (TEXTES CORRIGÉS EN NOIR/FONCÉ) -->
                        <div class="row text-start mt-4">
                            <div class="col-12 mb-3">
                                <!-- Changement ici : text-secondary fw-bold au lieu de text-light -->
                                <small class="text-uppercase text-secondary fw-bold"
                                    style="font-size: 0.75rem;">Département</small>
                                <div class="d-flex align-items-center gap-2 mt-1">
                                    <i class="bi bi-diagram-3 text-primary"></i>
                                    <span class="fw-medium text-dark"><?= htmlspecialchars($nom_team) ?></span>
                                </div>
                            </div>
                            <div class="col-12">
                                <!-- Changement ici : text-secondary fw-bold au lieu de text-light -->
                                <small class="text-uppercase text-secondary fw-bold" style="font-size: 0.75rem;">Statut
                                    Compte</small>
                                <div class="d-flex align-items-center gap-2 mt-1 text-success">
                                    <i class="bi bi-shield-check-fill"></i>
                                    <span class="fw-medium">Vérifié & Actif</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Manager Card -->
                <?php if ($infos['manager_id']): ?>
                    <div class="card-pro p-4 mb-4">
                        <!-- Changement ici : text-dark au lieu de text-light -->
                        <h6 class="fw-bold mb-3 small text-uppercase text-dark">Manager</h6>
                        <div class="manager-widget">
                            <img src="<?= htmlspecialchars($avatar_manager) ?>" class="manager-avatar">
                            <div style="overflow:hidden;">
                                <div class="fw-bold text-dark text-truncate"><?= htmlspecialchars($nom_manager) ?></div>
                                <div class="small text-secondary text-truncate"><?= htmlspecialchars($email_manager) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Carte Thème/Apparence -->
                <?php
                $currentTheme = $_SESSION['theme'] ?? $_COOKIE['app_theme'] ?? 'light';
                ?>
                <div class="card-pro p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-palette me-2"></i>Apparence</h6>
                    <p class="text-muted small mb-3">Choisissez votre thème préféré</p>
                    <form action="../../actions/update_theme.php" method="POST">
                        <div class="d-flex flex-column gap-2">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="theme" id="theme_light_sidebar" value="light" <?= $currentTheme === 'light' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="theme_light_sidebar">
                                    <i class="bi bi-sun-fill me-2"></i>Clair
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="theme" id="theme_dark_sidebar" value="dark" <?= $currentTheme === 'dark' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="theme_dark_sidebar">
                                    <i class="bi bi-moon-fill me-2"></i>Sombre
                                </label>
                            </div>
                        </div>
                        <button type="submit" class="btn-pro w-100 mt-3 shadow-sm">
                            <i class="bi bi-save me-2"></i>Enregistrer le thème
                        </button>
                    </form>
                </div>
            </div>

            <!-- DROITE : DÉTAILS & PARAMÈTRES -->
            <div class="col-lg-8">

                <!-- 1. PHOTO DE PROFIL -->
                <div class="card-pro mb-4">
                    <div class="section-header">
                        <i class="bi bi-person-circle section-icon"></i>
                        <h5 class="section-title">Photo de Profil</h5>
                    </div>
                    <div class="p-4">
                        <form id="avatarForm" action="../../actions/update_avatar_employee.php" method="POST" enctype="multipart/form-data">
                            <div class="d-flex align-items-center gap-4 mb-3">
                                <div class="position-relative">
                                    <img src="<?= htmlspecialchars($avatar) ?>" alt="Avatar" class="avatar-preview" id="avatarPreview" data-avatar="<?= htmlspecialchars($avatar_bdd) ?>" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid #059669; cursor: pointer;" onclick="document.getElementById('avatarInput').click()">
                                    <input type="file" name="avatar" id="avatarInput" accept="image/jpeg,image/jpg,image/png" style="display: none;" onchange="previewAvatar(this)">
                                </div>
                                <div class="flex-grow-1">
                                    <p class="text-muted small mb-3">Cliquez sur l'image ou utilisez le bouton pour changer votre photo de profil.</p>
                                    <label for="avatarInput" class="btn btn-primary btn-sm" style="cursor: pointer;">
                                        <i class="bi bi-upload me-2"></i>Choisir une photo
                                    </label>
                                    <p class="text-muted small mt-2 mb-0">Formats acceptés : JPG, PNG (max 2MB)</p>
                                </div>
                            </div>
                            <div id="avatarSubmitContainer" style="display: none; padding-top: 15px; border-top: 1px solid #e5e7eb;">
                                <div class="d-flex align-items-center gap-2">
                                    <button type="submit" class="btn btn-success btn-sm" id="submitAvatarBtn">
                                        <i class="bi bi-check-lg me-2"></i>Enregistrer la photo
                                    </button>
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="resetAvatar()">
                                        <i class="bi bi-x-lg me-2"></i>Annuler
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- 2. INFORMATION PERSONNELLE -->
                <div class="card-pro mb-4">
                    <div class="section-header">
                        <i class="bi bi-person-vcard section-icon"></i>
                        <h5 class="section-title">Informations Personnelles</h5>
                    </div>
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Prénom & Nom</label>
                            <div><?= htmlspecialchars($infos['nom']) ?></div>
                        </div>
                        <div class="info-item">
                            <label>Adresse Email</label>
                            <div><?= htmlspecialchars($infos['email']) ?></div>
                        </div>
                        <div class="info-item">
                            <label>Matricule RH</label>
                            <div class="font-monospace text-dark">EMP-<?= str_pad($user_id, 5, '0', STR_PAD_LEFT) ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <label>Date d'embauche</label>
                            <div class="text-muted fst-italic">Non renseigné</div>
                        </div>
                    </div>
                    <div class="bg-light p-3 border-top d-flex align-items-center gap-2 text-secondary small">
                        <i class="bi bi-info-circle-fill"></i>
                        Pour modifier ces informations, veuillez contacter le service RH.
                    </div>
                </div>

                <!-- 3. SÉCURITÉ -->
                <div class="card-pro mb-4">
                    <div class="section-header">
                        <i class="bi bi-shield-lock section-icon"></i>
                        <h5 class="section-title">Sécurité & Mot de passe</h5>
                    </div>
                    <div class="p-4">
                        <form action="../../actions/update_password_employee.php" method="POST">
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="form-label small fw-bold text-secondary">Mot de passe actuel</label>
                                    <input type="password" name="old_password" class="form-control-pro w-100"
                                        placeholder="••••••••" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-secondary">Nouveau mot de passe</label>
                                    <input type="password" name="new_password" class="form-control-pro w-100"
                                        placeholder="Min. 8 caractères" required minlength="8">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-secondary">Confirmer le nouveau</label>
                                    <input type="password" name="confirm_password" class="form-control-pro w-100"
                                        placeholder="Répétez le mot de passe" required>
                                </div>
                                <div class="col-12 text-end mt-4">
                                    <button type="submit" class="btn-pro shadow-sm">
                                        <i class="bi bi-check-lg me-2"></i>Mettre à jour
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>


            </div>
        </div>
    </div>

    <!-- Scripts Bootstrap -->
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
    <script src="../../assets/js/theme.js"></script>
    <script>
        // Appliquer le thème au chargement
        const currentTheme = '<?= $currentTheme ?>';
        if (currentTheme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        }

        // Preview avatar
        function previewAvatar(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                
                // Vérifier la taille du fichier
                if (file.size > 2 * 1024 * 1024) {
                    alert('Le fichier est trop volumineux. Taille maximale : 2MB');
                    input.value = '';
                    return;
                }
                
                // Vérifier le type de fichier
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Format de fichier non supporté. Formats acceptés : JPG, PNG');
                    input.value = '';
                    return;
                }
                
                // Afficher la prévisualisation
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('avatarPreview').src = e.target.result;
                    document.getElementById('avatarSubmitContainer').style.display = 'block';
                };
                reader.onerror = function() {
                    alert('Erreur lors de la lecture du fichier');
                    input.value = '';
                };
                reader.readAsDataURL(file);
            }
        }

        // Reset avatar
        function resetAvatar() {
            const fileInput = document.getElementById('avatarInput');
            const preview = document.getElementById('avatarPreview');
            const container = document.getElementById('avatarSubmitContainer');
            
            if (fileInput) fileInput.value = '';
            if (preview) preview.src = '<?= htmlspecialchars($avatar) ?>';
            if (container) container.style.display = 'none';
        }

        // S'assurer que le formulaire se soumet correctement
        document.addEventListener('DOMContentLoaded', function() {
            const avatarForm = document.getElementById('avatarForm');
            const fileInput = document.getElementById('avatarInput');
            
            if (avatarForm && fileInput) {
                // Vérifier avant la soumission
                avatarForm.addEventListener('submit', function(e) {
                    if (!fileInput.files || fileInput.files.length === 0) {
                        e.preventDefault();
                        alert('Veuillez sélectionner une image avant de continuer');
                        return false;
                    }
                    // Le formulaire peut se soumettre normalement
                    return true;
                });
            }
            
            // Si on revient avec un succès, forcer un rechargement complet de la page
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('success') === 'avatar') {
                // Si le paramètre reload est présent, recharger la page complètement sans cache
                if (urlParams.get('reload') === '1') {
                    // Retirer le paramètre reload de l'URL pour éviter une boucle
                    const newUrl = window.location.pathname + '?success=avatar&t=' + Date.now();
                    setTimeout(function() {
                        window.location.href = newUrl;
                    }, 200);
                    return;
                }
                
                // Sinon, forcer le rechargement de toutes les images d'avatar avec un nouveau timestamp
                setTimeout(function() {
                    const avatarImages = document.querySelectorAll('img[data-avatar], img.profile-avatar, img.avatar-preview, img.avatar-circle');
                    
                    if (avatarImages.length === 0) {
                        // Si aucune image n'est trouvée, recharger la page
                        window.location.reload(true);
                        return;
                    }
                    
                    avatarImages.forEach(function(img) {
                        const avatarName = img.getAttribute('data-avatar');
                        if (avatarName && avatarName !== 'default.png') {
                            // Construire le nouveau chemin avec timestamp
                            const baseUrl = '../../assets/img/' + avatarName;
                            const newSrc = baseUrl + '?v=' + Date.now();
                            
                            // Forcer le rechargement en changeant directement le src
                            const oldSrc = img.src;
                            img.src = newSrc;
                            
                            // Vérifier si l'image se charge correctement
                            setTimeout(function() {
                                if (img.complete && img.naturalHeight === 0) {
                                    // L'image ne s'est pas chargée, recharger la page
                                    window.location.reload(true);
                                }
                            }, 1000);
                        }
                    });
                }, 500);
            }
        });
    </script>
    
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