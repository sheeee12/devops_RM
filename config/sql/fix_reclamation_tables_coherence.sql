-- ============================================================================
-- Script de cohérence entre reclamations et reclamation_messages
-- Harmonise les clés étrangères et les noms de colonnes
-- ============================================================================

-- Étape 1: Vérifier quelle colonne de clé primaire existe dans reclamations
SET @has_id_reclamation = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'reclamations' 
    AND COLUMN_NAME = 'id_reclamation'
);

SET @has_id_reclam = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'reclamations' 
    AND COLUMN_NAME = 'id_reclam'
);

-- Étape 2: Vérifier quelle colonne de clé primaire existe dans reclamation_messages
SET @has_id_msg = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'reclamation_messages' 
    AND COLUMN_NAME = 'id_msg'
);

SET @has_id_message = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'reclamation_messages' 
    AND COLUMN_NAME = 'id_message'
);

SET @has_id = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'reclamation_messages' 
    AND COLUMN_NAME = 'id'
    AND COLUMN_KEY = 'PRI'
);

-- Étape 3: Normaliser la colonne de clé primaire dans reclamation_messages vers id_msg
-- Renommer id_message en id_msg si nécessaire
SET @sql = IF(@has_id_msg = 0 AND @has_id_message > 0,
    'ALTER TABLE reclamation_messages CHANGE COLUMN id_message id_msg INT AUTO_INCREMENT',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Renommer id en id_msg si nécessaire
SET @sql = IF(@has_id_msg = 0 AND @has_id > 0,
    'ALTER TABLE reclamation_messages CHANGE COLUMN id id_msg INT AUTO_INCREMENT',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Créer id_msg si aucune colonne n'existe
SET @sql = IF(@has_id_msg = 0 AND @has_id_message = 0 AND @has_id = 0,
    'ALTER TABLE reclamation_messages ADD COLUMN id_msg INT AUTO_INCREMENT PRIMARY KEY FIRST',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Étape 4: Supprimer l'ancienne clé étrangère si elle existe
SET @fk_name = (
    SELECT CONSTRAINT_NAME 
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'reclamation_messages' 
    AND COLUMN_NAME = 'reclamation_id'
    AND REFERENCED_TABLE_NAME = 'reclamations'
    LIMIT 1
);

SET @sql = IF(@fk_name IS NOT NULL,
    CONCAT('ALTER TABLE reclamation_messages DROP FOREIGN KEY ', @fk_name),
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Étape 5: Créer la nouvelle clé étrangère selon la colonne qui existe dans reclamations
SET @sql = IF(@has_id_reclamation > 0,
    'ALTER TABLE reclamation_messages 
     ADD CONSTRAINT fk_reclamation_messages_reclamation 
     FOREIGN KEY (reclamation_id) REFERENCES reclamations(id_reclamation) ON DELETE CASCADE',
    IF(@has_id_reclam > 0,
        'ALTER TABLE reclamation_messages 
         ADD CONSTRAINT fk_reclamation_messages_reclamation 
         FOREIGN KEY (reclamation_id) REFERENCES reclamations(id_reclam) ON DELETE CASCADE',
        'SELECT 1'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Étape 6: Vérifier et ajouter piece_jointe si nécessaire
SET @has_piece_jointe = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'reclamation_messages' 
    AND COLUMN_NAME = 'piece_jointe'
);

SET @sql = IF(@has_piece_jointe = 0,
    'ALTER TABLE reclamation_messages ADD COLUMN piece_jointe VARCHAR(255) NULL AFTER message',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Étape 7: Vérifier et ajouter is_internal si nécessaire
SET @has_is_internal = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'reclamation_messages' 
    AND COLUMN_NAME = 'is_internal'
);

SET @sql = IF(@has_is_internal = 0,
    'ALTER TABLE reclamation_messages ADD COLUMN is_internal BOOLEAN DEFAULT 0 AFTER message',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Résumé final
SELECT 'Cohérence entre reclamations et reclamation_messages vérifiée et corrigée!' as message;
