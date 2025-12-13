-- ============================================================================
-- SCRIPT SQL : Ajout des colonnes manquantes aux tables reclamations
-- À exécuter directement dans votre base de données (phpMyAdmin, MySQL, etc.)
-- ============================================================================

-- 1. Ajouter la colonne PRIORITE à la table reclamations
-- (Vérifie si elle existe déjà avant de l'ajouter)
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'reclamations' 
    AND COLUMN_NAME = 'priorite'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE reclamations ADD COLUMN priorite ENUM(\'Basse\', \'Moyenne\', \'Haute\') DEFAULT \'Moyenne\' AFTER statut',
    'SELECT "Colonne priorite existe déjà" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Mettre à jour toutes les réclamations existantes avec la priorité par défaut 'Moyenne'
UPDATE reclamations 
SET priorite = 'Moyenne' 
WHERE priorite IS NULL;

-- 3. (Optionnel) Ajouter la colonne SUJET si vous voulez l'utiliser
SET @col_exists_sujet = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'reclamations' 
    AND COLUMN_NAME = 'sujet'
);

SET @sql_sujet = IF(@col_exists_sujet = 0,
    'ALTER TABLE reclamations ADD COLUMN sujet VARCHAR(255) NULL AFTER id_dem',
    'SELECT "Colonne sujet existe déjà" as message'
);

PREPARE stmt FROM @sql_sujet;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. (Optionnel) Ajouter la colonne TYPE_RECLAMATION si vous voulez l'utiliser
SET @col_exists_type = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'reclamations' 
    AND COLUMN_NAME = 'type_reclamation'
);

SET @sql_type = IF(@col_exists_type = 0,
    'ALTER TABLE reclamations ADD COLUMN type_reclamation ENUM(\'Retard_Paiement\', \'Montant_Incorrect\', \'Rejet_Injustifie\', \'Probleme_Technique\', \'Autre\') DEFAULT \'Autre\' AFTER id_dem',
    'SELECT "Colonne type_reclamation existe déjà" as message'
);

PREPARE stmt FROM @sql_type;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5. Vérifier que la table reclamation_messages a bien toutes les colonnes nécessaires
-- Vérifier piece_jointe
SET @col_exists_pj = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'reclamation_messages' 
    AND COLUMN_NAME = 'piece_jointe'
);

SET @sql_pj = IF(@col_exists_pj = 0,
    'ALTER TABLE reclamation_messages ADD COLUMN piece_jointe VARCHAR(255) NULL AFTER message',
    'SELECT "Colonne piece_jointe existe déjà" as message'
);

PREPARE stmt FROM @sql_pj;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Vérifier is_internal
SET @col_exists_internal = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'reclamation_messages' 
    AND COLUMN_NAME = 'is_internal'
);

SET @sql_internal = IF(@col_exists_internal = 0,
    'ALTER TABLE reclamation_messages ADD COLUMN is_internal BOOLEAN DEFAULT 0 AFTER message',
    'SELECT "Colonne is_internal existe déjà" as message'
);

PREPARE stmt FROM @sql_internal;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Message de confirmation
SELECT 'Script terminé ! Colonnes ajoutées avec succès.' as resultat;

