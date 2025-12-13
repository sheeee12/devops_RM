// Gestion du thème pour l'espace admin
(function() {
    'use strict';
    
    // Récupérer le thème depuis localStorage
    function getTheme() {
        return localStorage.getItem('admin_theme') || 'light';
    }
    
    // Appliquer le thème
    function applyTheme(theme) {
        if (theme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        } else {
            document.documentElement.removeAttribute('data-theme');
        }
    }
    
    // Appliquer le thème au chargement
    const theme = getTheme();
    applyTheme(theme);
    
    // Exposer les fonctions globalement
    window.getAdminTheme = getTheme;
    window.applyAdminTheme = applyTheme;
    
    // Écouter les changements de thème depuis d'autres onglets
    window.addEventListener('storage', function(e) {
        if (e.key === 'admin_theme') {
            applyTheme(e.newValue || 'light');
        }
    });
})();

