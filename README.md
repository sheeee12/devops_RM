# Gestion des Frais - RembourseMaroc

Système de gestion des notes de frais professionnelles avec workflow d'approbation multi-niveaux.

## Description

Application web complète pour la gestion des dépenses professionnelles, permettant aux employés de soumettre des notes de frais, aux managers de les valider, et aux administrateurs de superviser l'ensemble du processus.

## Fonctionnalités

###  Espace Employé
- Création et gestion des notes de frais
- Suivi des demandes en temps réel
- Gestion des brouillons
- Système de réclamations/support
- Demandes d'avances
- Export PDF des notes de frais
- Notifications en temps réel

###  Espace Manager
- Validation/rejet des demandes de son équipe
- Visualisation du budget de l'équipe
- Historique et bilans
- Export PDF des bilans
- Gestion des avances

### Espace Administrateur
- Dashboard complet avec KPIs
- Gestion des paiements
- Gestion des utilisateurs et équipes
- Gestion des catégories de dépenses
- Système de réclamations (Helpdesk)
- Rapports et exports
- Gestion des budgets par équipe
- Mode sombre/clair

##  Technologies

- **Backend**: PHP 8.0+ **Natif (Vanilla PHP - Sans Framework)**
- **Architecture**: MVC **maison** (implémentation manuelle)
- **Base de données**: MySQL/MariaDB avec **PDO natif** (pas d'ORM)
- **Frontend**: Bootstrap 5, JavaScript (Vanilla)
- **PDF**: TCPDF
- **Email**: PHPMailer
- **Authentification 2FA**: Google Authenticator
- **Gestionnaire de dépendances**: Composer (uniquement pour vendor/)

**Note importante** : Ce projet n'utilise **aucun framework PHP** (Laravel, Symfony, CodeIgniter, etc.). Tout est développé en PHP natif avec une architecture MVC personnalisée.

##  Installation

### Prérequis
- PHP 8.0 ou supérieur
- MySQL/MariaDB 5.7+
- Composer
- Serveur web (Apache/Nginx) ou serveur de développement (Laragon, XAMPP, etc.)

### Étapes d'installation

1. **Cloner le repository**
```bash
git clone https://github.com/votre-username/gestion-frais.git
cd gestion-frais
```

2. **Installer les dépendances**
```bash
composer install
```

3. **Configurer la base de données**
   - Créer une base de données MySQL

4. **Configurer les fichiers sensibles**
   - ** IMPORTANT :** Suivez le guide [CONFIGURATION.md](./CONFIGURATION.md)
   - Copier les fichiers `.example` et remplir avec vos identifiants
   - Les fichiers sensibles sont exclus de Git pour la sécurité

5. **Configurer les permissions**
```bash
chmod -R 755 uploads/
```

6. **Accéder à l'application**
   - Ouvrir dans votre navigateur : `http://localhost/gestion-frais`

##  Structure du projet

```
gestion-frais/
├── actions/              # Actions PHP (traitements de formulaires)
├── assets/               # Ressources statiques (CSS, JS, images)
│   ├── css/
│   ├── js/
│   └── img/
├── classes/              # Classes PHP (modèles)
├── config/               # Configuration
│   ├── sql/             # Scripts SQL
│   ├── Database.php     # Configuration BDD
│   └── Lang.php         # Gestion multilingue
├── includes/             # Fichiers inclus (session, sécurité, etc.)
├── lang/                 # Fichiers de traduction (JSON)
├── uploads/              # Fichiers uploadés (avatars, preuves)
├── vendor/               # Dépendances Composer
├── views/                # Vues PHP
│   ├── admin/           # Pages administrateur
│   ├── auth/            # Pages d'authentification
│   ├── employe/         # Pages employé
│   └── manager/          # Pages manager
└── 
```

##  Sécurité

- Authentification par session sécurisée
- Authentification à deux facteurs (2FA) optionnelle
- Protection CSRF
- Validation et sanitization des entrées
- Hashage des mots de passe (bcrypt)
- Protection des fichiers sensibles via `.gitignore`
- **Les fichiers contenant des mots de passe, tokens API et identifiants sont exclus de Git**

** Voir [CONFIGURATION.md](./CONFIGURATION.md) pour configurer les fichiers sensibles en toute sécurité.**


##  Auteur

**TagOuaj**

---

**Note**: Ce projet est en développement actif. Certaines fonctionnalités peuvent être modifiées ou améliorées.

 
