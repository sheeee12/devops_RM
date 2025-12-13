# Changelog

Toutes les modifications notables de ce projet seront documentées dans ce fichier.

## [Non versionné] - 2024

### Ajouté
- Système de gestion des réclamations (Helpdesk) pour l'espace admin
- Système de notifications en temps réel pour les employés
- Mode sombre/clair pour l'espace administration
- Page de profil administrateur avec gestion d'avatar, mot de passe et thème
- Export PDF amélioré avec style professionnel
- Filtrage et recherche avancés dans l'espace admin

### Modifié
- Restructuration complète du projet pour GitHub
- Organisation des scripts SQL dans `config/sql/`
- Amélioration du style des exports PDF
- Harmonisation des headers dans tous les espaces
- Correction des valeurs ENUM pour les statuts de réclamations

### Supprimé
- Fichiers de test et debug (`fix_pass.php`, `test_db.php`, `forgot2.php`)
- Fichier obsolète `update_avatar.php` (remplacé par des fichiers spécifiques)
- Dossier `src/` vide

### Sécurité
- Ajout de `.gitignore` pour protéger les fichiers sensibles
- Création de fichiers `.example` pour la configuration
- Protection des identifiants de base de données et SMTP

