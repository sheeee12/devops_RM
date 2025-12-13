-- ============================================================================
-- Script pour ajouter la colonne date_fin à la table demande si elle n'existe pas
-- ============================================================================

-- Vérifier si la colonne date_fin existe
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'demande' 
    AND COLUMN_NAME = 'date_fin'
);

-- Ajouter la colonne si elle n'existe pas
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE demande ADD COLUMN date_fin DATE NULL AFTER date_dep',
    'SELECT "Column date_fin already exists" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Si la colonne vient d'être ajoutée, initialiser les valeurs NULL avec date_dep
-- (pour les demandes où date_fin n'était pas définie, on considère que c'est le même jour)
UPDATE demande 
SET date_fin = date_dep 
WHERE date_fin IS NULL AND date_dep IS NOT NULL;

SELECT 'Colonne date_fin ajoutée/mise à jour avec succès!' as message;

