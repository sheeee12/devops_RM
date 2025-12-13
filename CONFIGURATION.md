#  Guide de Configuration - Fichiers Sensibles

Ce guide explique comment configurer les fichiers sensibles nécessaires au fonctionnement de l'application.

## IMPORTANT - Sécurité

Ces fichiers contiennent des informations sensibles (mots de passe, tokens API) et sont exclus du contrôle de version via `.gitignore`.

##  Fichiers à Configurer

### 1. Configuration de la Base de Données

**Fichier : `config/Database.php`**

1. Copiez le fichier d'exemple :
   ```bash
   cp config/Database.php.example config/Database.php
   ```

2. Modifiez `config/Database.php` avec vos identifiants :
   ```php
   $host = 'localhost';
   $dbname = 'gestion_frais';
   $username = 'votre_utilisateur';
   $password = 'votre_mot_de_passe';
   ```

### 2. Configuration Email (PHPMailer)

**Fichier : `config/mail_credentials.php`**

1. Copiez le fichier d'exemple :
   ```bash
   cp config/mail_credentials.php.example config/mail_credentials.php
   ```

2. Modifiez `config/mail_credentials.php` avec vos identifiants SMTP :
   ```php
   return [
       'smtp_host' => 'smtp.gmail.com',
       'smtp_port' => 587,
       'smtp_username' => 'votre-email@gmail.com',
       'smtp_password' => 'votre-mot-de-passe-app', // Mot de passe d'application Gmail
       'smtp_encryption' => 'tls',
       'from_email' => 'noreply@remboursemaroc.com',
       'from_name' => 'RembourseMaroc'
   ];
   ```

**Note pour Gmail :** Vous devez utiliser un [mot de passe d'application](https://support.google.com/accounts/answer/185833) et non votre mot de passe Gmail standard.

### 3. Configuration API (Token GitHub pour l'IA)

**Fichier : `config/api_credentials.php`**

1. Copiez le fichier d'exemple :
   ```bash
   cp config/api_credentials.php.example config/api_credentials.php
   ```

2. Obtenez un token GitHub API :
   - Allez sur [https://github.com/settings/tokens](https://github.com/settings/tokens)
   - Cliquez sur "Generate new token" > "Generate new token (classic)"
   - Donnez un nom au token (ex: "RembourseMaroc AI Audit")
   - Sélectionnez les permissions nécessaires
   - Cliquez sur "Generate token"
   - **COPIEZ le token** (il commence par `ghp_` et ne sera affiché qu'une seule fois)

3. Modifiez `config/api_credentials.php` :
   ```php
   define('GITHUB_API_TOKEN', 'ghp_votre_token_ici');
   ```

## Vérification

Après avoir configuré les fichiers, vérifiez que :

1. Les fichiers `.example` sont présents dans le dépôt Git
2. Les fichiers réels (`Database.php`, `api_credentials.php`, `mail_credentials.php`) sont **exclus** de Git (vérifiez avec `git status`)
3. Les fichiers réels fonctionnent correctement avec votre environnement

## Structure des Fichiers

```
config/
├── Database.php.example           Versionné (template)
├── Database.php                   NON versionné (à créer)
├── mail_credentials.php.example   Versionné (template)
├── mail_credentials.php           NON versionné (à créer)
├── api_credentials.php.example    Versionné (template)
└── api_credentials.php            NON versionné (à créer)
```



