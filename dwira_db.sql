-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : mar. 24 fév. 2026 à 01:22
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `dwira_db`
--

-- --------------------------------------------------------

--
-- Structure de la table `biens`
--

CREATE TABLE `biens` (
  `id` int(11) NOT NULL,
  `reference` varchar(20) NOT NULL,
  `titre` varchar(255) NOT NULL,
  `telephone_proprietaire` varchar(30) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `prix` decimal(12,2) NOT NULL,
  `type` varchar(50) NOT NULL,
  `statut` varchar(50) NOT NULL,
  `ville` varchar(100) NOT NULL,
  `zone` varchar(100) DEFAULT NULL,
  `chambres` int(11) DEFAULT 0,
  `surface` decimal(8,2) DEFAULT NULL,
  `vue_mer` tinyint(4) DEFAULT 0,
  `parking` tinyint(4) DEFAULT 0,
  `image` varchar(255) DEFAULT NULL,
  `disponible` tinyint(4) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `caracteristiques` text DEFAULT NULL,
  `details` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `biens`
--

INSERT INTO `biens` (`id`, `reference`, `titre`, `telephone_proprietaire`, `description`, `prix`, `type`, `statut`, `ville`, `zone`, `chambres`, `surface`, `vue_mer`, `parking`, `image`, `disponible`, `created_at`, `caracteristiques`, `details`) VALUES
(11, '107', 'Terrain dar allouche', '58659541', NULL, 210.00, 'Terrain', 'Vente', 'Dar Allouche', NULL, 0, NULL, 0, 0, NULL, 1, '2026-02-21 14:01:18', '[]', '{\"facade_m\":\"13.5\",\"surface_m2\":\"350\",\"type_terrain\":\"Agricole\",\"zone\":\"\",\"constructible\":true,\"coin_angle\":false,\"distance_plage_m\":\"200\",\"type_rue\":\"Route goudronn\\u00e9e\",\"type_papier\":\"Titre foncier collectif\"}'),
(12, '106', 'Terrain kélibia Forsa', '00000000', NULL, 130.00, 'Terrain', 'Vente', 'Kelibia', NULL, 0, NULL, 0, 0, NULL, 1, '2026-02-21 14:08:06', '[]', '{\"facade_m\":\"\",\"surface_m2\":\"372\",\"type_terrain\":\"Habitation\",\"zone\":\"\",\"constructible\":true,\"coin_angle\":false,\"distance_plage_m\":\"\",\"type_rue\":\"Rue r\\u00e9sidentielle\",\"type_papier\":\"\"}'),
(13, '105', 'Appartement Vue Mer – a karkouane', '29839509', NULL, 195.00, 'Appartement', 'Vente', 'Karkouane', NULL, 2, NULL, 0, 0, NULL, 1, '2026-02-21 14:11:43', '[\"Eau Sonede\",\"Electricit\\u00e9 STEG\"]', '{\"surface_m2\":\"60\",\"etage\":\"2\",\"configuration\":\"S+2\",\"nb_sdb\":\"1\",\"annee_construction\":\"2025\",\"chauffage_central\":false,\"climatisation\":true,\"balcon\":true,\"terrasse\":true,\"ascenseur\":false,\"vue_mer\":true,\"gaz_de_ville\":false,\"cuisine_equipee\":true,\"place_parking\":false,\"syndic\":false,\"meuble\":true,\"independant\":false,\"proche_plage\":true,\"distance_plage_m\":\"80\",\"type_rue\":\"Piste\",\"type_papier\":\"Titre foncier collectif\"}'),
(14, '104', 'Appartement de kélibia – S+3 / S+2', '29081333', NULL, 350.00, 'Immeuble', 'Vente', 'Kelibia', NULL, 5, NULL, 0, 0, NULL, 1, '2026-02-21 14:17:08', '[\"Eau Sonede\",\"Electricit\\u00e9 STEG\"]', '{\"surface_terrain_m2\":\"420\",\"surface_batie_m2\":\"350\",\"nb_niveaux\":\"3\",\"nb_appartements\":\"2\",\"nb_locaux_commerciaux\":\"0\",\"nb_garages\":\"1\",\"appartements\":[{\"index\":1,\"chambres\":3},{\"index\":2,\"chambres\":2}],\"ascenseur\":false,\"parking_sous_sol\":false,\"parking_exterieur\":false,\"syndic\":false,\"vue_mer\":false,\"proche_plage\":false,\"distance_plage_m\":\"\",\"type_rue\":\"Rue r\\u00e9sidentielle\",\"type_papier\":\"Titre foncier individuel\"}'),
(15, '102', 'APPARTEMENT RDC À KÉLIBIA', '00000000', NULL, 155.00, 'Appartement', 'Vente', 'Kelibia', NULL, 2, NULL, 0, 0, NULL, 1, '2026-02-21 14:29:34', '[\"Eau Sonede\",\"Electricit\\u00e9 STEG\"]', '{\"surface_m2\":\"80\",\"etage\":\"0\",\"configuration\":\"S+2\",\"nb_sdb\":\"1\",\"annee_construction\":\"\",\"chauffage_central\":false,\"climatisation\":true,\"balcon\":false,\"terrasse\":true,\"ascenseur\":false,\"vue_mer\":false,\"gaz_de_ville\":true,\"cuisine_equipee\":true,\"place_parking\":true,\"syndic\":false,\"meuble\":false,\"independant\":false,\"proche_plage\":true,\"distance_plage_m\":\"1000\",\"type_rue\":\"Route goudronn\\u00e9e\",\"type_papier\":\"Titre foncier collectif\"}'),
(16, '101', 'Terrain à kélibia Proche de Salle de Féte Amira', '00000000', NULL, 130.00, 'Terrain', 'Vente', 'Kelibia', NULL, 0, NULL, 0, 0, NULL, 1, '2026-02-21 14:40:28', '[\"Eau Sonede\",\"Electricit\\u00e9 STEG\"]', '{\"facade_m\":\"16\",\"surface_m2\":\"364\",\"type_terrain\":\"\",\"zone\":\"\",\"constructible\":true,\"coin_angle\":false,\"distance_plage_m\":\"\",\"type_rue\":\"Piste\",\"type_papier\":\"Titre foncier collectif\"}'),
(17, '100', 'Appartement S+2 à coté du centre ville', '00000000', NULL, 195.00, 'Appartement', 'Vente', 'Kelibia', NULL, 2, NULL, 0, 0, NULL, 1, '2026-02-21 19:05:59', '[\"proche de la plage\"]', '{\"surface_m2\":\"100\",\"etage\":\"1\",\"configuration\":\"S+2\",\"nb_sdb\":\"1\",\"annee_construction\":\"2025\",\"distance_plage_m\":\"1000\",\"type_rue\":\"Piste\",\"type_papier\":\"Contrat seulement\"}'),
(18, '099', 'Maison RDC individuel !', '00000000', NULL, 195.00, 'Villa', 'Vente', 'Kelibia', NULL, 2, NULL, 0, 0, NULL, 1, '2026-02-21 19:15:12', '[]', '{\"surface_terrain_m2\":\"182\",\"surface_couverte_m2\":\"182\",\"nb_etages\":\"0\",\"nb_sdb\":\"1\",\"nb_chambres\":\"3\",\"annee_construction\":\"2025\",\"piscine\":false,\"jardin\":false,\"terrasse\":true,\"garage\":true,\"studio_indep\":false,\"vue_mer\":false,\"chauffage_central\":false,\"climatisation\":true,\"gaz_de_ville\":false,\"meuble\":true,\"proche_plage\":false,\"distance_plage_m\":\"\",\"type_rue\":\"Piste\",\"type_papier\":\"Titre foncier collectif\"}'),
(19, '098', 'Appartement spacieux S+2 à kélibia', '00000000', NULL, 220.00, 'Appartement', 'Vente', 'Kelibia', NULL, 2, NULL, 0, 0, NULL, 1, '2026-02-21 19:21:36', '[\"proche de la plage\"]', '{\"surface_m2\":\"100\",\"etage\":\"2\",\"configuration\":\"S+2\",\"nb_sdb\":\"1\",\"annee_construction\":\"2025\",\"distance_plage_m\":\"1000\",\"type_rue\":\"Piste\",\"type_papier\":\"Contrat seulement\"}');

-- --------------------------------------------------------

--
-- Structure de la table `biens_caracteristiques`
--

CREATE TABLE `biens_caracteristiques` (
  `id` int(11) NOT NULL,
  `bien_id` int(11) NOT NULL,
  `caracteristique_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `caracteristiques`
--

CREATE TABLE `caracteristiques` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `type_bien` varchar(50) NOT NULL DEFAULT 'Global',
  `poids` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `types` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `caracteristiques`
--

INSERT INTO `caracteristiques` (`id`, `nom`, `type_bien`, `poids`, `types`) VALUES
(8, 'puits', 'Global', 1, '[\"Terrain\"]'),
(9, 'proche de la plage', 'Global', 1, '[]'),
(10, 'kbir', 'Global', 1, '[\"Local commercial\"]'),
(11, 'test', 'Global', 1, '[\"Appartement\"]');

-- --------------------------------------------------------

--
-- Structure de la table `clients_demandes`
--

CREATE TABLE `clients_demandes` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `telephone` varchar(30) NOT NULL,
  `budget_max` decimal(12,2) NOT NULL,
  `type_bien` varchar(50) NOT NULL,
  `statut` varchar(50) NOT NULL,
  `ville` varchar(100) NOT NULL,
  `zone` varchar(100) DEFAULT NULL,
  `chambres_min` int(11) DEFAULT 0,
  `caracteristiques` text DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `surface_min` decimal(8,2) DEFAULT NULL,
  `vue_mer` tinyint(4) DEFAULT 0,
  `parking` tinyint(4) DEFAULT 0,
  `active` tinyint(4) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `clients_demandes`
--

INSERT INTO `clients_demandes` (`id`, `nom`, `telephone`, `budget_max`, `type_bien`, `statut`, `ville`, `zone`, `chambres_min`, `caracteristiques`, `details`, `surface_min`, `vue_mer`, `parking`, `active`, `created_at`) VALUES
(27, 'Pierre Alain dHAUSSY', '0689463618', 200.00, 'Appartement', 'Vente', 'Kelibia', NULL, 2, '[\"proche de la plage\"]', '{\"surface_min_m2\":\"\",\"surface_max_m2\":\"\",\"etage_min\":\"\",\"etage_max\":\"\",\"config_souhaitee\":\"\",\"nb_sdb_min\":\"\",\"annee_min\":\"\",\"annee_max\":\"\",\"type_rue\":\"\",\"type_papier\":\"\",\"distance_plage_max_m\":\"\"}', NULL, 0, 0, 1, '2026-02-23 19:07:03'),
(28, 'chayma', '52080695', 250.00, 'Appartement', 'Vente', 'Kelibia', NULL, 2, '[\"proche de la plage\"]', '{\"surface_min_m2\":\"\",\"surface_max_m2\":\"\",\"etage_min\":\"\",\"etage_max\":\"\",\"config_souhaitee\":\"\",\"nb_sdb_min\":\"\",\"annee_min\":\"\",\"annee_max\":\"\",\"type_rue\":\"\",\"type_papier\":\"\",\"distance_plage_max_m\":\"\"}', NULL, 0, 0, 1, '2026-02-23 20:58:54'),
(29, 'hatem', '2255663322', 300.00, 'Appartement', 'Vente', 'Kelibia', NULL, 2, '[]', '{\"surface_min_m2\":\"\",\"surface_max_m2\":\"\",\"etage_min\":\"\",\"etage_max\":\"\",\"config_souhaitee\":\"\",\"nb_sdb_min\":\"\",\"annee_min\":\"\",\"annee_max\":\"\",\"type_rue\":\"\",\"type_papier\":\"\",\"distance_plage_max_m\":\"\"}', NULL, 0, 0, 1, '2026-02-23 22:14:36');

-- --------------------------------------------------------

--
-- Structure de la table `logs`
--

CREATE TABLE `logs` (
  `id` int(11) NOT NULL,
  `action` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `matches`
--

CREATE TABLE `matches` (
  `id` int(11) NOT NULL,
  `bien_id` int(11) NOT NULL,
  `demande_id` int(11) NOT NULL,
  `score` int(11) DEFAULT 0,
  `vu` tinyint(4) DEFAULT 0,
  `statut` varchar(50) DEFAULT 'nouveau',
  `interet_client` tinyint(3) UNSIGNED DEFAULT NULL,
  `decision_client` varchar(50) DEFAULT NULL,
  `commentaire_client` text DEFAULT NULL,
  `prochain_suivi_at` datetime DEFAULT NULL,
  `resultat_final` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `matches`
--

INSERT INTO `matches` (`id`, `bien_id`, `demande_id`, `score`, `vu`, `statut`, `interet_client`, `decision_client`, `commentaire_client`, `prochain_suivi_at`, `resultat_final`, `created_at`) VALUES
(62, 15, 27, 75, 0, 'offre', 8, 'a_reflechir', 'nessanew', '2026-02-23 20:22:00', 'gagne', '2026-02-23 19:07:03'),
(63, 17, 27, 91, 0, 'visite_planifiee', 8, 'interesse', '', '2026-02-28 10:00:00', NULL, '2026-02-23 19:07:03'),
(64, 19, 27, 73, 0, 'visite_planifiee', 10, NULL, '', '2026-02-26 20:16:00', NULL, '2026-02-23 19:07:03'),
(65, 13, 28, 68, 1, 'nouveau', NULL, NULL, NULL, NULL, NULL, '2026-02-23 20:58:54'),
(66, 15, 28, 75, 0, 'nouveau', NULL, NULL, NULL, NULL, NULL, '2026-02-23 20:58:54'),
(67, 17, 28, 100, 0, 'nouveau', NULL, NULL, NULL, NULL, NULL, '2026-02-23 20:58:54'),
(68, 19, 28, 96, 1, 'visite_planifiee', 10, 'interesse', 'yessana f lawre9', '2026-02-26 21:59:00', NULL, '2026-02-23 20:58:54'),
(69, 13, 29, 68, 0, 'nouveau', NULL, NULL, NULL, NULL, NULL, '2026-02-23 22:14:36'),
(70, 15, 29, 75, 0, 'nouveau', NULL, NULL, NULL, NULL, NULL, '2026-02-23 22:14:36'),
(71, 17, 29, 75, 0, 'nouveau', NULL, NULL, NULL, NULL, NULL, '2026-02-23 22:14:36'),
(72, 19, 29, 75, 0, 'nouveau', NULL, NULL, NULL, NULL, NULL, '2026-02-23 22:14:36');

-- --------------------------------------------------------

--
-- Structure de la table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `type` varchar(50) DEFAULT NULL,
  `message` varchar(255) DEFAULT NULL,
  `related_id` int(11) DEFAULT NULL,
  `vu` tinyint(4) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(20) DEFAULT 'admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `created_at`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '2026-02-07 12:33:41');

-- --------------------------------------------------------

--
-- Structure de la table `visites`
--

CREATE TABLE `visites` (
  `id` int(11) NOT NULL,
  `bien_id` int(11) NOT NULL,
  `demande_id` int(11) DEFAULT NULL,
  `client_nom` varchar(255) DEFAULT NULL,
  `client_tel` varchar(50) DEFAULT NULL,
  `match_id` int(11) DEFAULT NULL,
  `date_visite` datetime NOT NULL,
  `lieu` varchar(255) DEFAULT NULL,
  `statut` enum('Prévue','Réalisée','Annulée','No show') DEFAULT 'Prévue',
  `note` text DEFAULT NULL,
  `source` enum('match','manuel') NOT NULL DEFAULT 'manuel',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `visites`
--

INSERT INTO `visites` (`id`, `bien_id`, `demande_id`, `client_nom`, `client_tel`, `match_id`, `date_visite`, `lieu`, `statut`, `note`, `source`, `created_at`) VALUES
(6, 19, NULL, 'ghaith', '29879227', NULL, '2026-02-24 22:02:00', NULL, 'Prévue', NULL, 'manuel', '2026-02-23 19:00:14'),
(7, 17, 27, 'Pierre Alain dHAUSSY', '0689463618', 63, '2026-02-24 10:59:00', 'agence', 'Prévue', NULL, 'match', '2026-02-23 19:10:04'),
(9, 15, 27, 'Pierre Alain dHAUSSY', '0689463618', 62, '2026-02-27 22:29:00', 'bien', 'Prévue', NULL, 'match', '2026-02-23 19:29:12'),
(10, 19, 28, 'chayma', '52080695', 68, '2026-03-01 02:04:00', NULL, 'Prévue', NULL, 'match', '2026-02-23 21:00:44'),
(11, 17, NULL, 'hatem', '12365896', NULL, '2026-02-24 01:21:00', NULL, 'Prévue', NULL, 'manuel', '2026-02-23 21:18:05'),
(12, 11, NULL, 'hatemmostah', '22998056', NULL, '2026-02-24 01:35:00', 'f dar', 'Prévue', NULL, 'manuel', '2026-02-23 21:32:59'),
(13, 17, 28, NULL, NULL, NULL, '2026-02-23 02:11:00', NULL, 'Prévue', NULL, 'manuel', '2026-02-23 22:08:03'),
(14, 17, 29, NULL, NULL, NULL, '2026-02-27 01:17:00', NULL, 'Prévue', NULL, 'manuel', '2026-02-23 22:15:15');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `biens`
--
ALTER TABLE `biens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference` (`reference`);

--
-- Index pour la table `biens_caracteristiques`
--
ALTER TABLE `biens_caracteristiques`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bien_id` (`bien_id`),
  ADD KEY `caracteristique_id` (`caracteristique_id`);

--
-- Index pour la table `caracteristiques`
--
ALTER TABLE `caracteristiques`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `clients_demandes`
--
ALTER TABLE `clients_demandes`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `matches`
--
ALTER TABLE `matches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_match` (`bien_id`,`demande_id`),
  ADD KEY `fk_match_demande` (`demande_id`);

--
-- Index pour la table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Index pour la table `visites`
--
ALTER TABLE `visites`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bien_id` (`bien_id`),
  ADD KEY `demande_id` (`demande_id`),
  ADD KEY `fk_visite_match` (`match_id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `biens`
--
ALTER TABLE `biens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT pour la table `biens_caracteristiques`
--
ALTER TABLE `biens_caracteristiques`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `caracteristiques`
--
ALTER TABLE `caracteristiques`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT pour la table `clients_demandes`
--
ALTER TABLE `clients_demandes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT pour la table `logs`
--
ALTER TABLE `logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `matches`
--
ALTER TABLE `matches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- AUTO_INCREMENT pour la table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `visites`
--
ALTER TABLE `visites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `biens_caracteristiques`
--
ALTER TABLE `biens_caracteristiques`
  ADD CONSTRAINT `biens_caracteristiques_ibfk_1` FOREIGN KEY (`bien_id`) REFERENCES `biens` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `biens_caracteristiques_ibfk_2` FOREIGN KEY (`caracteristique_id`) REFERENCES `caracteristiques` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `matches`
--
ALTER TABLE `matches`
  ADD CONSTRAINT `fk_match_bien` FOREIGN KEY (`bien_id`) REFERENCES `biens` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_match_demande` FOREIGN KEY (`demande_id`) REFERENCES `clients_demandes` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `visites`
--
ALTER TABLE `visites`
  ADD CONSTRAINT `fk_visite_match` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `visites_ibfk_1` FOREIGN KEY (`bien_id`) REFERENCES `biens` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `visites_ibfk_2` FOREIGN KEY (`demande_id`) REFERENCES `clients_demandes` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
