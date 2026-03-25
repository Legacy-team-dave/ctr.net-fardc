-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Mar 25, 2026 at 05:21 PM
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
-- Database: `ctr.net-fardc`
--

-- --------------------------------------------------------

--
-- Table structure for table `controles`
--

CREATE TABLE `controles` (
  `id` int NOT NULL COMMENT 'Identifiant unique du contrôle',
  `matricule` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Matricule du militaire concerné (doit exister dans militaires)',
  `type_controle` enum('Militaire','Bénéficiaire') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Type de personne contrôlée',
  `nom_beneficiaire` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nom du bénéficiaire (si type_controle = beneficiaire)',
  `new_beneficiaire` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lien_parente` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Lien de parenté avec le militaire (ex: conjoint, enfant)',
  `date_controle` datetime NOT NULL COMMENT 'Date du contrôle',
  `mention` enum('Favorable','Défavorable','Présent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Résultat du contrôle',
  `observations` text COLLATE utf8mb4_unicode_ci COMMENT 'Observations complémentaires',
  `cree_le` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date de création de l’enregistrement'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Contrôles effectués sur les militaires ou leurs bénéficiaires';

-- --------------------------------------------------------

--
-- Table structure for table `equipes`
--

CREATE TABLE `equipes` (
  `id` int NOT NULL,
  `noms` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `grade` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `litiges`
--

CREATE TABLE `litiges` (
  `id` int NOT NULL,
  `matricule` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `noms` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `grade` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `type_controle` enum('Militaire','Bénéficiaire') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nom_beneficiaire` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `lien_parente` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `garnison` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `province` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `date_controle` date DEFAULT NULL,
  `observations` text COLLATE utf8mb4_general_ci,
  `cree_le` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `logs`
--

CREATE TABLE `logs` (
  `id_log` int NOT NULL,
  `id_utilisateur` int DEFAULT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `table_concernee` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_enregistrement` int DEFAULT NULL,
  `details` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `date_action` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `militaires`
--

CREATE TABLE `militaires` (
  `matricule` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Identifiant unique du militaire',
  `noms` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nom et prénom(s) du militaire',
  `grade` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Grade (actuel ou dernier connu)',
  `dependance` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Dépendance hiérarchique ou administrative',
  `unite` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Unité d’affectation (ou dernière unité)',
  `beneficiaire` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Bénéficiaire (pour inactifs) - peut être NULL',
  `garnison` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Garnison de rattachement',
  `province` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Province de localisation',
  `categorie` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Catégorie (officier, sous-officier, etc.)',
  `statut` tinyint NOT NULL DEFAULT '1' COMMENT '1 = actif, 0 = inactif'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tous les militaires (actifs et inactifs)';

-- --------------------------------------------------------

--
-- Table structure for table `utilisateurs`
--

CREATE TABLE `utilisateurs` (
  `id_utilisateur` int NOT NULL COMMENT 'Identifiant unique de l’utilisateur',
  `login` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nom d’utilisateur',
  `mot_de_passe` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Mot de passe (hashé de préférence)',
  `nom_complet` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nom et prénom de l’utilisateur',
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Adresse email',
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nom du fichier avatar',
  `reset_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Token de réinitialisation',
  `reset_expires` datetime DEFAULT NULL COMMENT 'Expiration du token',
  `profil` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Rôle (ADMIN_IG, GESTIONNAIRE, CONTROLEUR)',
  `actif` tinyint(1) DEFAULT '1' COMMENT '1 = actif, 0 = inactif',
  `dernier_acces` datetime DEFAULT NULL COMMENT 'Dernière connexion',
  `preferences` text COLLATE utf8mb4_unicode_ci,
  `remember_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remember_token_expires` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT 'Date de création'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Utilisateurs de l’application';

--
-- Indexes for dumped tables
--

--
-- Indexes for table `controles`
--
ALTER TABLE `controles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_date` (`date_controle`) COMMENT 'Index pour les recherches par date',
  ADD KEY `fk_controles_matricule` (`matricule`);

--
-- Indexes for table `equipes`
--
ALTER TABLE `equipes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `litiges`
--
ALTER TABLE `litiges`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id_log`),
  ADD KEY `idx_utilisateur` (`id_utilisateur`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_date` (`date_action`);

--
-- Indexes for table `militaires`
--
ALTER TABLE `militaires`
  ADD PRIMARY KEY (`matricule`),
  ADD KEY `idx_statut` (`statut`);

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
