-- Table pour les notifications (clarifications, validations, rejets)
-- Note: Les réclamations utilisent la table 'reclamations' existante
-- Cette table est créée automatiquement par le dashboard, mais vous pouvez l'exécuter manuellement si besoin

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('clarification', 'validation', 'rejet') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    related_id INT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La table 'reclamations' existe déjà avec la structure suivante:
-- id_reclam (INT), id_dem (INT), message (TEXT), statut (ENUM), created_at (TIMESTAMP)

