<?php
session_start();
require_once '../../config/Lang.php';

// Initialiser le système de langue
Lang::init();

// Gérer le changement de langue via GET
if (isset($_GET['lang']) && in_array($_GET['lang'], ['fr', 'en'])) {
    Lang::set($_GET['lang']);
    // Rediriger sans le paramètre lang pour éviter de le garder dans l'URL
    header('Location: ' . str_replace('?lang=' . $_GET['lang'], '', $_SERVER['REQUEST_URI']));
    exit();
}

//COOKIE "SE SOUVENIR DE MOI"
$saved_email = '';
if (isset($_COOKIE['user_email'])) {
    $saved_email = $_COOKIE['user_email'];
}

$currentLang = Lang::current();
$translations = json_decode(Lang::getJSTranslations(), true);
?>

<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $currentLang === 'fr' ? 'Connexion' : 'Login'; ?> | RembourseMaroc</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/login.css">
    
    <style>
        #capsLockWarning { display: none; color: #d01919cc; font-size: 0.85rem; margin-top: 5px; }
    </style>
</head>
<body>
    <!-- ÉCRAN DE CHARGEMENT (SPLASH SCREEN) -->
    <div id="splash-screen">
        <!-- Le fond avec le motif répété -->
        <div class="splash-bg"></div>
        
        <!-- Le logo central -->
        <div class="splash-logo" >
            <i class="bi bi-building-fill-check"></i>
            <span color='#0b0f0bff'>RembourseMaroc</span>
        </div>
    </div>
    <div class="split-screen">
    
   
    <div class="left-pane">
        
        <!-- Logo -->
        <div class="brand-logo">
            <i class="bi bi-building-fill-check " id="logo1"></i> RembourseMaroc
        </div>

        <h1 class="login-title" id="bien" data-i18n="login.title"><?php echo Lang::get('login.title'); ?></h1>

        <div class="login-content">
            
           <!-- <p class="login-subtitle">Accédez à votre espace sécurisé.</p>-->
            
            <!-- SWITCH LANGUE -->
            <div class="lang-switch">
                <a href="?lang=fr" class="<?php echo $currentLang === 'fr' ? 'active' : ''; ?>" id="btn-fr" data-i18n="common.lang_fr"><?php echo Lang::get('common.lang_fr'); ?></a>
                <span class="separator" data-i18n="common.separator"><?php echo Lang::get('common.separator'); ?></span>
                <a href="?lang=en" class="<?php echo $currentLang === 'en' ? 'active' : ''; ?>" id="btn-en" data-i18n="common.lang_en"><?php echo Lang::get('common.lang_en'); ?></a>
            </div> 
          

            <!-- Erreur Identifiants -->
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger border-0 bg-danger-subtle text-danger mb-4 d-flex align-items-center" data-i18n="login.error_bad_credentials">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo Lang::get('login.error_bad_credentials'); ?>
                </div>
            <?php endif; ?>
            
            <!-- Succès Envoi Email (Mot de passe oublié) -->
            <?php if (isset($_GET['msg']) && $_GET['msg'] == 'mail_sent'): ?>
                <div class="alert alert-success border-0 bg-success-subtle text-success mb-4" data-i18n="login.success_mail_sent">
                    <i class="bi bi-send-check-fill me-2"></i> <?php echo Lang::get('login.success_mail_sent'); ?>
                </div>
            <?php endif; ?>

            <!-- Succès Changement Mot de passe -->
            <?php if (isset($_GET['msg']) && $_GET['msg'] == 'password_updated'): ?>
                <div class="alert alert-success border-0 bg-success-subtle text-success mb-4" data-i18n="login.success_password_updated">
                    <i class="bi bi-check-circle-fill me-2"></i> <?php echo Lang::get('login.success_password_updated'); ?>
                </div>
            <?php endif; ?>

            <!-- Succès Envoi Lien Reset 2FA -->
            <?php if (isset($_GET['msg']) && $_GET['msg'] == '2fa_reset_sent'): ?>
                <div class="alert alert-info border-0 bg-info-subtle text-info-emphasis mb-4" data-i18n="login.success_2fa_reset_sent">
                    <i class="bi bi-envelope-fill me-2"></i> <?php echo Lang::get('login.success_2fa_reset_sent'); ?>
                </div>
            <?php endif; ?>

            <!-- Succès Reset 2FA terminé -->
            <?php if (isset($_GET['msg']) && $_GET['msg'] == '2fa_reset_success'): ?>
                <div class="alert alert-success border-0 bg-success-subtle text-success mb-4" data-i18n="login.success_2fa_reset">
                    <i class="bi bi-qr-code-scan me-2"></i> <?php echo Lang::get('login.success_2fa_reset'); ?>
                </div>
            <?php endif; ?>


            <form action="../../actions/login_action.php" method="POST">
                
                <!-- Champ Email -->
                <div class="form-floating mb-3" id="ch1">
                    <input type="email" name="email" class="form-control" id="emailInput" 
                           placeholder="name@company.com" required autocomplete="email"
                           value="<?php echo htmlspecialchars($saved_email); ?>">
                    <label for="emailInput" id="lab1" data-i18n="login.label_email"><?php echo Lang::get('login.label_email'); ?></label>
                </div>

                <div class="form-floating mb-1 position-relative" id="ch2">
                    <input type="password" name="password" class="form-control" id="passInput" 
                           placeholder="Password" required autocomplete="current-password">
                    <label for="passInput" id="lab2" data-i18n="login.label_password"><?php echo Lang::get('login.label_password'); ?></label>
                    
                 
                    <i class="bi bi-eye-slash position-absolute top-50 end-0 translate-middle-y me-3 text-muted" 
                       id="togglePassword" style="cursor: pointer; z-index: 5;" title="<?php echo $currentLang === 'fr' ? 'Afficher/Masquer' : 'Show/Hide'; ?>"></i>
                </div>

                
                <div id="capsLockWarning" data-i18n="login.caps_lock_warning" style="display: none;">
                    <i class="bi bi-arrow-up-square-fill"></i> <?php echo Lang::get('login.caps_lock_warning'); ?>
                </div>

               
                <div class="d-flex justify-content-between align-items-center mt-3 mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="remember" id="rememberMe" 
                               <?php echo ($saved_email) ? 'checked' : ''; ?>>
                        <label class="form-check-label small text-muted" for="rememberMe" data-i18n="login.remember_me"><?php echo Lang::get('login.remember_me'); ?></label>
                    </div>
                    <a href="forgot_pwd.php" class="link-muted fw-semibold" id="pwd" data-i18n="login.forgot_password"><?php echo Lang::get('login.forgot_password'); ?></a>
                </div>

                <!-- Bouton -->
                <button type="submit" class="btn btn-corp w-100" data-i18n="login.login_button">
                    <?php echo Lang::get('login.login_button'); ?>
                </button>
            </form>
        </div>
    </div>

    
    <div class="right-pane">
        <div class="hero-text" data-i18n="login.hero_text">
            <?php echo Lang::get('login.hero_text'); ?> <br>
            <span style="color: #114924ff;" data-i18n="login.hero_highlight"><?php echo Lang::get('login.hero_highlight'); ?></span>
        </div>
        <p class="hero-sub" data-i18n="login.hero_sub">
            <?php echo Lang::get('login.hero_sub'); ?>
        </p>
    </div>

</div>


<script>
   
    const togglePassword = document.querySelector('#togglePassword');
    const password = document.querySelector('#passInput');

    togglePassword.addEventListener('click', function (e) {
        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
        password.setAttribute('type', type);
        this.classList.toggle('bi-eye');
        this.classList.toggle('bi-eye-slash');
    });

    const capsLockWarning = document.getElementById('capsLockWarning');
    if (capsLockWarning && password) {
        password.addEventListener('keyup', function(event) {
            if (event.getModifierState("CapsLock")) {
                capsLockWarning.style.display = "block";
            } else {
                capsLockWarning.style.display = "none";
            }
        });
    }
    
    // --- ANIMATION DE DEMARRAGE (Splash Screen) ---
    document.addEventListener('DOMContentLoaded', function() {
        const splash = document.getElementById('splash-screen');
        if (splash) {
            setTimeout(() => {
                splash.classList.add('finish');
                setTimeout(() => { splash.style.display = 'none'; }, 1200); 
            }, 1000);
        }

        // Nettoyage URL
        if (window.history.replaceState) {
            const url = new URL(window.location.href);
            if (url.searchParams.has('msg') || url.searchParams.has('error')) {
                window.history.replaceState(null, null, window.location.pathname);
            }
        }

        // Disparition Alertes
        const alerts = document.querySelectorAll('.alert');
        if (alerts.length > 0) {
            setTimeout(function() {
                alerts.forEach(function(alert) {
                    alert.style.transition = "opacity 0.8s ease, transform 0.8s ease";
                    alert.style.opacity = "0";
                    alert.style.transform = "translateY(-20px)";
                    setTimeout(function() { alert.remove(); }, 800);
                });
            }, 4000);
        }
    });

    // Charger les traductions depuis PHP
    const translations = <?php echo Lang::getJSTranslations(); ?>;
    const currentLang = '<?php echo $currentLang; ?>';
    
    // Fonction pour obtenir une traduction (support des clés avec points)
    function getTranslation(key) {
        const keys = key.split('.');
        let value = translations;
        for (let k of keys) {
            if (value && value[k]) {
                value = value[k];
            } else {
                return key;
            }
        }
        return value;
    }

    // Mise à jour dynamique des textes (pour les éléments qui changent après chargement)
    function updateTranslations() {
        const elements = document.querySelectorAll('[data-i18n]');
        elements.forEach(el => {
            const key = el.getAttribute('data-i18n');
            const translation = getTranslation(key);
            if (translation && translation !== key) {
                // Si la clé contient du HTML, utiliser innerHTML
                if (['login.hero_text', 'login.caps_lock_warning'].includes(key)) {
                    el.innerHTML = translation;
                } else {
                    el.textContent = translation;
                }
            }
        });
    }
    
    // Mettre à jour les traductions au chargement
    document.addEventListener('DOMContentLoaded', updateTranslations);
</script>

</body>

</html>
