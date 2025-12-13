-- ============================================================================
-- Script de création de la table reclamation_messages
-- Table pour stocker les messages/réponses dans les tickets de réclamation
-- ============================================================================

-- Vérifier si la table existe déjà
SET @table_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'reclamation_messages'
);

-- Créer la table si elle n'existe pas
SET @sql = IF(@table_exists = 0,
    'CREATE TABLE reclamation_messages (
        id_message INT AUTO_INCREMENT PRIMARY KEY,
        reclamation_id INT NOT NULL,
        user_id INT NOT NULL,
        message TEXT NOT NULL,
        is_internal TINYINT(1) DEFAULT 0 COMMENT "0 = visible par l''employé, 1 = interne au support",
        piece_jointe VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_reclamation_id (reclamation_id),
        INDEX idx_user_id (user_id),
        INDEX idx_created_at (created_at),
        FOREIGN KEY (reclamation_id) REFERENCES reclamations(id_reclamation) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    'SELECT "Table reclamation_messages already exists" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'Table reclamation_messages créée avec succès!' as message;

