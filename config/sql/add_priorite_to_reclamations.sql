-- ============================================================================
-- Script pour ajouter la colonne priorite à la table reclamations si elle n'existe pas
-- ============================================================================

-- Vérifier si la colonne priorite existe
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'reclamations' 
    AND COLUMN_NAME = 'priorite'
);

-- Ajouter la colonne si elle n'existe pas
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE reclamations ADD COLUMN priorite ENUM(\'Basse\', \'Moyenne\', \'Haute\') DEFAULT \'Moyenne\' AFTER statut',
    'SELECT "Column priorite already exists" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Mettre à jour les valeurs NULL avec 'Moyenne' par défaut
UPDATE reclamations 
SET priorite = 'Moyenne' 
WHERE priorite IS NULL;

SELECT 'Colonne priorite ajoutée/mise à jour avec succès!' as message;

