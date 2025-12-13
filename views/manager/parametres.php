<?php
session_start();
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../config/Lang.php';

Lang::init();
protect_page('manager');

$pdo = Database::getInstance()->getConnexion();
$managerId = $_SESSION['user_id'];
$userName = $_SESSION['user']['nom'] ?? 'Manager';

// Récupérer les informations du manager
$sql = "SELECT nom, prenom, email, tel, avatar FROM users WHERE user_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$managerId]);
$userInfo = $stmt->fetch(PDO::FETCH_ASSOC);

$avatarFile = $userInfo['avatar'] ?? 'default.png';
$avatar = '../../assets/img/' . $avatarFile;

// Notifications (même code que dashboard)
try {
    // Créer la table de suivi si elle n'existe pas
    $pdo->exec("CREATE TABLE IF NOT EXISTS reclamation_views (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reclamation_id INT NOT NULL,
        manager_id INT NOT NULL,
        viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_view (reclamation_id, manager_id),
        FOREIGN KEY (manager_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $sqlReclamations = "SELECT r.id_reclam, r.id_dem, r.message, r.statut, r.created_at,
                               d.titre_dem, d.montant_total,
                               u.nom as employee_name, u.user_id as employee_id
                        FROM reclamations r
                        JOIN demande d ON r.id_dem = d.id_dem
                        JOIN users u ON d.user_id = u.user_id
                        LEFT JOIN reclamation_views rv ON r.id_reclam = rv.reclamation_id AND rv.manager_id = ?
                        WHERE u.manager_id = ?
                        AND r.statut != 'resolu'
                        AND rv.id IS NULL
                        ORDER BY r.created_at DESC
                        LIMIT 10";
    $stmt = $pdo->prepare($sqlReclamations);
    $stmt->execute([$managerId, $managerId]);
    $reclamations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $reclamations = [];
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type ENUM('clarification', 'validation', 'rejet') NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        related_id INT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    )");
} catch (PDOException $e) {}

try {
    $sqlNotifications = "SELECT n.*, u.nom as from_user_name
                       FROM notifications n
                       LEFT JOIN users u ON n.user_id = u.user_id
                       WHERE u.manager_id = ?
                       AND n.user_id != ?
                       AND n.type NOT IN ('validation', 'rejet')
                       AND n.is_read = 0
                       ORDER BY n.created_at DESC
                       LIMIT 10";
    $stmt = $pdo->prepare($sqlNotifications);
    $stmt->execute([$managerId, $managerId]);
    $otherNotifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $otherNotifications = [];
}

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
        'statut' => $rec['statut']
    ];
}
foreach ($otherNotifications as $notif) {
    $allNotifications[] = [
        'id' => $notif['id'],
        'type' => $notif['type'],
        'title' => $notif['title'],
        'message' => $notif['message'],
        'created_at' => $notif['created_at'],
        'from_user_name' => $notif['from_user_name'] ?? null
    ];
}

usort($allNotifications, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

$notifications = array_slice($allNotifications, 0, 10);
$notificationCount = count($notifications);

// Récupérer le thème actuel
$currentTheme = $_SESSION['theme'] ?? $_COOKIE['app_theme'] ?? 'light';

// Messages de succès/erreur
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// Charger les traductions pour JavaScript
$translations = json_decode(Lang::getJSTranslations(), true);
?>

<!DOCTYPE html>
<html lang="<?= Lang::current() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres du Compte | Rembourse Maroc</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/dark-theme.css">
    <style>
        .logo-rm {
            width: 32px;
            height: 32px;
            background: #059669;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 14px;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2), 0 0 0 1px rgba(255, 255, 255, 0.1) inset;
        }
        .settings-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .settings-card h5 {
            color: #059669;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
        }
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
        .avatar-upload-label {
            cursor: pointer;
            display: inline-block;
            margin-top: 15px;
        }
        .form-control:focus, .form-select:focus {
            border-color: #059669;
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
        }
        .btn-primary {
            background: #059669;
            border: none;
        }
        .btn-primary:hover {
            background: #047857;
        }
        .password-section {
            border-top: 2px solid #e2e8f0;
            margin-top: 30px;
            padding-top: 30px;
        }
        .toggle-pass {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #64748b;
            cursor: pointer;
            padding: 5px;
        }
        .toggle-pass:hover {
            color: #059669;
        }
        .feedback-text {
            font-size: 0.85rem;
            margin-top: 5px;
        }
        .text-valid {
            color: #10b981;
        }
        .text-error {
            color: #ef4444;
        }
        .input-valid {
            border-color: #10b981 !important;
        }
        .input-error {
            border-color: #ef4444 !important;
        }
    </style>
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
            <div class="dropdown position-relative">
                <a href="#" class="notification-bell position-relative text-decoration-none" data-bs-toggle="dropdown" id="notificationDropdown">
                    <i class="bi bi-bell fs-5"></i>
                    <span class="notification-badge" style="display: <?= $notificationCount > 0 ? 'flex' : 'none' ?>;"><?= $notificationCount ?></span>
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
                                'reclamation' => 'bi-exclamation-triangle text-warning',
                                'clarification' => 'bi-question-circle',
                                'validation' => 'bi-check-circle text-success',
                                'rejet' => 'bi-x-circle text-danger',
                                default => 'bi-info-circle'
                            };
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
        <div class="mb-4">
            <h4 class="fw-bold m-0 text-dark">Paramètres du Compte</h4>
            <div class="text-muted small">Gérez vos informations personnelles et votre sécurité</div>
        </div>

        <div class="row g-4">
            <!-- Colonne Gauche : Carte Manager avec Apparence -->
            <div class="col-lg-4">
                <div class="settings-card">
                    <h5><i class="bi bi-person-badge me-2"></i>Manager</h5>
                    <div class="text-center mb-4">
                        <img src="<?= htmlspecialchars($avatar) ?>" alt="Avatar" class="avatar-preview mb-3" style="width: 100px; height: 100px;">
                        <h6 class="fw-bold mb-1"><?= htmlspecialchars($userInfo['prenom'] ?? '') ?> <?= htmlspecialchars($userInfo['nom'] ?? '') ?></h6>
                        <p class="text-muted small mb-0"><?= htmlspecialchars($userInfo['email'] ?? '') ?></p>
                    </div>
                    
                    <hr class="my-4">
                    
                    <!-- Section Thème dans la carte Manager -->
                    <h6 class="fw-bold mb-3"><i class="bi bi-palette me-2"></i><?= Lang::get('settings.theme_title', 'Apparence') ?></h6>
                    <p class="text-muted small mb-3"><?= Lang::get('settings.theme_subtitle', 'Choisissez votre thème préféré') ?></p>
                    <form action="../../actions/update_theme.php" method="POST">
                        <div class="d-flex flex-column gap-2">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="theme" id="theme_light" value="light" <?= $currentTheme === 'light' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="theme_light">
                                    <i class="bi bi-sun-fill me-2"></i><?= Lang::get('settings.theme_light', 'Clair') ?>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="theme" id="theme_dark" value="dark" <?= $currentTheme === 'dark' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="theme_dark">
                                    <i class="bi bi-moon-fill me-2"></i><?= Lang::get('settings.theme_dark', 'Sombre') ?>
                                </label>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mt-3">
                            <i class="bi bi-save me-2"></i><?= Lang::get('settings.theme_save', 'Enregistrer le thème') ?>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Colonne Droite : Autres sections -->
            <div class="col-lg-8">
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i>
                <?php
                $messages = [
                    'avatar' => Lang::get('settings.success_avatar', 'Photo de profil mise à jour avec succès'),
                    'profile' => Lang::get('settings.success_profile', 'Informations mises à jour avec succès'),
                    'password' => Lang::get('settings.success_password', 'Mot de passe modifié avec succès'),
                    'theme' => Lang::get('settings.success_theme', 'Thème mis à jour avec succès')
                ];
                echo $messages[$success] ?? 'Opération réussie';
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <?php
                $messages = [
                    'upload' => Lang::get('settings.error_upload', 'Erreur lors du téléchargement de la photo'),
                    'format' => Lang::get('settings.error_format', 'Format de fichier non supporté. Utilisez JPG, PNG ou JPEG'),
                    'size' => Lang::get('settings.error_size', 'Fichier trop volumineux. Taille maximale : 2MB'),
                    'profile' => Lang::get('settings.error_profile', 'Erreur lors de la mise à jour des informations'),
                    'password' => Lang::get('settings.error_password', 'Erreur lors de la modification du mot de passe'),
                    'password_weak' => Lang::get('settings.error_password_weak', 'Le mot de passe ne respecte pas les critères de sécurité'),
                    'password_mismatch' => Lang::get('settings.error_password_mismatch', 'Les mots de passe ne correspondent pas'),
                    'password_current' => Lang::get('settings.error_password_current', 'Le mot de passe actuel est incorrect'),
                    'email_exists' => Lang::get('settings.error_email_exists', 'Cet email est déjà utilisé par un autre compte')
                ];
                echo $messages[$error] ?? 'Une erreur est survenue';
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Section Photo de Profil -->
        <div class="settings-card">
            <h5><i class="bi bi-person-circle me-2"></i>Photo de Profil</h5>
            <form action="../../actions/update_avatar_manager.php" method="POST" enctype="multipart/form-data">
                <div class="d-flex align-items-center gap-4">
                    <div>
                        <img src="<?= htmlspecialchars($avatar) ?>" alt="Avatar" class="avatar-preview" id="avatarPreview" onclick="document.getElementById('avatarInput').click()">
                        <input type="file" name="avatar" id="avatarInput" accept="image/jpeg,image/jpg,image/png" style="display: none;" onchange="previewAvatar(this)">
                    </div>
                    <div class="flex-grow-1">
                        <p class="text-muted mb-3">Formats acceptés : JPG, PNG, JPEG (max 2MB)</p>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload me-2"></i>Changer la photo
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Section Coordonnées -->
        <div class="settings-card">
            <h5><i class="bi bi-person-lines-fill me-2"></i>Mes Coordonnées</h5>
            <form action="../../actions/update_profile.php" method="POST">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Nom</label>
                        <input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($userInfo['nom'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Prénom</label>
                        <input type="text" name="prenom" class="form-control" value="<?= htmlspecialchars($userInfo['prenom'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($userInfo['email'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Téléphone</label>
                        <input type="tel" name="tel" class="form-control" value="<?= htmlspecialchars($userInfo['tel'] ?? '') ?>" placeholder="+212 6XX XXX XXX">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-2"></i>Enregistrer les modifications
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Section Mot de Passe -->
        <div class="settings-card">
            <h5><i class="bi bi-shield-lock me-2"></i>Changer le Mot de Passe</h5>
            <form action="../../actions/update_password.php" method="POST" id="passwordForm">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-bold">Mot de passe actuel</label>
                        <div class="position-relative">
                            <input type="password" name="current_password" id="currentPass" class="form-control" placeholder="Entrez votre mot de passe actuel" required>
                            <button type="button" class="toggle-pass" onclick="togglePass('currentPass', this)">
                                <i class="bi bi-eye-slash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Nouveau mot de passe</label>
                        <div class="position-relative">
                            <input type="password" name="new_password" id="newPass" class="form-control" placeholder="Entrez votre nouveau mot de passe" required>
                            <button type="button" class="toggle-pass" onclick="togglePass('newPass', this)">
                                <i class="bi bi-eye-slash"></i>
                            </button>
                        </div>
                        <div id="passFeedback" class="feedback-text text-muted">
                            Le mot de passe doit contenir au moins 8 caractères, une majuscule, un chiffre et un caractère spécial
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Confirmer le nouveau mot de passe</label>
                        <div class="position-relative">
                            <input type="password" name="confirm_password" id="confirmPass" class="form-control" placeholder="Confirmez votre nouveau mot de passe" required disabled>
                            <button type="button" class="toggle-pass" onclick="togglePass('confirmPass', this)">
                                <i class="bi bi-eye-slash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                            <i class="bi bi-key me-2"></i>Modifier le mot de passe
                        </button>
                    </div>
                </div>
            </form>
        </div>

                </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/theme.js"></script>
    <script>
        // Appliquer le thème au chargement
        const currentTheme = '<?= $currentTheme ?>';
        if (currentTheme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    </script>
    <script>
        // Preview avatar
        function previewAvatar(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('avatarPreview').src = e.target.result;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Toggle password visibility
        function togglePass(inputId, icon) {
            const input = document.getElementById(inputId);
            if (input.type === "password") {
                input.type = "text";
                icon.classList.replace('bi-eye-slash', 'bi-eye');
            } else {
                input.type = "password";
                icon.classList.replace('bi-eye', 'bi-eye-slash');
            }
        }

        // Password validation (same as reset_pwd.php)
        const passInput = document.getElementById('newPass');
        const confirmInput = document.getElementById('confirmPass');
        const feedback = document.getElementById('passFeedback');
        const btn = document.getElementById('submitBtn');

        const rules = [
            { regex: /.{8,}/, message: "Au moins 8 caractères" },
            { regex: /[A-Z]/, message: "Une majuscule" },
            { regex: /[a-z]/, message: "Une minuscule" },
            { regex: /[0-9]/, message: "Un chiffre" },
            { regex: /[\W_]/, message: "Un caractère spécial" }
        ];

        if (passInput) {
            passInput.addEventListener('input', function() {
                const val = this.value;
                let error = null;

                for (let rule of rules) {
                    if (!rule.regex.test(val)) {
                        error = rule.message;
                        break;
                    }
                }

                if (val.length === 0) {
                    resetState();
                } else if (error) {
                    this.classList.add('input-error');
                    this.classList.remove('input-valid');
                    feedback.textContent = error;
                    feedback.className = "feedback-text text-error";
                    if (confirmInput) confirmInput.disabled = true;
                    if (btn) btn.disabled = true;
                } else {
                    this.classList.remove('input-error');
                    this.classList.add('input-valid');
                    feedback.textContent = "Mot de passe sécurisé ✓";
                    feedback.className = "feedback-text text-valid";
                    if (confirmInput) confirmInput.disabled = false;
                    checkConfirm();
                }
            });
        }

        if (confirmInput) {
            confirmInput.addEventListener('input', checkConfirm);
        }

        function checkConfirm() {
            if (!passInput || !confirmInput || !btn) return;
            
            if (confirmInput.value === passInput.value && confirmInput.value !== "") {
                confirmInput.classList.add('input-valid');
                confirmInput.classList.remove('input-error');
                btn.disabled = false;
                btn.style.opacity = "1";
            } else if (confirmInput.value !== "") {
                confirmInput.classList.add('input-error');
                btn.disabled = true;
                btn.style.opacity = "0.6";
            }
        }

        function resetState() {
            if (!passInput || !confirmInput || !feedback || !btn) return;
            passInput.classList.remove('input-error', 'input-valid');
            feedback.textContent = "Le mot de passe doit contenir au moins 8 caractères, une majuscule, un chiffre et un caractère spécial";
            feedback.className = "feedback-text text-muted";
            confirmInput.classList.remove('input-error', 'input-valid');
            confirmInput.value = "";
            confirmInput.disabled = true;
            btn.disabled = true;
        }

        // Gestion des notifications - Marquer comme lue au clic
        document.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', async function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const notificationId = this.getAttribute('data-notification-id');
                const notificationType = this.getAttribute('data-notification-type');
                const demandId = this.getAttribute('data-demand-id');
                
                // Marquer la notification comme lue
                if (notificationId && notificationType) {
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
                            // Retirer la notification de la liste visuellement
                            this.style.opacity = '0.5';
                            this.style.pointerEvents = 'none';
                            
                            // Retirer la notification de la liste après un court délai
                            setTimeout(() => {
                                this.remove();
                                updateNotificationBadge();
                            }, 300);
                            
                            // Mettre à jour le badge immédiatement
                            updateNotificationBadge();
                        } else {
                            console.error('Erreur:', result.message);
                        }
                    } catch (error) {
                        console.error('Erreur lors de la mise à jour de la notification:', error);
                    }
                }
                
                // Rediriger si c'est une réclamation avec une demande
                if (demandId) {
                    setTimeout(() => {
                        window.location.href = 'details_validation.php?id=' + demandId;
                    }, 500);
                }
            });
        });

        // Fonction pour mettre à jour le badge de notification
        function updateNotificationBadge() {
            const notificationItems = document.querySelectorAll('.notification-item:not([style*="opacity: 0.5"]):not([style*="pointer-events: none"])');
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
    </script>
</body>
</html>

