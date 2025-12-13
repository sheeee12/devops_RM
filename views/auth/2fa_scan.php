<?php
session_start();
require_once '../../classes/User.php';
require_once '../../config/Lang.php';

// Initialiser le système de langue
Lang::init();

// Gérer le changement de langue via GET
if (isset($_GET['lang']) && in_array($_GET['lang'], ['fr', 'en'])) {
    Lang::set($_GET['lang']);
    header('Location: ' . str_replace('?lang=' . $_GET['lang'], '', $_SERVER['REQUEST_URI']));
    exit();
}

// Sécurité : Pas d'accès direct sans être passé par le login (étape 1)
if (!isset($_SESSION['temp_user_id'])) { 
    header('Location: login.php'); 
    exit(); 
}

$userId = $_SESSION['temp_user_id'];
$email = $_SESSION['temp_user_email'];

// Récupération du secret
$data = User::get2FASecret($userId); 
$secret = $data['secret'];
$isNew = $data['is_new']; 
$qrUrl = User::getQRUrl($email, $secret);

$currentLang = Lang::current();
$translations = json_decode(Lang::getJSTranslations(), true);
?>

<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo Lang::get('2fa.verify_title'); ?> | Sécurité</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/2fa.css">
</head>

<body>

  
    

    <div class="split-screen">
        
        <!-- GAUCHE : FORMULAIRE -->
        <div class="left-pane" style="background-color: white;">
            
            <div class="brand-logo">
                <i class="bi bi-building-fill-check" style="color: #059669;"></i>
                <span style="color: #0b0f0bff">RembourseMaroc</span>
            </div>

            <div class="login-content text-center" style="color: #020b08ff;">
                
                <div class="mb-4">
                    <!-- Espace pour icône si besoin -->
                </div>

                <?php if ($isNew): ?>
                    <!-- CAS 1 : CONFIGURATION -->
                    
                    <div class="alert alert-light  shadow-sm text-start small mb-4 p-3 ">
                        <strong data-i18n="2fa.instruct_title"><?php echo Lang::get('2fa.instruct_title'); ?></strong><br>
                        <span data-i18n="2fa.instruct_1"><?php echo str_replace('Google Authenticator', '<strong style="color: #059669;">Google Authenticator</strong>', Lang::get('2fa.instruct_1')); ?></span><br>
                        <span data-i18n="2fa.instruct_2"><?php echo Lang::get('2fa.instruct_2'); ?></span><br>
                        <span data-i18n="2fa.instruct_3"><?php echo Lang::get('2fa.instruct_3'); ?></span>
                    </div>

                    <div class="mb-4 p-2 border rounded d-inline-block bg-white shadow-sm">
                        <img src="<?php echo $qrUrl; ?>" alt="QR Code" width="160">
                    </div>
                
                <?php else: ?>
                    <!-- CAS 2 : VERIFICATION -->
                    <h1 class="login-title h3" data-i18n="2fa.verify_title"><?php echo Lang::get('2fa.verify_title'); ?></h1>
                    <p class="login-subtitle" data-i18n="2fa.verify_sub"><?php echo Lang::get('2fa.verify_sub'); ?></p>
                <?php endif; ?>

                <!-- ERREURS -->
                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger border-0 bg-danger-subtle text-danger py-2 mb-3 small" data-i18n="2fa.error_invalid_code">
                        <i class="bi bi-x-circle-fill me-2"></i> <?php echo Lang::get('2fa.error_invalid_code'); ?>
                    </div>
                <?php endif; ?>

                <!-- FORMULAIRE -->
                <form action="../../actions/login_action.php" method="POST">
                    
                    <div class="form-floating mb-4" id="btn">
                        <input type="text" name="code" class="form-control text-center fs-3 fw-bold letter-spacing-3 " 
                               id="code2fa" placeholder="000 000" maxlength="6" autocomplete="off" autofocus required>
                        <label for="code2fa" class="text-center w-100" id="labelCode" data-i18n="2fa.label_code"><?php echo Lang::get('2fa.label_code'); ?></label>
                    </div>
                    
                    <button type="submit" class="btn btn-corp py-3" id="btn" data-i18n="2fa.validate_button">
                        <?php echo Lang::get('2fa.validate_button'); ?> <i class="bi bi-arrow-right-short"></i>
                    </button>
                </form>

                <!-- LIEN DE SECOURS -->
                <div class="mt-4 border-top pt-3">
                    <p class="text-muted small mb-2" data-i18n="2fa.lost_app_text"><?php echo Lang::get('2fa.lost_app_text'); ?></p>
                    <form action="../../actions/reset_2fa_ask.php" method="POST">
                        <button type="submit" class="btn btn-link text-decoration-none p-0 link-muted fw-bold small">
                            <i class="bi bi-envelope-at me-1"></i> <span data-i18n="2fa.reset_email_button"><?php echo Lang::get('2fa.reset_email_button'); ?></span>
                        </button>
                    </form>
                </div>

            </div>
        </div>

        <!-- DROITE :  -->
        <div class="right-pane">
            <div class="hero-content">
                <h1 class="hero-text" data-i18n="2fa.hero_title">
                    <span class="accent"><?php echo Lang::get('2fa.hero_title'); ?></span>
                </h1>
                <p class="hero-sub" data-i18n="2fa.hero_sub">
                    <?php echo Lang::get('2fa.hero_sub'); ?>
                </p>
            </div>

            <!-- Déco de fond -->
            <div class="background-deco">
                <i class="bi bi-fingerprint" style="font-size: 350px; color: rgba(255,255,255,0.05);"></i>
            </div>
        </div>

    </div>

    <!-- JS -->
    <script>
        // Charger les traductions depuis PHP
        const translations = <?php echo Lang::getJSTranslations(); ?>;
        const currentLang = '<?php echo $currentLang; ?>';
        
        // Fonction pour obtenir une traduction
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

        document.addEventListener('DOMContentLoaded', function() {
            // NETTOYAGE URL
            if (window.history.replaceState) {
                const url = new URL(window.location.href);
                if (url.searchParams.has('error')) {
                    window.history.replaceState(null, null, window.location.pathname);
                }
            }

            // Mise à jour des traductions
            const elements = document.querySelectorAll('[data-i18n]');
            elements.forEach(el => {
                const key = el.getAttribute('data-i18n');
                const translation = getTranslation(key);
                if (translation && translation !== key) {
                    // Si la clé contient du HTML, utiliser innerHTML
                    if (['2fa.hero_title', '2fa.instruct_1', '2fa.error_invalid_code'].includes(key)) {
                        el.innerHTML = translation;
                    } else {
                        el.textContent = translation;
                    }
                }
            });
        });
    </script>

</body>
</html>