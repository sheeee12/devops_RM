-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Apr 25, 2026 at 08:25 PM
-- Server version: 8.4.3
-- PHP Version: 8.3.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `gestion_frais`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id_log` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `action` varchar(50) DEFAULT NULL,
  `details` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id_log`, `user_id`, `action`, `details`, `created_at`) VALUES
(1, 1, 'Rejet Admin', '{\"motif\": \"\", \"id_dem\": 12}', '2025-12-03 19:39:21'),
(2, 1, 'Paiement Confirmé', '{\"msg\": \"Confirmé manuellement par Admin\", \"id_dem\": 13}', '2025-12-11 14:38:31');

-- --------------------------------------------------------

--
-- Table structure for table `avances`
--

CREATE TABLE `avances` (
  `id_avance` int NOT NULL,
  `user_id` int NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `date_besoin` date NOT NULL,
  `motif` varchar(255) NOT NULL,
  `status` enum('En_Attente','Valide','Paye','Rejete') DEFAULT 'En_Attente',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `avances`
--

INSERT INTO `avances` (`id_avance`, `user_id`, `montant`, `date_besoin`, `motif`, `status`, `created_at`) VALUES
(1, 6, 2000.00, '2025-12-26', 'reserver avion', 'Paye', '2025-11-30 16:02:28'),
(2, 6, 200.00, '2025-12-08', 'jj', 'En_Attente', '2025-12-01 11:34:19');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id_categ` int NOT NULL,
  `nom_categ` varchar(50) NOT NULL,
  `plafond_max` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id_categ`, `nom_categ`, `plafond_max`) VALUES
(1, 'Carburant', 150.00),
(2, 'Restauration', 120.00),
(3, 'Transport', 200.00),
(4, 'Hôtel', 800.00),
(5, 'test', 1000.00);

-- --------------------------------------------------------

--
-- Table structure for table `demande`
--

CREATE TABLE `demande` (
  `id_dem` int NOT NULL,
  `user_id` int NOT NULL,
  `titre_dem` varchar(150) DEFAULT NULL,
  `date_dep` date DEFAULT NULL,
  `date_fin` date DEFAULT NULL,
  `montant_total` decimal(10,2) DEFAULT '0.00',
  `status` enum('Brouillon','Attente_Manager','Attente_Admin','Valide','Rejete','Paye') DEFAULT 'Brouillon',
  `motif_rejet` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `avance_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `demande`
--

INSERT INTO `demande` (`id_dem`, `user_id`, `titre_dem`, `date_dep`, `date_fin`, `montant_total`, `status`, `motif_rejet`, `created_at`, `avance_id`) VALUES
(1, 6, 'Installation Serveurs Tanger', '2025-12-03', NULL, 1500.00, 'Paye', NULL, '2025-08-26 23:00:00', NULL),
(2, 6, 'Réunion Client Rabat', '2025-09-27', NULL, 450.00, 'Paye', NULL, '2025-09-26 23:00:00', NULL),
(3, 6, 'Séminaire Tech Casa', '2025-10-27', NULL, 2000.00, 'Valide', NULL, '2025-10-26 23:00:00', NULL),
(4, 6, 'Achat Matériel Non autorisé', '2025-11-27', NULL, 5000.00, 'Rejete', 'Hors procédure frais déplacement', '2025-11-26 23:00:00', NULL),
(5, 6, 'Mission Urgente Fès', '2025-11-27', NULL, 800.00, 'Paye', NULL, '2025-11-26 23:00:00', NULL),
(7, 6, 'deplacement', '2025-11-27', NULL, 300.00, 'Paye', NULL, '2025-11-27 17:19:04', NULL),
(8, 6, 'deplacement', '2025-11-12', NULL, 2000.00, 'Paye', NULL, '2025-11-27 17:20:17', NULL),
(9, 6, 'deplacement', '2025-11-22', NULL, 222.00, 'Paye', NULL, '2025-11-27 17:28:13', NULL),
(12, 6, 'test', '2025-11-05', '2025-11-13', 1.00, 'Rejete', '', '2025-11-27 19:56:13', NULL),
(13, 6, 'avion', '2025-11-30', '2025-12-05', 2500.00, 'Paye', NULL, '2025-11-30 16:06:06', 1),
(14, 6, 'deplacement', '2025-12-04', '2025-12-17', 700.00, 'Attente_Manager', NULL, '2025-12-04 14:40:07', NULL),
(15, 6, 'deplacementjj', '2025-12-06', '2025-12-06', 99.00, 'Attente_Manager', NULL, '2025-12-06 15:15:33', NULL),
(16, 6, 'br', '2025-12-06', '2025-12-06', 88.00, 'Brouillon', NULL, '2025-12-06 15:15:59', NULL),
(17, 6, 'Réunion Client - OCP Jorf Lasfar', '2025-10-15', '2025-10-15', 300.00, 'Paye', NULL, '2025-12-11 14:21:08', NULL),
(18, 6, ' Maintenance Serveurs - Site Tanger Med', '2025-11-02', '2025-11-03', 700.00, 'Paye', NULL, '2025-12-11 14:22:53', NULL),
(19, 10, ' Conférence Devoxx Morocco - Marrakech', '2025-11-12', '2025-11-14', 1020.00, 'Paye', NULL, '2025-12-11 14:26:47', NULL),
(20, 10, 'Formation Certification Agile', '2025-09-20', '2025-09-20', 920.00, 'Paye', NULL, '2025-12-11 14:28:28', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `expense_line`
--

CREATE TABLE `expense_line` (
  `id` int NOT NULL,
  `id_categ` int NOT NULL,
  `date_depense` date DEFAULT NULL,
  `montant` decimal(10,2) DEFAULT NULL,
  `justificatif_path` varchar(255) DEFAULT NULL,
  `details_specifiques` json DEFAULT NULL,
  `id_dem` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `expense_line`
--

INSERT INTO `expense_line` (`id`, `id_categ`, `date_depense`, `montant`, `justificatif_path`, `details_specifiques`, `id_dem`) VALUES
(1, 3, '2025-08-27', 500.00, 'train.pdf', NULL, 1),
(2, 4, '2025-08-27', 1000.00, 'hotel.pdf', NULL, 1),
(3, 1, '2025-09-27', 150.00, 'essence.pdf', NULL, 2),
(4, 2, '2025-09-27', 300.00, 'dejeuner.pdf', NULL, 2),
(5, 4, '2025-10-27', 1600.00, 'hotel_luxe.pdf', NULL, 3),
(6, 2, '2025-10-27', 400.00, 'diner.pdf', NULL, 3),
(7, 3, '2025-11-27', 5000.00, 'facture.pdf', NULL, 4),
(8, 1, '2025-11-27', 800.00, 'gasoil.pdf', NULL, 5),
(9, 4, '2025-11-27', 300.00, 'proof_7_0_1764263944.png', '{\"description\": \"se voir avec client \"}', 7),
(10, 1, '2025-11-27', 2000.00, 'proof_8_0_1764264017.png', '{\"description\": \"\"}', 8),
(11, 4, '2025-11-27', 222.00, 'proof_9_0_1764264493.png', '{\"description\": \"pas plus \"}', 9),
(15, 4, '2025-11-27', 1.00, 'proof_12_0_1764273373.pdf', '{\"description\": \"\"}', 12),
(16, 3, '2025-11-30', 2500.00, 'proof_13_0_1764518766.png', '{\"description\": \"\"}', 13),
(17, 4, '2025-12-04', 600.00, 'proof_14_0_1764859207.pdf', '{\"description\": \"\"}', 14),
(18, 3, '2025-12-04', 100.00, 'proof_14_1_1764859207.pdf', '{\"description\": \"\"}', 14),
(19, 1, '2025-12-06', 99.00, 'proof_15_0_1765034133.png', '{\"description\": \"\"}', 15),
(20, 4, '2025-12-06', 88.00, 'proof_16_0_1765034159.png', '{\"description\": \"\"}', 16),
(21, 3, '2025-10-15', 180.00, 'proof_17_0_1765462868.jpeg', '{\"description\": \" Billet Train Aller-Retour Casa-El Jadida\"}', 17),
(22, 2, '2025-10-15', 120.00, 'proof_17_1_1765462868.jpeg', '{\"description\": \"Déjeuner avec l\'équipe projet\"}', 17),
(23, 3, '2025-11-02', 200.00, 'proof_18_0_1765462973.jpeg', '{\"description\": \"\"}', 18),
(24, 4, '2025-11-02', 500.00, 'proof_18_1_1765462973.jpeg', '{\"description\": \"Nuitée Hôtel Ibis Tanger \"}', 18),
(25, 4, '2025-11-13', 800.00, 'proof_19_0_1765463207.jpeg', '{\"description\": \"Forfait 2 nuits Hôtel Atlas (Séminaire)\"}', 19),
(26, 2, '2025-11-13', 100.00, 'proof_19_1_1765463207.jpeg', '{\"description\": \"\"}', 19),
(27, 2, '2025-12-13', 120.00, 'proof_19_2_1765463207.jpeg', '{\"description\": \"\"}', 19),
(28, 3, '2025-11-22', 200.00, 'proof_20_0_1765463308.jpeg', '{\"description\": \"\"}', 20),
(29, 2, '2025-11-22', 120.00, 'proof_20_1_1765463308.jpeg', '{\"description\": \"\"}', 20),
(30, 4, '2025-11-22', 600.00, 'proof_20_2_1765463308.jpeg', '{\"description\": \"\"}', 20);

-- --------------------------------------------------------

--
-- Table structure for table `reclamations`
--

CREATE TABLE `reclamations` (
  `id_reclamation` int NOT NULL,
  `user_id` int NOT NULL,
  `demande_id` int DEFAULT NULL,
  `type_reclamation` enum('Retard_Paiement','Montant_Incorrect','Rejet_Injustifie','Probleme_Technique','Autre') DEFAULT 'Autre',
  `sujet` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `piece_jointe` varchar(255) DEFAULT NULL,
  `status` enum('Ouvert','En_Cours','Resolu','Ferme') DEFAULT 'Ouvert',
  `priorite` enum('Basse','Moyenne','Haute') DEFAULT 'Moyenne',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `reclamations`
--

INSERT INTO `reclamations` (`id_reclamation`, `user_id`, `demande_id`, `type_reclamation`, `sujet`, `message`, `piece_jointe`, `status`, `priorite`, `created_at`) VALUES
(1, 6, 13, 'Retard_Paiement', 'Automatisation du déploiement d’une stratégie de déception', 'oooo', NULL, 'En_Cours', 'Moyenne', '2025-12-05 15:00:16'),
(2, 6, 12, 'Probleme_Technique', 'hhh', 'nnn', NULL, 'En_Cours', 'Haute', '2025-12-05 15:07:38'),
(3, 10, NULL, 'Probleme_Technique', 'Bug', 'Il y a un problème dans la soumission  des demandes', NULL, 'Ferme', 'Moyenne', '2025-12-11 14:29:47');

-- --------------------------------------------------------

--
-- Table structure for table `reclamation_messages`
--

CREATE TABLE `reclamation_messages` (
  `id_msg` int NOT NULL,
  `reclamation_id` int NOT NULL,
  `user_id` int NOT NULL,
  `message` text NOT NULL,
  `piece_jointe` varchar(255) DEFAULT NULL,
  `is_internal` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `reclamation_messages`
--

INSERT INTO `reclamation_messages` (`id_msg`, `reclamation_id`, `user_id`, `message`, `piece_jointe`, `is_internal`, `created_at`) VALUES
(1, 1, 1, 'd accord', NULL, 0, '2025-12-05 15:24:16'),
(2, 1, 1, 'note admin', NULL, 1, '2025-12-06 10:34:33'),
(3, 2, 1, '<em>[Système] Ticket mis à jour : En_Cours / Priorité Haute</em>', NULL, 1, '2025-12-09 14:40:20'),
(4, 3, 1, 'daccord', NULL, 0, '2025-12-11 14:42:30'),
(5, 3, 1, '<em>[Système] Ticket mis à jour : Ferme / Priorité Moyenne</em>', NULL, 1, '2025-12-11 14:42:36'),
(6, 3, 1, '<em>[Système] Ticket mis à jour : Resolu / Priorité Moyenne</em>', NULL, 1, '2025-12-11 14:42:45'),
(7, 3, 1, '<em>[Système] Ticket mis à jour : En_Cours / Priorité Moyenne</em>', NULL, 1, '2025-12-11 14:42:52'),
(8, 3, 1, 'message valable que pour les admins', NULL, 1, '2025-12-11 14:43:20'),
(9, 3, 10, 'merci', NULL, 0, '2025-12-11 14:45:03'),
(10, 3, 7, 'note admin Omar', NULL, 1, '2025-12-11 14:45:45'),
(11, 3, 7, '<em>[Système] Ticket mis à jour : Ferme / Priorité Moyenne</em>', NULL, 1, '2025-12-11 14:45:54');

-- --------------------------------------------------------

--
-- Table structure for table `teams`
--

CREATE TABLE `teams` (
  `team_id` int NOT NULL,
  `nom_team` varchar(100) NOT NULL,
  `budget_annuel` decimal(10,2) DEFAULT '0.00',
  `budget_consomme` decimal(10,2) DEFAULT '0.00',
  `manager_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `teams`
--

INSERT INTO `teams` (`team_id`, `nom_team`, `budget_annuel`, `budget_consomme`, `manager_id`) VALUES
(1, 'Team IT', 150000.00, 14822.00, 2),
(2, 'Team Finance', 120000.00, 71940.00, 3),
(3, 'Team RH', 80000.00, 70000.00, 4),
(4, 'Team Marketing', 100000.00, 9000.00, 5),
(5, 'Team test', 90000.00, 0.00, 17);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int NOT NULL,
  `nom` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('employee','manager','admin') NOT NULL,
  `avatar` varchar(255) DEFAULT 'default.png',
  `team_id` int DEFAULT NULL,
  `manager_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `nom`, `email`, `password`, `role`, `avatar`, `team_id`, `manager_id`) VALUES
(1, 'Admin Principal', 'admin@frais.ma', 'password', 'admin', 'admin.png', NULL, NULL),
(2, 'Manager IT', 'manager.it@frais.ma', 'password', 'manager', 'manager.png', 1, NULL),
(3, 'Manager Finance', 'manager.fin@frais.ma', 'password', 'manager', 'manager.png', 2, NULL),
(4, 'Manager RH', 'manager.rh@frais.ma', 'password', 'manager', 'manager.png', 3, NULL),
(5, 'Manager Marketing', 'manager.mkt@frais.ma', 'password', 'manager', 'manager.png', 4, NULL),
(6, 'Yasmine', 'yasmine@frais.ma', 'password', 'employee', 'emp.png', 1, 2),
(7, 'Omar', 'omar@frais.ma', 'password', 'admin', 'emp.png', 1, 2),
(8, 'Sofia', 'sofia@frais.ma', 'password', 'employee', 'emp.png', 1, 2),
(9, 'Karim', 'karim@frais.ma', 'password', 'employee', 'emp.png', 2, 3),
(10, 'Imane', 'imane@frais.ma', 'password', 'employee', 'emp.png', 2, 3),
(11, 'Mehdi', 'mehdi@frais.ma', 'password', 'employee', 'emp.png', 2, 3),
(12, 'Salma', 'salma@frais.ma', 'password', 'employee', 'emp.png', 3, 4),
(13, 'Hicham', 'hicham@frais.ma', 'password', 'employee', 'emp.png', 3, 4),
(14, 'Nadia', 'nadia@frais.ma', 'password', 'employee', 'emp.png', 3, 4),
(15, 'Samir', 'samir@frais.ma', 'password', 'employee', 'emp.png', 4, 5),
(16, 'Aya', 'aya@frais.ma', 'password', 'employee', 'emp.png', 4, 5),
(17, 'Tariq', 'tariq@frais.ma', 'password', 'employee', 'emp.png', 4, 5);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id_log`);

--
-- Indexes for table `avances`
--
ALTER TABLE `avances`
  ADD PRIMARY KEY (`id_avance`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id_categ`);

--
-- Indexes for table `demande`
--
ALTER TABLE `demande`
  ADD PRIMARY KEY (`id_dem`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `avance_id` (`avance_id`);

--
-- Indexes for table `expense_line`
--
ALTER TABLE `expense_line`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_categ` (`id_categ`),
  ADD KEY `fk_demande` (`id_dem`);

--
-- Indexes for table `reclamations`
--
ALTER TABLE `reclamations`
  ADD PRIMARY KEY (`id_reclamation`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `demande_id` (`demande_id`);

--
-- Indexes for table `reclamation_messages`
--
ALTER TABLE `reclamation_messages`
  ADD PRIMARY KEY (`id_msg`),
  ADD KEY `reclamation_id` (`reclamation_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `teams`
--
ALTER TABLE `teams`
  ADD PRIMARY KEY (`team_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `team_id` (`team_id`),
  ADD KEY `manager_id` (`manager_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id_log` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `avances`
--
ALTER TABLE `avances`
  MODIFY `id_avance` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id_categ` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `demande`
--
ALTER TABLE `demande`
  MODIFY `id_dem` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `expense_line`
--
ALTER TABLE `expense_line`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `reclamations`
--
ALTER TABLE `reclamations`
  MODIFY `id_reclamation` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `reclamation_messages`
--
ALTER TABLE `reclamation_messages`
  MODIFY `id_msg` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `teams`
--
ALTER TABLE `teams`
  MODIFY `team_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `avances`
--
ALTER TABLE `avances`
  ADD CONSTRAINT `avances_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `demande`
--
ALTER TABLE `demande`
  ADD CONSTRAINT `demande_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `demande_ibfk_2` FOREIGN KEY (`avance_id`) REFERENCES `avances` (`id_avance`);

--
-- Constraints for table `expense_line`
--
ALTER TABLE `expense_line`
  ADD CONSTRAINT `expense_line_ibfk_1` FOREIGN KEY (`id_categ`) REFERENCES `categories` (`id_categ`),
  ADD CONSTRAINT `fk_demande` FOREIGN KEY (`id_dem`) REFERENCES `demande` (`id_dem`) ON DELETE CASCADE;

--
-- Constraints for table `reclamations`
--
ALTER TABLE `reclamations`
  ADD CONSTRAINT `reclamations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reclamations_ibfk_2` FOREIGN KEY (`demande_id`) REFERENCES `demande` (`id_dem`) ON DELETE SET NULL;

--
-- Constraints for table `reclamation_messages`
--
ALTER TABLE `reclamation_messages`
  ADD CONSTRAINT `reclamation_messages_ibfk_1` FOREIGN KEY (`reclamation_id`) REFERENCES `reclamations` (`id_reclamation`) ON DELETE CASCADE,
  ADD CONSTRAINT `reclamation_messages_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`team_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`manager_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
