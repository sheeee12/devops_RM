<?php
session_start();
require_once '../../config/Lang.php';

// Initialiser le système de langue
Lang::init();

// Gérer le changement de langue via GET
if (isset($_GET['lang']) && in_array($_GET['lang'], ['fr', 'en'])) {
    Lang::set($_GET['lang']);
    header('Location: ' . str_replace('?lang=' . $_GET['lang'], '', $_SERVER['REQUEST_URI']));
    exit();
}

// S'assurer que la langue est sauvegardée dans la session
$currentLang = Lang::current();
if (!isset($_SESSION['lang']) || $_SESSION['lang'] !== $currentLang) {
    Lang::set($currentLang);
}
$translations = json_decode(Lang::getJSTranslations(), true);
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo Lang::get('forgot_password.title'); ?> | Gestion Frais</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/forgot_pwd_sty.css">
   
</head>
<body>
    <div class="brand-logo">
        <i class="bi bi-building-fill-check" id="logo1"></i>
        <span>RembourseMaroc</span>
    </div>
    <!--<div class="bg-image"> <i class="bi bi-shield-check" ></i></div>-->
    <div class="recovery-card">
        
        <!--<div class="icon-box">
            <i class="bi bi-envelope-paper-fill"></i>
        </div>-->

        <h1 data-i18n="forgot_password.title"><?php echo Lang::get('forgot_password.title'); ?></h1>
        <p class="subtitle" data-i18n="forgot_password.subtitle">
            <?php echo Lang::get('forgot_password.subtitle'); ?>
        </p>

        
        <?php if (isset($_GET['error']) && $_GET['error'] == 'email_not_found'): ?>
            <div class="alert alert-danger border-0 bg-danger-subtle text-danger mb-4 d-flex align-items-center" data-i18n="forgot_password.error_email_not_found">
                <i class="bi bi-x-circle-fill me-2"></i> <?php echo Lang::get('forgot_password.error_email_not_found'); ?>
            </div>
        <?php endif; ?>

        <form action="../../actions/send_reset_link.php" method="POST">
            <!-- Champ caché pour transmettre la langue -->
            <input type="hidden" name="lang" value="<?php echo $currentLang; ?>">
            
            <div class="form-floating mb-3">
                <input type="email" name="email" class="form-control" id="recupEmail" 
                       placeholder="name@example.com" required autocomplete="email">
                <label for="recupEmail" data-i18n="forgot_password.label_email"><?php echo Lang::get('forgot_password.label_email'); ?></label>
            </div>

            <button type="submit" class="btn btn-recover" data-i18n="forgot_password.send_link_button">
                <?php echo Lang::get('forgot_password.send_link_button'); ?>
            </button>

        </form>

        <a href="login.php" class="back-link" data-i18n="forgot_password.back_to_login">
            <i class="bi bi-arrow-left me-1"></i> <?php echo Lang::get('forgot_password.back_to_login'); ?>
        </a>

    </div>

    <script>
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

        // Mise à jour dynamique des textes
        function updateTranslations() {
            const elements = document.querySelectorAll('[data-i18n]');
            elements.forEach(el => {
                const key = el.getAttribute('data-i18n');
                const translation = getTranslation(key);
                if (translation && translation !== key) {
                    el.textContent = translation;
                }
            });
        }
        
        document.addEventListener('DOMContentLoaded', () => {
            updateTranslations();
            const elements = document.querySelectorAll('[data-i18n]');
            elements.forEach(el => el.style.transition = "opacity 0.3s ease");
        });
    </script>
</body>
</html>