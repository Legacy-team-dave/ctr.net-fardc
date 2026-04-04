-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Mar 26, 2026 at 11:52 AM
-- Server version: 8.4.3
-- PHP Version: 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ctr.net-fardc-1`
--

-- --------------------------------------------------------

--
-- Table structure for table `controles`
--

CREATE TABLE `controles` (
  `id` int NOT NULL COMMENT 'Identifiant unique du contrôle',
  `matricule` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Matricule du militaire',
  `type_controle` enum('Militaire','Bénéficiaire') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Type',
  `nom_beneficiaire` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nom du bénéficiaire',
  `new_beneficiaire` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lien_parente` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Lien de parenté',
  `date_controle` datetime NOT NULL COMMENT 'Date du contrôle',
  `mention` enum('Favorable','Défavorable','Présent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Résultat',
  `observations` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Observations',
  `cree_le` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date de création',
  `id_source` int DEFAULT NULL COMMENT 'ID original dans la source',
  `db_source` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'terrain' COMMENT 'Origine',
  `sync_status` enum('local','synced','conflict','archived') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'local' COMMENT 'État de synchronisation',
  `sync_date` datetime DEFAULT NULL COMMENT 'Date de dernière synchronisation',
  `sync_version` int NOT NULL DEFAULT '1' COMMENT 'Version pour conflits'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Contrôles effectués';

-- --------------------------------------------------------

--
-- Table structure for table `equipes`
--

CREATE TABLE `equipes` (
  `id` int NOT NULL,
  `matricule` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `noms` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `grade` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `unites` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_source` int DEFAULT NULL,
  `db_source` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'local',
  `sync_status` enum('local','synced') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'local',
  `sync_date` datetime DEFAULT NULL,
  `sync_version` int NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `litiges`
--

CREATE TABLE `litiges` (
  `id` int NOT NULL,
  `matricule` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `noms` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `grade` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `type_controle` enum('Militaire','Bénéficiaire') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nom_beneficiaire` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `lien_parente` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `garnison` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `province` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `date_controle` date DEFAULT NULL,
  `observations` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `cree_le` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `id_source` int DEFAULT NULL,
  `db_source` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'terrain',
  `sync_status` enum('local','synced','conflict','archived') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'local',
  `sync_date` datetime DEFAULT NULL,
  `sync_version` int NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `logs`
--

CREATE TABLE `logs` (
  `id_log` int NOT NULL,
  `id_utilisateur` int DEFAULT NULL,
  `action` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `table_concernee` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_enregistrement` int DEFAULT NULL,
  `details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `date_action` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `sync_session_id` int DEFAULT NULL COMMENT 'Session de synchronisation associée'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `logs_actions`
--

CREATE TABLE `logs_actions` (
  `id_log` int NOT NULL,
  `id_utilisateur` int DEFAULT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `table_concernee` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_enregistrement` int DEFAULT NULL,
  `details` text COLLATE utf8mb4_unicode_ci,
  `adresse_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_action` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `enrollements_vivants`
--

CREATE TABLE `enrollements_vivants` (
  `id` int NOT NULL COMMENT 'Identifiant unique de l’enrôlement',
  `matricule` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Matricule du militaire',
  `noms` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nom complet du militaire',
  `grade` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Grade du militaire',
  `unite` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Unité d’affectation',
  `garnison` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Garnison',
  `province` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Province',
  `categorie` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Catégorie militaire',
  `qr_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Données JSON issues du QR code',
  `photo_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Photo encodée du militaire',
  `empreinte_gauche_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Empreinte gauche encodée',
  `empreinte_droite_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Empreinte droite encodée',
  `observations` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Observations de l’enrôlement',
  `appareil_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Identifiant ou libellé de la tablette',
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date de création',
  `sync_status` enum('local','synced') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'local' COMMENT 'État de synchronisation'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Enrôlements des militaires vivants depuis tablette';

-- --------------------------------------------------------

--
-- Table structure for table `militaires`
--

CREATE TABLE `militaires` (
  `matricule` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Identifiant unique du militaire',
  `noms` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nom et prénom(s)',
  `grade` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Grade',
  `dependance` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Dépendance hiérarchique',
  `unite` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Unité d’affectation',
  `beneficiaire` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Bénéficiaire',
  `garnison` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Garnison',
  `province` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Province',
  `categorie` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Catégorie',
  `statut` tinyint NOT NULL DEFAULT '1' COMMENT '1 = actif, 0 = inactif',
  `db_source` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'terrain' COMMENT 'Base source',
  `sync_version` int NOT NULL DEFAULT '1' COMMENT 'Version pour conflits'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tous les militaires (actifs et inactifs)';

-- --------------------------------------------------------

--
-- Table structure for table `synchronisation`
--

CREATE TABLE `synchronisation` (
  `id` int NOT NULL,
  `controle_ids` text COLLATE utf8mb4_unicode_ci,
  `equipe_ids` text COLLATE utf8mb4_unicode_ci,
  `nb_controles` int NOT NULL DEFAULT '0',
  `nb_equipes` int NOT NULL DEFAULT '0',
  `statut` enum('succes','echec','en_attente') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en_attente',
  `details` longtext COLLATE utf8mb4_unicode_ci,
  `utilisateur_id` int DEFAULT NULL,
  `cree_le` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `utilisateurs`
--

CREATE TABLE `utilisateurs` (
  `id_utilisateur` int NOT NULL COMMENT 'Identifiant unique de l’utilisateur',
  `login` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nom d’utilisateur',
  `mot_de_passe` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Mot de passe (hashé)',
  `nom_complet` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nom et prénom',
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Adresse email',
  `avatar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nom du fichier avatar',
  `reset_token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Token de réinitialisation',
  `reset_expires` datetime DEFAULT NULL COMMENT 'Expiration du token',
  `profil` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Rôle (ADMIN_IG, GESTIONNAIRE, CONTROLEUR)',
  `actif` tinyint(1) DEFAULT '1' COMMENT '1 = actif, 0 = inactif',
  `dernier_acces` datetime DEFAULT NULL COMMENT 'Dernière connexion',
  `preferences` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `remember_token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remember_token_expires` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT 'Date de création',
  `last_sync` datetime DEFAULT NULL COMMENT 'Dernière synchronisation effectuée',
  `sync_count` int NOT NULL DEFAULT '0' COMMENT 'Nombre de synchronisations'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Utilisateurs de l’application';

--
-- Indexes for dumped tables
--

--
-- Indexes for table `controles`
--
ALTER TABLE `controles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_date` (`date_controle`),
  ADD KEY `fk_controles_matricule` (`matricule`),
  ADD KEY `idx_sync_status` (`sync_status`),
  ADD KEY `idx_source` (`db_source`,`id_source`);

--
-- Indexes for table `equipes`
--
ALTER TABLE `equipes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_equipes_sync_status` (`sync_status`),
  ADD KEY `idx_equipes_source` (`db_source`,`id_source`),
  ADD KEY `idx_equipes_matricule` (`matricule`);

--
-- Indexes for table `enrollements_vivants`
--
ALTER TABLE `enrollements_vivants`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_enrol_matricule` (`matricule`),
  ADD KEY `idx_enrol_date` (`cree_le`),
  ADD KEY `idx_enrol_sync_status` (`sync_status`);

--
-- Indexes for table `synchronisation`
--
ALTER TABLE `synchronisation`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sync_statut` (`statut`),
  ADD KEY `idx_sync_date` (`cree_le`);

--
-- Indexes for table `litiges`
--
ALTER TABLE `litiges`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sync_status` (`sync_status`),
  ADD KEY `idx_source` (`db_source`,`id_source`);

--
-- Indexes for table `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id_log`),
  ADD KEY `idx_utilisateur` (`id_utilisateur`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_date` (`date_action`),
  ADD KEY `idx_sync_session` (`sync_session_id`);

--
-- Indexes for table `logs_actions`
--
ALTER TABLE `logs_actions`
  ADD PRIMARY KEY (`id_log`),
  ADD KEY `idx_utilisateur` (`id_utilisateur`),
  ADD KEY `idx_action` (`action`);

--
-- Indexes for table `militaires`
--
ALTER TABLE `militaires`
  ADD PRIMARY KEY (`matricule`),
  ADD KEY `idx_statut` (`statut`),
  ADD KEY `idx_source` (`db_source`,`matricule`);

--
-- Indexes for table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  ADD PRIMARY KEY (`id_utilisateur`),
  ADD UNIQUE KEY `uk_login` (`login`),
  ADD UNIQUE KEY `uk_email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `controles`
--
ALTER TABLE `controles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT COMMENT 'Identifiant unique du contrôle';

--
-- AUTO_INCREMENT for table `equipes`
--
ALTER TABLE `equipes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `enrollements_vivants`
--
ALTER TABLE `enrollements_vivants`
  MODIFY `id` int NOT NULL AUTO_INCREMENT COMMENT 'Identifiant unique de l’enrôlement';

--
-- AUTO_INCREMENT for table `synchronisation`
--
ALTER TABLE `synchronisation`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `litiges`
--
ALTER TABLE `litiges`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `logs`
--
ALTER TABLE `logs`
  MODIFY `id_log` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `logs_actions`
--
ALTER TABLE `logs_actions`
  MODIFY `id_log` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  MODIFY `id_utilisateur` int NOT NULL AUTO_INCREMENT COMMENT 'Identifiant unique de l’utilisateur';

--
-- Constraints for dumped tables
--

--
-- Constraints for table `controles`
--
ALTER TABLE `controles`
  ADD CONSTRAINT `fk_controles_matricule` FOREIGN KEY (`matricule`) REFERENCES `militaires` (`matricule`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `logs`
--
ALTER TABLE `logs`
  ADD CONSTRAINT `logs_ibfk_1` FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateurs` (`id_utilisateur`) ON DELETE SET NULL;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
