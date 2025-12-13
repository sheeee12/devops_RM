<?php
// Charger le thème depuis la session ou le cookie
$currentTheme = $_SESSION['theme'] ?? $_COOKIE['app_theme'] ?? 'light';
?>
<link rel="stylesheet" href="../../assets/css/dark-theme.css">
<script>
    // Appliquer le thème immédiatement pour éviter le flash
    (function() {
        const theme = '<?= $currentTheme ?>';
        if (theme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    })();
</script>
<script src="../../assets/js/theme.js"></script>

