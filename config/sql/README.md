# Scripts SQL - Gestion des Frais

Ce dossier contient tous les scripts SQL de migration et de configuration de la base de données.

## 🚀 Script principal : Création complète de la base de données

### `01_create_database_complete.sql`
**Script principal pour créer toute la base de données de zéro**

Ce script crée toutes les tables nécessaires avec leurs colonnes, contraintes, clés étrangères et index.

**Instructions d'utilisation :**
1. Créez la base de données : `CREATE DATABASE gestion_frais;`
2. Utilisez la base : `USE gestion_frais;`
3. Exécutez le script : `source config/sql/01_create_database_complete.sql;` (ou copiez-collez dans phpMyAdmin)

**Tables créées :**
- `users` - Utilisateurs (employés, managers, admins)
- `teams` - Équipes
- `categories` - Catégories de dépenses
- `demande` - Demandes de remboursement
- `expense_line` - Lignes de dépenses
- `avances` - Avances de fonds
- `reclamations` - Réclamations
- `reclamation_messages` - Messages des réclamations
- `notifications` - Notifications système
- `missions` - Missions de déplacement
- `mission_participants` - Participants aux missions
- `reclamation_views` - Vues des réclamations

**Données par défaut :**
- 5 catégories de dépenses (Transport, Hôtel, Restauration, Kilométrage, Autre)

---

## 📋 Liste des autres scripts (migrations)

### Scripts de migration des réclamations

1. **`SCRIPT_SQL_AJOUT_COLONNES_RECLAMATIONS.sql`**
   - Ajoute les colonnes `priorite`, `sujet`, `type_reclamation` à la table `reclamations`
   - Vérifie et ajoute `piece_jointe`, `is_internal` à la table `reclamation_messages`

2. **`FIX_STATUT_ENUM_VALUES.sql`**
   - Harmonise les valeurs ENUM de la colonne `statut` dans `reclamations`
   - Valeurs : "Ouvert", "En Cours", "Résolu", "Fermé"
   - Met à jour les enregistrements existants

3. **`create_reclamation_messages_table.sql`**
   - Crée la table `reclamation_messages` pour les messages des réclamations

4. **`fix_reclamation_tables_coherence.sql`**
   - Corrige la cohérence entre les tables `reclamations` et `reclamation_messages`

5. **`unify_reclamations_table.sql`**
   - Unifie la structure de la table `reclamations` (gère les variantes `id_reclam`/`id_reclamation`)

### Scripts de migration générale

6. **`add_priorite_to_reclamations.sql`**
   - Ajoute la colonne `priorite` à la table `reclamations`

7. **`add_date_fin_to_demande.sql`**
   - Ajoute la colonne `date_fin` à la table `demande`

8. **`create_notifications_table.sql`**
   - Crée la table `notifications` pour le système de notifications

## ⚠️ Ordre d'exécution recommandé

### Pour une nouvelle installation :
1. **`01_create_database_complete.sql`** - Crée toute la base de données

### Pour une base existante (migrations) :
1. `create_reclamation_messages_table.sql`
2. `create_notifications_table.sql`
3. `add_date_fin_to_demande.sql`
4. `SCRIPT_SQL_AJOUT_COLONNES_RECLAMATIONS.sql`
5. `FIX_STATUT_ENUM_VALUES.sql`
6. `fix_reclamation_tables_coherence.sql` (si nécessaire)
7. `unify_reclamations_table.sql` (si nécessaire)

## 📝 Notes

- Tous les scripts sont idempotents (peuvent être exécutés plusieurs fois sans erreur)
- Les scripts vérifient l'existence des colonnes/tables avant de les créer/modifier
- Sauvegardez votre base de données avant d'exécuter ces scripts
- Le script principal (`01_create_database_complete.sql`) supprime toutes les tables existantes avant de les recréer
