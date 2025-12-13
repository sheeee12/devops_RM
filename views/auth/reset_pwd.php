<?php
session_start();
require_once '../../config/Database.php';
require_once '../../config/Lang.php';

// Initialiser le système de langue
Lang::init();

// Gérer le changement de langue via GET (sans redirection pour éviter les boucles)
if (isset($_GET['lang']) && in_array($_GET['lang'], ['fr', 'en'])) {
    Lang::set($_GET['lang']);
}

$token = $_GET['token'] ?? '';
$isValid = false;

if (!empty($token)) {
    $pdo = Database::getInstance()->getConnexion();
    
    $tokenHash = hash("sha256", $token);
    
    $sql = "SELECT user_id FROM users 
            WHERE reset_token_hash = ? 
            AND reset_expires_at > NOW()";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tokenHash]);
    
    if ($stmt->fetch()) {
        $isValid = true;
    }
}

$currentLang = Lang::current();
$translations = json_decode(Lang::getJSTranslations(), true);
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo Lang::get('reset_password.title'); ?> | Sécurité</title>
    
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="../../assets/css/update_pwd.css">
</head>
<body>

<div class="update-body">
    <div class="update-logo">
        <i class="bi bi-building-fill-check"></i>
        <span>RembourseMaroc</span>
    </div>

    <div class="update-card">
        <?php if ($isValid): ?>
            <div class="update-header">
                <!--<p class="update-tag">SECURITY</p>-->
                <h1 data-i18n="reset_password.title"><?php echo Lang::get('reset_password.title'); ?></h1>
                <p class="subtitle" data-i18n="reset_password.subtitle"><?php echo Lang::get('reset_password.subtitle'); ?></p>
            </div>

            <form action="../../actions/update_pwd.php" method="POST" class="update-form">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <input type="hidden" name="lang" value="<?php echo $currentLang; ?>">

                <div class="form-floating mb-3 position-relative">
                    <input type="password" name="password" class="form-control" id="newPass" placeholder="********" required>
                    <label for="newPass" data-i18n="reset_password.label_new_password"><?php echo Lang::get('reset_password.label_new_password'); ?></label>
                    <button type="button" class="toggle-pass" onclick="togglePass('newPass', this)">
                        <i class="bi bi-eye-slash"></i>
                    </button>
                </div>
                <div id="passFeedback" class="feedback-text text-muted" data-i18n="reset_password.password_hint">
                    <?php echo Lang::get('reset_password.password_hint'); ?>
                </div>

                <div class="form-floating mb-4 position-relative">
                    <input type="password" name="confirm_password" class="form-control" id="confirmPass" placeholder="********" required disabled>
                    <label for="confirmPass" data-i18n="reset_password.label_confirm_password"><?php echo Lang::get('reset_password.label_confirm_password'); ?></label>
                    <button type="button" class="toggle-pass" onclick="togglePass('confirmPass', this)">
                        <i class="bi bi-eye-slash"></i>
                    </button>
                </div>

                <button type="submit" class="btn btn-corp w-100" id="submitBtn" disabled data-i18n="reset_password.save_button">
                    <?php echo Lang::get('reset_password.save_button'); ?>
                </button>
            </form>
        <?php else: ?>
            <div class="update-header text-center">
                <div class="status-icon warning">
                    <i class="bi bi-hourglass-bottom"></i>
                </div>
                <h1 data-i18n="reset_password.error_expired"><?php echo Lang::get('reset_password.error_expired'); ?></h1>
                <p class="subtitle" data-i18n="reset_password.error_expired_message">
                    <?php echo Lang::get('reset_password.error_expired_message'); ?><br>
                    <?php echo Lang::get('reset_password.error_expired_detail'); ?>
                </p>
                <a href="forgot_pwd.php" class="btn btn-outline-dark w-100" data-i18n="reset_password.request_new_link">
                    <?php echo Lang::get('reset_password.request_new_link'); ?>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <p class="update-foot" data-i18n="reset_password.hero_sub">
        <?php echo Lang::get('reset_password.hero_sub'); ?>
    </p>
</div>


<script>
    
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

    // Validation dynamique du pwd
    const passInput = document.getElementById('newPass');
    const confirmInput = document.getElementById('confirmPass');
    const feedback = document.getElementById('passFeedback');
    const btn = document.getElementById('submitBtn');

    // Les règles de sécurité avec traductions
    const rules = [
        { regex: /.{8,}/, messageKey: "reset_password.password_rules.min_length" },
        { regex: /[A-Z]/, messageKey: "reset_password.password_rules.uppercase" },
        { regex: /[0-9]/, messageKey: "reset_password.password_rules.number" },
        { regex: /[\W_]/, messageKey: "reset_password.password_rules.special" }
    ];

    if (passInput) {
        passInput.addEventListener('input', function() {
            const val = this.value;
            let error = null;

            for (let rule of rules) {
                if (!rule.regex.test(val)) {
                    error = getTranslation(rule.messageKey);
                    break; 
                }
            }

            if (val.length === 0) {
                resetState();
            } 
            else if (error) {
                this.classList.add('input-error');     
                this.classList.remove('input-valid');
                feedback.textContent = error;
                feedback.className = "feedback-text text-error"; 

                if (confirmInput) confirmInput.disabled = true; 
                if (btn) btn.disabled = true;          
            } else {
                this.classList.remove('input-error');
                this.classList.add('input-valid');
                feedback.textContent = getTranslation('reset_password.password_secure');
                feedback.className = "feedback-text text-valid"; 
                
                if (confirmInput) confirmInput.disabled = false; 
                checkConfirm();
            }
        });
    }

    // Vérification de la correspondance (Confirm)
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
        feedback.textContent = getTranslation('reset_password.password_hint');
        feedback.className = "feedback-text text-muted";
        confirmInput.classList.remove('input-error', 'input-valid');
        confirmInput.value = "";
        confirmInput.disabled = true;
        btn.disabled = true;
    }
    
    // Mise à jour des traductions au chargement
    document.addEventListener('DOMContentLoaded', function() {
        const elements = document.querySelectorAll('[data-i18n]');
        elements.forEach(el => {
            const key = el.getAttribute('data-i18n');
            const translation = getTranslation(key);
            if (translation && translation !== key) {
                el.textContent = translation;
            }
        });
    });
</script>

</body>
</html>