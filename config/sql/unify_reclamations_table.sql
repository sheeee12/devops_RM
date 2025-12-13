-- ============================================================================
-- Script d'unification de la table reclamations
-- Compatible avec les deux structures (binôme et manager)
-- ============================================================================

-- Étape 1: Vérifier et ajouter les colonnes manquantes de la structure binôme
-- (si elles n'existent pas déjà)

-- Ajouter id_reclamation si elle n'existe pas (renommer id_reclam si nécessaire)
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'reclamations' 
    AND COLUMN_NAME = 'id_reclamation'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE reclamations CHANGE COLUMN id_reclam id_reclamation INT AUTO_INCREMENT PRIMARY KEY',
    'SELECT "Column id_reclamation already exists" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ajouter user_id si elle n'existe pas
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'reclamations' 
    AND COLUMN_NAME = 'user_id'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE reclamations ADD COLUMN user_id INT NULL AFTER id_reclamation',
    'SELECT "Column user_id already exists" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ajouter demande_id si elle n'existe pas (renommer id_dem si nécessaire)
SET @col_exists_demande_id = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'reclamations' 
    AND COLUMN_NAME = 'demande_id'
);

SET @col_exists_id_dem = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'reclamations' 
    AND COLUMN_NAME = 'id_dem'
);

SET @sql = IF(@col_exists_demande_id = 0 AND @col_exists_id_dem > 0,
    'ALTER TABLE reclamations CHANGE COLUMN id_dem demande_id INT NULL',
    IF(@col_exists_demande_id = 0,
        'ALTER TABLE reclamations ADD COLUMN demande_id INT NULL AFTER user_id',
        'SELECT "Column demande_id already exists" as message'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ajouter sujet si elle n'existe pas
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'reclamations' 
    AND COLUMN_NAME = 'sujet'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE reclamations ADD COLUMN sujet VARCHAR(255) NULL AFTER demande_id',
    'SELECT "Column sujet already exists" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ajouter status si elle n'existe pas (renommer statut si nécessaire)
SET @col_exists_status = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'reclamations' 
    AND COLUMN_NAME = 'status'
);

SET @col_exists_statut = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'reclamations' 
    AND COLUMN_NAME = 'statut'
);

SET @sql = IF(@col_exists_status = 0 AND @col_exists_statut > 0,
    'ALTER TABLE reclamations CHANGE COLUMN statut status ENUM(\'Ouvert\', \'En_Cours\', \'Resolu\', \'Ferme\') DEFAULT \'Ouvert\'',
    IF(@col_exists_status = 0,
        'ALTER TABLE reclamations ADD COLUMN status ENUM(\'Ouvert\', \'En_Cours\', \'Resolu\', \'Ferme\') DEFAULT \'Ouvert\' AFTER message',
        'SELECT "Column status already exists" as message'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ajouter type_reclamation si elle n'existe pas
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'reclamations' 
    AND COLUMN_NAME = 'type_reclamation'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE reclamations ADD COLUMN type_reclamation ENUM(\'Retard_Paiement\', \'Montant_Incorrect\', \'Rejet_Injustifie\', \'Probleme_Technique\', \'Autre\') DEFAULT \'Autre\' AFTER demande_id',
    'SELECT "Column type_reclamation already exists" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ajouter priorite si elle n'existe pas
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'reclamations' 
    AND COLUMN_NAME = 'priorite'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE reclamations ADD COLUMN priorite ENUM(\'Basse\', \'Moyenne\', \'Haute\') DEFAULT \'Moyenne\' AFTER status',
    'SELECT "Column priorite already exists" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ajouter piece_jointe si elle n'existe pas
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'reclamations' 
    AND COLUMN_NAME = 'piece_jointe'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE reclamations ADD COLUMN piece_jointe VARCHAR(255) NULL AFTER message',
    'SELECT "Column piece_jointe already exists" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Étape 2: Mettre à jour les données existantes pour remplir les colonnes manquantes
-- Si on a des données avec id_dem mais pas demande_id
UPDATE reclamations 
SET demande_id = id_dem 
WHERE demande_id IS NULL AND id_dem IS NOT NULL;

-- Si on a des données avec statut mais pas status
UPDATE reclamations 
SET status = statut 
WHERE status IS NULL AND statut IS NOT NULL;

-- Si on a des données sans user_id, le récupérer depuis demande
UPDATE reclamations r
LEFT JOIN demande d ON r.demande_id = d.id_dem
SET r.user_id = d.user_id
WHERE r.user_id IS NULL AND d.user_id IS NOT NULL;

-- Si sujet est NULL, utiliser un sujet par défaut basé sur le message
UPDATE reclamations 
SET sujet = LEFT(message, 100)
WHERE sujet IS NULL OR sujet = '';

-- Étape 3: Ajouter les contraintes de clé étrangère si elles n'existent pas
-- (Vérifier d'abord si elles existent)
SET @fk_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'reclamations' 
    AND COLUMN_NAME = 'user_id' 
    AND REFERENCED_TABLE_NAME = 'users'
);

SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE reclamations ADD CONSTRAINT fk_reclamations_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE',
    'SELECT "Foreign key fk_reclamations_user already exists" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'reclamations' 
    AND COLUMN_NAME = 'demande_id' 
    AND REFERENCED_TABLE_NAME = 'demande'
);

SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE reclamations ADD CONSTRAINT fk_reclamations_demande FOREIGN KEY (demande_id) REFERENCES demande(id_dem) ON DELETE SET NULL',
    'SELECT "Foreign key fk_reclamations_demande already exists" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Étape 4: Structure finale unifiée
-- La table reclamations devrait maintenant avoir toutes les colonnes suivantes:
-- - id_reclamation (INT AUTO_INCREMENT PRIMARY KEY)
-- - user_id (INT, FK vers users)
-- - demande_id (INT, FK vers demande, peut être NULL)
-- - type_reclamation (ENUM)
-- - priorite (ENUM)
-- - sujet (VARCHAR(255))
-- - message (TEXT)
-- - piece_jointe (VARCHAR(255), NULL)
-- - status (ENUM)
-- - created_at (TIMESTAMP)

SELECT 'Unification de la table reclamations terminée!' as message;

