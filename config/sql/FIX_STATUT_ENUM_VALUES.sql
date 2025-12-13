-- ============================================================================
-- Script SQL : Corriger les valeurs ENUM de la colonne statut
-- Pour qu'elles correspondent exactement à l'interface : Ouvert, En_Cours, Resolu, Ferme
-- ============================================================================

-- 1. Vérifier quelle colonne existe (statut ou status)
SET @has_statut = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'reclamations' 
    AND COLUMN_NAME = 'statut'
);

SET @has_status = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'reclamations' 
    AND COLUMN_NAME = 'status'
);

-- 2. Modifier la colonne statut pour avoir les bonnes valeurs ENUM (exactement comme dans l'interface)
-- Valeurs ENUM dans BDD : Ouvert, En Cours, Résolu, Fermé (avec accents et espaces)
-- Si la colonne s'appelle 'statut'
SET @sql_statut = IF(@has_statut > 0,
    'ALTER TABLE reclamations MODIFY COLUMN statut ENUM(\'Ouvert\', \'En Cours\', \'Résolu\', \'Fermé\') DEFAULT \'Ouvert\'',
    'SELECT "Colonne statut n''existe pas" as message'
);

PREPARE stmt FROM @sql_statut;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Si la colonne s'appelle 'status'
SET @sql_status = IF(@has_status > 0 AND @has_statut = 0,
    'ALTER TABLE reclamations MODIFY COLUMN status ENUM(\'Ouvert\', \'En Cours\', \'Résolu\', \'Fermé\') DEFAULT \'Ouvert\'',
    'SELECT "Colonne status n''existe pas ou statut existe déjà" as message'
);

PREPARE stmt FROM @sql_status;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Mettre à jour les valeurs existantes qui ne correspondent pas
-- Si la colonne s'appelle 'statut'
UPDATE reclamations 
SET statut = 'Ouvert' 
WHERE statut IN ('Ouverte', 'ouvert', 'OUVERT', 'Ouvertes') 
AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'reclamations' 
            AND COLUMN_NAME = 'statut');

UPDATE reclamations 
SET statut = 'En Cours' 
WHERE statut IN ('En_Cours', 'en cours', 'EN_COURS', 'EnCours', 'en_cours', 'En Cours') 
AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'reclamations' 
            AND COLUMN_NAME = 'statut');

UPDATE reclamations 
SET statut = 'Résolu' 
WHERE statut IN ('Resolu', 'resolu', 'RESOLU', 'Résolu', 'traite', 'Traite', 'TRAITE', 'Traité', 'traité') 
AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'reclamations' 
            AND COLUMN_NAME = 'statut');

UPDATE reclamations 
SET statut = 'Fermé' 
WHERE statut IN ('Ferme', 'ferme', 'FERME', 'Fermé', 'fermé', 'FERMÉ', 'Fermee', 'fermee') 
AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'reclamations' 
            AND COLUMN_NAME = 'statut');

-- Si la colonne s'appelle 'status'
UPDATE reclamations 
SET status = 'Ouvert' 
WHERE status IN ('Ouverte', 'ouvert', 'OUVERT', 'Ouvertes') 
AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'reclamations' 
            AND COLUMN_NAME = 'status');

UPDATE reclamations 
SET status = 'En Cours' 
WHERE status IN ('En_Cours', 'en cours', 'EN_COURS', 'EnCours', 'en_cours', 'En Cours') 
AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'reclamations' 
            AND COLUMN_NAME = 'status');

UPDATE reclamations 
SET status = 'Résolu' 
WHERE status IN ('Resolu', 'resolu', 'RESOLU', 'Résolu', 'traite', 'Traite', 'TRAITE', 'Traité', 'traité') 
AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'reclamations' 
            AND COLUMN_NAME = 'status');

UPDATE reclamations 
SET status = 'Fermé' 
WHERE status IN ('Ferme', 'ferme', 'FERME', 'Fermé', 'fermé', 'FERMÉ', 'Fermee', 'fermee') 
AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'reclamations' 
            AND COLUMN_NAME = 'status');

-- Message de confirmation
SELECT 'Script terminé ! Colonne statut/status mise à jour avec les valeurs : Ouvert, En Cours, Résolu, Fermé' as resultat;

