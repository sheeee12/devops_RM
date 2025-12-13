-- ============================================================================
-- SCRIPT SQL COMPLET : Création de la base de données Gestion de Frais
-- RembourseMaroc - Version complète
-- ============================================================================
-- 
-- Ce script crée toute la base de données de zéro avec toutes les tables,
-- colonnes, contraintes, clés étrangères et index nécessaires.
--
-- INSTRUCTIONS:
-- 1. Créez d'abord la base de données : CREATE DATABASE gestion_frais;
-- 2. Utilisez la base : USE gestion_frais;
-- 3. Exécutez ce script complet
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- ============================================================================
-- 1. TABLE: users
-- Description: Table principale des utilisateurs (employés, managers, admins)
-- Note: La FK vers teams sera ajoutée après la création de la table teams
-- ============================================================================
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `user_id` INT(11) NOT NULL AUTO_INCREMENT,
  `nom` VARCHAR(255) NOT NULL,
  `prenom` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('employee', 'manager', 'admin') NOT NULL DEFAULT 'employee',
  `tel` VARCHAR(20) DEFAULT NULL,
  `avatar` VARCHAR(255) DEFAULT 'default.png',
  `manager_id` INT(11) DEFAULT NULL,
  `team_id` INT(11) DEFAULT NULL,
  `google_secret` VARCHAR(255) DEFAULT NULL COMMENT 'Secret pour authentification 2FA',
  `reset_token_hash` VARCHAR(64) DEFAULT NULL,
  `reset_expires_at` DATETIME DEFAULT NULL,
  `theme` ENUM('light', 'dark') DEFAULT 'light',
  `langue` ENUM('fr', 'en') DEFAULT 'fr',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_manager_id` (`manager_id`),
  KEY `idx_team_id` (`team_id`),
  KEY `idx_role` (`role`),
  CONSTRAINT `fk_users_manager` FOREIGN KEY (`manager_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2. TABLE: teams
-- Description: Table des équipes gérées par les managers
-- ============================================================================
DROP TABLE IF EXISTS `teams`;
CREATE TABLE `teams` (
  `team_id` INT(11) NOT NULL AUTO_INCREMENT,
  `nom_team` VARCHAR(255) NOT NULL,
  `manager_id` INT(11) NOT NULL,
  `budget_annuel` DECIMAL(10,2) DEFAULT 0.00,
  `budget_consomme` DECIMAL(10,2) DEFAULT 0.00,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`team_id`),
  UNIQUE KEY `manager_id` (`manager_id`),
  CONSTRAINT `fk_teams_manager` FOREIGN KEY (`manager_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ajouter la FK de users vers teams maintenant que teams existe
ALTER TABLE `users` 
ADD CONSTRAINT `fk_users_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`team_id`) ON DELETE SET NULL;

-- ============================================================================
-- 3. TABLE: categories
-- Description: Catégories de dépenses (Transport, Hôtel, Restauration, etc.)
-- ============================================================================
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id_categ` INT(11) NOT NULL AUTO_INCREMENT,
  `nom_categ` VARCHAR(255) NOT NULL,
  `plafond_max` DECIMAL(10,2) DEFAULT 0.00,
  `description` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_categ`),
  UNIQUE KEY `nom_categ` (`nom_categ`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 4. TABLE: avances
-- Description: Avances de fonds demandées par les employés
-- ============================================================================
DROP TABLE IF EXISTS `avances`;
CREATE TABLE `avances` (
  `id_avance` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `montant_demande` DECIMAL(10,2) NOT NULL,
  `motif` TEXT NOT NULL,
  `status` ENUM('En_Attente', 'Approuve', 'Rejete', 'Paye') DEFAULT 'En_Attente',
  `date_demande` DATE NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_avance`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_avances_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 5. TABLE: demande
-- Description: Demandes de remboursement de frais
-- ============================================================================
DROP TABLE IF EXISTS `demande`;
CREATE TABLE `demande` (
  `id_dem` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `titre_dem` VARCHAR(255) NOT NULL,
  `date_dep` DATE NOT NULL,
  `date_fin` DATE DEFAULT NULL,
  `montant_total` DECIMAL(10,2) DEFAULT 0.00,
  `montant_avance` DECIMAL(10,2) DEFAULT 0.00,
  `avance_id` INT(11) DEFAULT NULL,
  `status` ENUM('Brouillon', 'Attente_Manager', 'Attente_Admin', 'Valide', 'Rejete', 'Paye') DEFAULT 'Brouillon',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_dem`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_avance_id` (`avance_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_demande_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_demande_avance` FOREIGN KEY (`avance_id`) REFERENCES `avances` (`id_avance`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 6. TABLE: expense_line
-- Description: Lignes de dépenses détaillées pour chaque demande
-- ============================================================================
DROP TABLE IF EXISTS `expense_line`;
CREATE TABLE `expense_line` (
  `id_expense_line` INT(11) NOT NULL AUTO_INCREMENT,
  `id_dem` INT(11) NOT NULL,
  `id_categ` INT(11) NOT NULL,
  `date_depense` DATE NOT NULL,
  `montant` DECIMAL(10,2) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `justificatif_path` VARCHAR(255) DEFAULT NULL,
  `details_specifiques` JSON DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_expense_line`),
  KEY `idx_id_dem` (`id_dem`),
  KEY `idx_id_categ` (`id_categ`),
  KEY `idx_date_depense` (`date_depense`),
  CONSTRAINT `fk_expense_line_demande` FOREIGN KEY (`id_dem`) REFERENCES `demande` (`id_dem`) ON DELETE CASCADE,
  CONSTRAINT `fk_expense_line_categorie` FOREIGN KEY (`id_categ`) REFERENCES `categories` (`id_categ`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 7. TABLE: reclamations
-- Description: Réclamations des employés concernant leurs demandes
-- ============================================================================
DROP TABLE IF EXISTS `reclamations`;
CREATE TABLE `reclamations` (
  `id_reclam` INT(11) NOT NULL AUTO_INCREMENT,
  `id_dem` INT(11) DEFAULT NULL,
  `message` TEXT NOT NULL,
  `statut` ENUM('Ouvert', 'En Cours', 'Résolu', 'Fermé') DEFAULT 'Ouvert',
  `priorite` ENUM('Basse', 'Moyenne', 'Haute') DEFAULT 'Moyenne',
  `sujet` VARCHAR(255) DEFAULT NULL,
  `type_reclamation` ENUM('Retard_Paiement', 'Montant_Incorrect', 'Rejet_Injustifie', 'Probleme_Technique', 'Autre') DEFAULT 'Autre',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_reclam`),
  KEY `idx_id_dem` (`id_dem`),
  KEY `idx_statut` (`statut`),
  KEY `idx_priorite` (`priorite`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_reclamations_demande` FOREIGN KEY (`id_dem`) REFERENCES `demande` (`id_dem`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 8. TABLE: reclamation_messages
-- Description: Messages échangés dans le cadre d'une réclamation
-- ============================================================================
DROP TABLE IF EXISTS `reclamation_messages`;
CREATE TABLE `reclamation_messages` (
  `id_message` INT(11) NOT NULL AUTO_INCREMENT,
  `reclamation_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `message` TEXT NOT NULL,
  `is_internal` TINYINT(1) DEFAULT 0 COMMENT '0 = visible par l''employé, 1 = interne au support',
  `piece_jointe` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_message`),
  KEY `idx_reclamation_id` (`reclamation_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_reclamation_messages_reclamation` FOREIGN KEY (`reclamation_id`) REFERENCES `reclamations` (`id_reclam`) ON DELETE CASCADE,
  CONSTRAINT `fk_reclamation_messages_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 9. TABLE: notifications
-- Description: Notifications système (clarifications, validations, rejets, réclamations)
-- ============================================================================
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `type` ENUM('clarification', 'validation', 'rejet', 'reclamation_reply') NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `related_id` INT(11) DEFAULT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_user_read` (`user_id`, `is_read`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 10. TABLE: missions
-- Description: Missions de déplacement créées par les managers
-- ============================================================================
DROP TABLE IF EXISTS `missions`;
CREATE TABLE `missions` (
  `id_mission` INT(11) NOT NULL AUTO_INCREMENT,
  `titre` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `date_debut` DATE NOT NULL,
  `date_fin` DATE NOT NULL,
  `lieu` VARCHAR(255) DEFAULT NULL,
  `manager_id` INT(11) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_mission`),
  KEY `idx_manager_id` (`manager_id`),
  KEY `idx_date_debut` (`date_debut`),
  KEY `idx_date_fin` (`date_fin`),
  CONSTRAINT `fk_missions_manager` FOREIGN KEY (`manager_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 11. TABLE: mission_participants
-- Description: Participants aux missions (table de liaison)
-- ============================================================================
DROP TABLE IF EXISTS `mission_participants`;
CREATE TABLE `mission_participants` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `id_mission` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_participant` (`id_mission`, `user_id`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_mission_participants_mission` FOREIGN KEY (`id_mission`) REFERENCES `missions` (`id_mission`) ON DELETE CASCADE,
  CONSTRAINT `fk_mission_participants_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 12. TABLE: reclamation_views (optionnel)
-- Description: Suivi des réclamations vues par les managers
-- ============================================================================
DROP TABLE IF EXISTS `reclamation_views`;
CREATE TABLE `reclamation_views` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `reclamation_id` INT(11) NOT NULL,
  `manager_id` INT(11) NOT NULL,
  `viewed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_view` (`reclamation_id`, `manager_id`),
  KEY `idx_manager_id` (`manager_id`),
  CONSTRAINT `fk_reclamation_views_reclamation` FOREIGN KEY (`reclamation_id`) REFERENCES `reclamations` (`id_reclam`) ON DELETE CASCADE,
  CONSTRAINT `fk_reclamation_views_manager` FOREIGN KEY (`manager_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- INSERTION DE DONNÉES PAR DÉFAUT
-- ============================================================================

-- Catégories par défaut
INSERT INTO `categories` (`nom_categ`, `plafond_max`, `description`) VALUES
('Transport', 500.00, 'Frais de transport (train, avion, taxi, etc.)'),
('Hôtel', 200.00, 'Frais d\'hébergement'),
('Restauration', 50.00, 'Repas et restauration'),
('Kilométrage', 0.50, 'Frais de déplacement en voiture (par km)'),
('Autre', 0.00, 'Autres dépenses');

-- ============================================================================
-- FIN DU SCRIPT
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;

-- Message de confirmation
SELECT 'Base de données créée avec succès !' as message;
SELECT 'N''oubliez pas de configurer config/Database.php avec vos identifiants' as rappel;

