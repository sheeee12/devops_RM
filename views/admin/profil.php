<?php
// -------------------------------------------------------------------------
// PROFIL ADMINISTRATEUR
// -------------------------------------------------------------------------
require_once __DIR__ . '/../../includes/session.php';
requireRole('admin');
require_once __DIR__ . '/../../config/Database.php';

$user_id = $_SESSION['user']['user_id'];
$pdo = Database::getInstance()->getConnexion();

// Récupération des données utilisateur
$sql = "SELECT * FROM users WHERE user_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$infos = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$infos) {
    header('Location: dashboard.php');
    exit;
}

// Variables d'affichage
$user_name = $infos['nom'] ?? 'Administrateur';
$user_email = $infos['email'] ?? '';

// Gestion Avatar
$avatar_bdd = $infos['avatar'] ?? 'default.png';
$chemin_physique = __DIR__ . '/../../assets/img/' . $avatar_bdd;
if (file_exists($chemin_physique) && !empty($avatar_bdd) && $avatar_bdd !== 'default.png') {
    $avatar = '../../assets/img/' . $avatar_bdd . '?v=' . time();
} else {
    $avatar = '../../assets/img/default.png';
}

// Recharger les données si succès avatar
if (isset($_GET['success']) && $_GET['success'] === 'avatar') {
    usleep(200000);
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $infos = $stmt->fetch(PDO::FETCH_ASSOC);
    $avatar_bdd = $infos['avatar'] ?? 'default.png';
    $chemin_physique = __DIR__ . '/../../assets/img/' . $avatar_bdd;
    if (file_exists($chemin_physique) && !empty($avatar_bdd) && $avatar_bdd !== 'default.png') {
        $avatar = '../../assets/img/' . $avatar_bdd . '?v=' . time();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mon Profil | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
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

        [data-theme="dark"] {
            --app-bg: #0f172a;
            --header-bg: #1e293b;
            --header-border: #334155;
            --text-main: #f1f5f9;
            --text-light: #94a3b8;
            --card-border: #334155;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--app-bg);
            color: var(--text-main);
            font-size: 0.875rem;
            padding-top: 70px;
            transition: background-color 0.3s, color 0.3s;
        }

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

        @keyframes logo-bounce {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); box-shadow: 0 0 15px rgba(5, 150, 105, 0.4); }
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
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            transition: border-color 0.3s;
        }

        [data-theme="dark"] .avatar-circle {
            border-color: var(--header-border);
        }

        .page-container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .card-pro {
            background: var(--header-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 24px;
            transition: background-color 0.3s, border-color 0.3s;
        }

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
            border: 4px solid var(--header-bg);
            object-fit: cover;
            background: var(--header-bg);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            display: block;
            margin: 0 auto;
            transition: border-color 0.3s;
        }

        .btn-edit-avatar {
            position: absolute;
            bottom: 5px;
            right: 50%;
            transform: translateX(45px);
            width: 36px;
            height: 36px;
            background: var(--header-bg);
            border: 1px solid var(--card-border);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-main);
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .btn-edit-avatar:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .section-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--card-border);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title {
            font-weight: 600;
            font-size: 1.05rem;
            margin: 0;
            color: var(--text-main);
        }

        .section-icon {
            color: var(--primary);
            background: #ecfdf5;
            padding: 6px;
            border-radius: 6px;
        }

        .section-content {
            padding: 24px;
        }

        .form-control-pro {
            border: 1px solid var(--card-border);
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 0.95rem;
            background: var(--header-bg);
            color: var(--text-main);
            transition: all 0.3s;
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
            transition: all 0.3s;
        }

        .btn-pro:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .theme-toggle {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: var(--header-bg);
            border: 1px solid var(--card-border);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .theme-toggle:hover {
            border-color: var(--primary);
        }

        .theme-switch {
            position: relative;
            width: 50px;
            height: 26px;
            background: #cbd5e1;
            border-radius: 13px;
            transition: background 0.3s;
        }

        [data-theme="dark"] .theme-switch {
            background: var(--primary);
        }

        .theme-switch::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            background: white;
            border-radius: 50%;
            top: 3px;
            left: 3px;
            transition: transform 0.3s;
        }

        [data-theme="dark"] .theme-switch::after {
            transform: translateX(24px);
        }

        .alert {
            border-radius: 8px;
            border: none;
        }
    </style>
</head>
<body>

    <header class="app-header">
        <div class="d-flex align-items-center gap-2">
            <div class="brand-logo">RM</div>
            <span class="fw-bold text-dark">RembourseMaroc</span>
        </div>
        <nav class="app-nav d-none d-md-flex">
            <a href="dashboard.php" class="nav-item-link"><i class="bi bi-grid-fill me-2"></i>Dashboard</a>
            <a href="manage_pending.php" class="nav-item-link"><i class="bi bi-layers-fill me-2"></i>Paiements</a>
            <a href="manage_data.php?tab=users" class="nav-item-link"><i class="bi bi-people me-2"></i>Utilisateurs</a>
            <a href="manage_data.php?tab=teams" class="nav-item-link"><i class="bi bi-diagram-3 me-2"></i>Équipes</a>
            <a href="manage_categories.php" class="nav-item-link"><i class="bi bi-tags me-2"></i>Catégories</a>
            <a href="manage_reclamations.php" class="nav-item-link"><i class="bi bi-life-preserver me-2"></i>Réclamations</a>
        </nav>
        <div class="dropdown">
            <a href="#" class="d-flex align-items-center gap-2 text-decoration-none" data-bs-toggle="dropdown">
                <div class="text-end d-none d-sm-block">
                    <div class="fw-bold text-dark small"><?= htmlspecialchars($user_name) ?></div>
                    <div class="text-muted" style="font-size: 0.65rem;">Administrateur</div>
                </div>
                <img src="<?= htmlspecialchars($avatar) ?>" class="avatar-circle" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover;">
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-3 p-2 rounded-3">
                <li><a class="dropdown-item rounded-2" href="profil.php">Mon Profil</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item rounded-2 text-danger" href="../../actions/logout.php">Déconnexion</a></li>
            </ul>
        </div>
    </header>

    <div class="page-container">
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php
                if ($_GET['success'] === 'avatar') {
                    echo 'Photo de profil mise à jour avec succès !';
                } elseif ($_GET['success'] === 'password') {
                    echo 'Mot de passe modifié avec succès !';
                }
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php
                switch ($_GET['error']) {
                    case 'upload':
                        echo 'Erreur lors du téléchargement de la photo.';
                        break;
                    case 'format':
                        echo 'Format de fichier non supporté. Formats acceptés : JPG, PNG.';
                        break;
                    case 'size':
                        echo 'Le fichier est trop volumineux. Taille maximale : 2MB.';
                        break;
                    case 'password':
                        echo 'Erreur lors de la modification du mot de passe.';
                        break;
                    case 'password_mismatch':
                        echo 'Les nouveaux mots de passe ne correspondent pas.';
                        break;
                    case 'password_weak':
                        echo 'Le mot de passe doit contenir au moins 8 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.';
                        break;
                    case 'password_current':
                        echo 'Le mot de passe actuel est incorrect.';
                        break;
                    default:
                        echo 'Une erreur est survenue.';
                }
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Photo de Profil -->
        <div class="card-pro">
            <div class="profile-cover"></div>
            <div class="profile-avatar-wrapper">
                <img src="<?= htmlspecialchars($avatar) ?>" alt="Avatar" class="profile-avatar" id="avatarPreview">
                <form action="../../actions/update_avatar_admin.php" method="POST" enctype="multipart/form-data" id="avatarForm">
                    <input type="file" name="avatar" id="avatarInput" accept="image/jpeg,image/png,image/jpg" style="display: none;" onchange="document.getElementById('avatarForm').submit();">
                    <button type="button" class="btn-edit-avatar" onclick="document.getElementById('avatarInput').click();">
                        <i class="bi bi-camera-fill"></i>
                    </button>
                </form>
            </div>
            <div class="section-content text-center">
                <h5 class="fw-bold mb-1"><?= htmlspecialchars($user_name) ?></h5>
                <p class="text-muted mb-0"><?= htmlspecialchars($user_email) ?></p>
            </div>
        </div>

        <!-- Thème -->
        <div class="card-pro">
            <div class="section-header">
                <div class="section-icon"><i class="bi bi-palette-fill"></i></div>
                <h6 class="section-title mb-0">Thème</h6>
            </div>
            <div class="section-content">
                <div class="theme-toggle" onclick="toggleTheme()">
                    <div>
                        <div class="fw-bold mb-1">Mode sombre</div>
                        <div class="text-muted small">Activer le thème sombre</div>
                    </div>
                    <div class="theme-switch"></div>
                </div>
            </div>
        </div>

        <!-- Changement de mot de passe -->
        <div class="card-pro">
            <div class="section-header">
                <div class="section-icon"><i class="bi bi-shield-lock-fill"></i></div>
                <h6 class="section-title mb-0">Changement de mot de passe</h6>
            </div>
            <div class="section-content">
                <form action="../../actions/update_password_admin.php" method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Mot de passe actuel</label>
                        <input type="password" name="old_password" class="form-control form-control-pro" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nouveau mot de passe</label>
                        <input type="password" name="new_password" class="form-control form-control-pro" required>
                        <small class="text-muted">Au moins 8 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Confirmer le nouveau mot de passe</label>
                        <input type="password" name="confirm_password" class="form-control form-control-pro" required>
                    </div>
                    <button type="submit" class="btn btn-pro">Modifier le mot de passe</button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/admin_theme.js"></script>
    <script>
        // Gestion du thème
        function getTheme() {
            return localStorage.getItem('admin_theme') || 'light';
        }

        function setTheme(theme) {
            localStorage.setItem('admin_theme', theme);
            document.documentElement.setAttribute('data-theme', theme);
            // Synchroniser avec le script global
            if (window.applyAdminTheme) {
                window.applyAdminTheme(theme);
            }
        }

        function toggleTheme() {
            const currentTheme = getTheme();
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            setTheme(newTheme);
            updateThemeSwitch();
        }

        // Appliquer le thème au chargement
        const savedTheme = getTheme();
        setTheme(savedTheme);

        // Mettre à jour l'affichage du switch de thème
        function updateThemeSwitch() {
            const theme = getTheme();
            const switchElement = document.querySelector('.theme-switch');
            if (switchElement) {
                if (theme === 'dark') {
                    switchElement.style.background = 'var(--primary)';
                } else {
                    switchElement.style.background = '#cbd5e1';
                }
            }
        }

        // Mettre à jour le switch au chargement
        updateThemeSwitch();
    </script>
</body>
</html>

