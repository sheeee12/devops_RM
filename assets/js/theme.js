// Theme Management Script
(function() {
    'use strict';
    
    // Récupérer le thème depuis le cookie ou la session
    function getTheme() {
        // Vérifier le cookie
        const cookies = document.cookie.split(';');
        for (let cookie of cookies) {
            const [name, value] = cookie.trim().split('=');
            if (name === 'app_theme') {
                return value;
            }
        }
        return 'light'; // Par défaut
    }
    
    // Appliquer le thème
    function applyTheme(theme) {
        if (theme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
            // FORCER LE MODE SOMBRE AVEC DES STYLES INLINE SI NÉCESSAIRE
            forceDarkMode();
        } else {
            document.documentElement.removeAttribute('data-theme');
            // Retirer les styles inline forcés
            removeForcedDarkMode();
        }
    }
    
    // Forcer le mode sombre avec des styles inline
    function forceDarkMode() {
        // Attendre que le DOM soit chargé
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', applyForcedStyles);
        } else {
            applyForcedStyles();
        }
    }
    
    function applyForcedStyles() {
        // Forcer le body
        const body = document.body;
        if (body) {
            body.style.setProperty('background-color', '#0f172a', 'important');
            body.style.setProperty('background', '#0f172a', 'important');
            body.style.setProperty('color', '#f1f5f9', 'important');
        }
        
        // Forcer le html
        const html = document.documentElement;
        if (html) {
            html.style.setProperty('background-color', '#0f172a', 'important');
            html.style.setProperty('background', '#0f172a', 'important');
        }
        
        // Forcer les main-container
        const mainContainers = document.querySelectorAll('.main-container');
        mainContainers.forEach(container => {
            container.style.setProperty('background-color', '#0f172a', 'important');
            container.style.setProperty('background', '#0f172a', 'important');
            container.style.setProperty('color', '#f1f5f9', 'important');
        });
        
        // Forcer les cartes widget
        const cardWidgets = document.querySelectorAll('.card-widget, .stat-card, .filter-card');
        cardWidgets.forEach(card => {
            if (!card.style.backgroundColor || card.style.backgroundColor.includes('white') || card.style.backgroundColor.includes('#fff')) {
                card.style.setProperty('background', 'linear-gradient(135deg, #1e293b 0%, #0f172a 100%)', 'important');
                card.style.setProperty('background-color', '#1e293b', 'important');
                card.style.setProperty('color', '#f1f5f9', 'important');
            }
        });
        
        // Forcer les textes dark
        const textDarkElements = document.querySelectorAll('.text-dark, h1.text-dark, h2.text-dark, h3.text-dark, h4.text-dark, h5.text-dark, h6.text-dark');
        textDarkElements.forEach(el => {
            el.style.setProperty('color', '#f1f5f9', 'important');
        });
        
        // Observer les changements du DOM pour appliquer les styles aux nouveaux éléments
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1) { // Element node
                        if (node.classList && node.classList.contains('main-container')) {
                            node.style.setProperty('background-color', '#0f172a', 'important');
                            node.style.setProperty('background', '#0f172a', 'important');
                            node.style.setProperty('color', '#f1f5f9', 'important');
                        }
                        if (node.classList && (node.classList.contains('card-widget') || node.classList.contains('stat-card') || node.classList.contains('filter-card'))) {
                            node.style.setProperty('background', 'linear-gradient(135deg, #1e293b 0%, #0f172a 100%)', 'important');
                            node.style.setProperty('background-color', '#1e293b', 'important');
                            node.style.setProperty('color', '#f1f5f9', 'important');
                        }
                    }
                });
            });
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
    
    function removeForcedDarkMode() {
        const body = document.body;
        if (body) {
            body.style.removeProperty('background-color');
            body.style.removeProperty('background');
            body.style.removeProperty('color');
        }
        
        const html = document.documentElement;
        if (html) {
            html.style.removeProperty('background-color');
            html.style.removeProperty('background');
        }
        
        const mainContainers = document.querySelectorAll('.main-container');
        mainContainers.forEach(container => {
            container.style.removeProperty('background-color');
            container.style.removeProperty('background');
            container.style.removeProperty('color');
        });
    }
    
    // Appliquer le thème au chargement
    const theme = getTheme();
    applyTheme(theme);
    
    // Exposer la fonction pour les autres scripts
    window.applyTheme = applyTheme;
    window.getTheme = getTheme;
})();

