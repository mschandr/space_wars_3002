/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19-11.8.3-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: space_wars
-- ------------------------------------------------------
-- Server version	11.8.3-MariaDB-0+deb13u1 from Debian

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `colonies`
--

DROP TABLE IF EXISTS `colonies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `colonies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` uuid NOT NULL,
  `player_id` bigint(20) unsigned NOT NULL,
  `poi_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `population` int(11) NOT NULL DEFAULT 100,
  `population_growth_rate` decimal(5,2) NOT NULL DEFAULT 0.05,
  `max_population` int(11) NOT NULL DEFAULT 10000,
  `food_production` int(11) NOT NULL DEFAULT 0,
  `food_storage` int(11) NOT NULL DEFAULT 1000,
  `mineral_production` int(11) NOT NULL DEFAULT 0,
  `mineral_storage` int(11) NOT NULL DEFAULT 500,
  `quantium_storage` int(11) NOT NULL DEFAULT 0,
  `credits_per_cycle` int(11) NOT NULL DEFAULT 0,
  `development_level` int(11) NOT NULL DEFAULT 1,
  `defense_rating` int(11) NOT NULL DEFAULT 0,
  `garrison_strength` int(11) NOT NULL DEFAULT 0,
  `habitability_rating` decimal(3,2) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'establishing',
  `established_at` timestamp NOT NULL,
  `last_growth_at` timestamp NULL DEFAULT NULL,
  `last_attacked_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `colonies_player_id_poi_id_unique` (`player_id`,`poi_id`),
  UNIQUE KEY `colonies_uuid_unique` (`uuid`),
  KEY `colonies_player_id_index` (`player_id`),
  KEY `colonies_poi_id_index` (`poi_id`),
  CONSTRAINT `colonies_player_id_foreign` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE,
  CONSTRAINT `colonies_poi_id_foreign` FOREIGN KEY (`poi_id`) REFERENCES `points_of_interest` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `colony_buildings`
--

DROP TABLE IF EXISTS `colony_buildings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `colony_buildings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` uuid NOT NULL,
  `colony_id` bigint(20) unsigned NOT NULL,
  `building_type` varchar(255) NOT NULL,
  `required_stage` int(11) NOT NULL DEFAULT 1,
  `level` int(11) NOT NULL DEFAULT 1,
  `status` varchar(255) NOT NULL DEFAULT 'constructing',
  `construction_progress` int(11) NOT NULL DEFAULT 0,
  `construction_cost_credits` int(11) NOT NULL DEFAULT 0,
  `construction_cost_minerals` int(11) NOT NULL DEFAULT 0,
  `construction_cost_population` int(11) NOT NULL DEFAULT 0,
  `construction_started_at` timestamp NULL DEFAULT NULL,
  `construction_completed_at` timestamp NULL DEFAULT NULL,
  `last_cycle_at` timestamp NULL DEFAULT NULL,
  `effects` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`effects`)),
  `credits_per_cycle` int(11) NOT NULL DEFAULT 0,
  `quantium_per_cycle` int(11) NOT NULL DEFAULT 0,
  `food_per_cycle` int(11) NOT NULL DEFAULT 0,
  `minerals_per_cycle` int(11) NOT NULL DEFAULT 0,
  `credits_generated_per_cycle` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `colony_buildings_uuid_unique` (`uuid`),
  KEY `colony_buildings_colony_id_index` (`colony_id`),
  KEY `colony_buildings_colony_id_building_type_index` (`colony_id`,`building_type`),
  CONSTRAINT `colony_buildings_colony_id_foreign` FOREIGN KEY (`colony_id`) REFERENCES `colonies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `colony_missions`
--

DROP TABLE IF EXISTS `colony_missions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `colony_missions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` uuid NOT NULL,
  `colony_id` bigint(20) unsigned NOT NULL,
  `player_ship_id` bigint(20) unsigned NOT NULL,
  `destination_poi_id` bigint(20) unsigned NOT NULL,
  `mission_type` varchar(255) NOT NULL,
  `colonists_aboard` int(11) NOT NULL DEFAULT 0,
  `cargo_capacity_used` int(11) NOT NULL DEFAULT 0,
  `cargo_manifest` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`cargo_manifest`)),
  `status` varchar(255) NOT NULL DEFAULT 'preparing',
  `turns_remaining` int(11) DEFAULT NULL,
  `launched_at` timestamp NULL DEFAULT NULL,
  `arrival_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `colony_missions_uuid_unique` (`uuid`),
  KEY `colony_missions_destination_poi_id_foreign` (`destination_poi_id`),
  KEY `colony_missions_colony_id_index` (`colony_id`),
  KEY `colony_missions_player_ship_id_index` (`player_ship_id`),
  KEY `colony_missions_status_index` (`status`),
  CONSTRAINT `colony_missions_colony_id_foreign` FOREIGN KEY (`colony_id`) REFERENCES `colonies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `colony_missions_destination_poi_id_foreign` FOREIGN KEY (`destination_poi_id`) REFERENCES `points_of_interest` (`id`),
  CONSTRAINT `colony_missions_player_ship_id_foreign` FOREIGN KEY (`player_ship_id`) REFERENCES `player_ships` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `colony_ship_production`
--

DROP TABLE IF EXISTS `colony_ship_production`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `colony_ship_production` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` uuid NOT NULL,
  `colony_id` bigint(20) unsigned NOT NULL,
  `ship_id` bigint(20) unsigned NOT NULL,
  `player_id` bigint(20) unsigned NOT NULL,
  `production_progress` int(11) NOT NULL DEFAULT 0,
  `production_cost_credits` int(11) NOT NULL,
  `production_cost_minerals` int(11) NOT NULL,
  `production_time_cycles` int(11) NOT NULL DEFAULT 10,
  `status` varchar(255) NOT NULL DEFAULT 'queued',
  `queue_position` int(11) NOT NULL DEFAULT 1,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `colony_ship_production_uuid_unique` (`uuid`),
  KEY `colony_ship_production_ship_id_foreign` (`ship_id`),
  KEY `colony_ship_production_colony_id_index` (`colony_id`),
  KEY `colony_ship_production_player_id_index` (`player_id`),
  KEY `colony_ship_production_colony_id_status_index` (`colony_id`,`status`),
  CONSTRAINT `colony_ship_production_colony_id_foreign` FOREIGN KEY (`colony_id`) REFERENCES `colonies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `colony_ship_production_player_id_foreign` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE,
  CONSTRAINT `colony_ship_production_ship_id_foreign` FOREIGN KEY (`ship_id`) REFERENCES `ships` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `combat_participants`
--

DROP TABLE IF EXISTS `combat_participants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `combat_participants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `combat_session_id` bigint(20) unsigned NOT NULL,
  `player_id` bigint(20) unsigned DEFAULT NULL,
  `player_ship_id` bigint(20) unsigned DEFAULT NULL,
  `side` varchar(255) NOT NULL,
  `starting_hull` int(11) NOT NULL,
  `current_hull` int(11) NOT NULL,
  `damage_dealt` int(11) NOT NULL DEFAULT 0,
  `damage_taken` int(11) NOT NULL DEFAULT 0,
  `survived` tinyint(1) NOT NULL DEFAULT 1,
  `result` varchar(255) DEFAULT NULL,
  `xp_earned` int(11) NOT NULL DEFAULT 0,
  `credits_earned` decimal(15,2) NOT NULL DEFAULT 0.00,
  `loot_received` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`loot_received`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `combat_participants_combat_session_id_player_id_unique` (`combat_session_id`,`player_id`),
  KEY `combat_participants_player_ship_id_foreign` (`player_ship_id`),
  KEY `combat_participants_combat_session_id_index` (`combat_session_id`),
  KEY `combat_participants_player_id_index` (`player_id`),
  KEY `combat_participants_combat_session_id_side_index` (`combat_session_id`,`side`),
  KEY `combat_participants_result_index` (`result`),
  CONSTRAINT `combat_participants_combat_session_id_foreign` FOREIGN KEY (`combat_session_id`) REFERENCES `combat_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `combat_participants_player_id_foreign` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE,
  CONSTRAINT `combat_participants_player_ship_id_foreign` FOREIGN KEY (`player_ship_id`) REFERENCES `player_ships` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `combat_sessions`
--

DROP TABLE IF EXISTS `combat_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `combat_sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` uuid NOT NULL,
  `combat_type` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `current_round` int(11) NOT NULL DEFAULT 1,
  `poi_id` bigint(20) unsigned DEFAULT NULL,
  `victor_type` varchar(255) DEFAULT NULL,
  `victor_player_id` bigint(20) unsigned DEFAULT NULL,
  `combat_log` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`combat_log`)),
  `rewards` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`rewards`)),
  `pvp_challenge_id` bigint(20) unsigned DEFAULT NULL,
  `target_colony_id` bigint(20) unsigned DEFAULT NULL,
  `started_at` timestamp NOT NULL,
  `ended_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `combat_sessions_uuid_unique` (`uuid`),
  KEY `combat_sessions_victor_player_id_foreign` (`victor_player_id`),
  KEY `combat_sessions_pvp_challenge_id_foreign` (`pvp_challenge_id`),
  KEY `combat_sessions_target_colony_id_foreign` (`target_colony_id`),
  KEY `combat_sessions_combat_type_index` (`combat_type`),
  KEY `combat_sessions_status_index` (`status`),
  KEY `combat_sessions_poi_id_index` (`poi_id`),
  CONSTRAINT `combat_sessions_poi_id_foreign` FOREIGN KEY (`poi_id`) REFERENCES `points_of_interest` (`id`) ON DELETE SET NULL,
  CONSTRAINT `combat_sessions_pvp_challenge_id_foreign` FOREIGN KEY (`pvp_challenge_id`) REFERENCES `pvp_challenges` (`id`) ON DELETE CASCADE,
  CONSTRAINT `combat_sessions_target_colony_id_foreign` FOREIGN KEY (`target_colony_id`) REFERENCES `colonies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `combat_sessions_victor_player_id_foreign` FOREIGN KEY (`victor_player_id`) REFERENCES `players` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `galaxies`
--

DROP TABLE IF EXISTS `galaxies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `galaxies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` uuid NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `width` int(10) unsigned NOT NULL,
  `height` int(10) unsigned NOT NULL,
  `seed` bigint(20) NOT NULL,
  `distribution_method` tinyint(4) NOT NULL DEFAULT 0,
  `spacing_factor` double NOT NULL DEFAULT 0.75,
  `engine` tinyint(4) NOT NULL DEFAULT 0,
  `turn_limit` int(10) unsigned NOT NULL DEFAULT 200,
  `status` tinyint(4) NOT NULL DEFAULT 0,
  `version` varchar(20) NOT NULL DEFAULT '1.0.0',
  `is_public` tinyint(1) NOT NULL DEFAULT 1,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `galaxies_galaxy_uuid_unique` (`uuid`)
) ENGINE=InnoDB AUTO_INCREMENT=565 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `market_events`
--

DROP TABLE IF EXISTS `market_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `market_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` uuid NOT NULL,
  `mineral_id` bigint(20) unsigned DEFAULT NULL,
  `trading_hub_id` bigint(20) unsigned DEFAULT NULL,
  `event_type` varchar(255) NOT NULL,
  `price_multiplier` decimal(5,2) NOT NULL DEFAULT 1.00,
  `description` text NOT NULL,
  `started_at` timestamp NOT NULL,
  `expires_at` timestamp NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `market_events_uuid_unique` (`uuid`),
  KEY `market_events_mineral_id_index` (`mineral_id`),
  KEY `market_events_trading_hub_id_index` (`trading_hub_id`),
  KEY `market_events_is_active_expires_at_index` (`is_active`,`expires_at`),
  CONSTRAINT `market_events_mineral_id_foreign` FOREIGN KEY (`mineral_id`) REFERENCES `minerals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `market_events_trading_hub_id_foreign` FOREIGN KEY (`trading_hub_id`) REFERENCES `trading_hubs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=63 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `minerals`
--

DROP TABLE IF EXISTS `minerals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `minerals` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` uuid NOT NULL,
  `name` varchar(255) NOT NULL,
  `symbol` varchar(10) NOT NULL,
  `description` text DEFAULT NULL,
  `base_value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `rarity` varchar(255) NOT NULL DEFAULT 'common',
  `attributes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attributes`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `minerals_uuid_unique` (`uuid`),
  UNIQUE KEY `minerals_name_unique` (`name`),
  UNIQUE KEY `minerals_symbol_unique` (`symbol`),
  KEY `minerals_rarity_index` (`rarity`)
) ENGINE=InnoDB AUTO_INCREMENT=92 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=77 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pirate_captains`
--

DROP TABLE IF EXISTS `pirate_captains`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pirate_captains` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` uuid NOT NULL,
  `faction_id` bigint(20) unsigned NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL DEFAULT 'Captain',
  `combat_skill` int(11) NOT NULL DEFAULT 50,
  `attributes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attributes`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pirate_captains_uuid_unique` (`uuid`),
  KEY `pirate_captains_faction_id_index` (`faction_id`),
  CONSTRAINT `pirate_captains_faction_id_foreign` FOREIGN KEY (`faction_id`) REFERENCES `pirate_factions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=107 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pirate_cargo`
--

DROP TABLE IF EXISTS `pirate_cargo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pirate_cargo` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `pirate_fleet_id` bigint(20) unsigned NOT NULL,
  `mineral_id` bigint(20) unsigned DEFAULT NULL,
  `plan_id` bigint(20) unsigned DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pirate_cargo_mineral_id_foreign` (`mineral_id`),
  KEY `pirate_cargo_plan_id_foreign` (`plan_id`),
  KEY `pirate_cargo_pirate_fleet_id_index` (`pirate_fleet_id`),
  CONSTRAINT `pirate_cargo_mineral_id_foreign` FOREIGN KEY (`mineral_id`) REFERENCES `minerals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pirate_cargo_pirate_fleet_id_foreign` FOREIGN KEY (`pirate_fleet_id`) REFERENCES `pirate_fleets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pirate_cargo_plan_id_foreign` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pirate_cargo_check` CHECK (`mineral_id` is not null and `plan_id` is null or `mineral_id` is null and `plan_id` is not null)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pirate_factions`
--

DROP TABLE IF EXISTS `pirate_factions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pirate_factions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `galaxy_id` bigint(20) unsigned NOT NULL,
  `uuid` uuid NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `attributes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attributes`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pirate_factions_uuid_unique` (`uuid`),
  UNIQUE KEY `pirate_factions_name_unique` (`name`),
  KEY `pirate_factions_is_active_index` (`is_active`),
  KEY `pirate_factions_galaxy_id_index` (`galaxy_id`),
  CONSTRAINT `pirate_factions_galaxy_id_foreign` FOREIGN KEY (`galaxy_id`) REFERENCES `galaxies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=63 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pirate_fleets`
--

DROP TABLE IF EXISTS `pirate_fleets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pirate_fleets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` uuid NOT NULL,
  `captain_id` bigint(20) unsigned NOT NULL,
  `ship_id` bigint(20) unsigned NOT NULL,
  `ship_name` varchar(255) DEFAULT NULL,
  `hull` int(11) NOT NULL DEFAULT 100,
  `max_hull` int(11) NOT NULL DEFAULT 100,
  `weapons` int(11) NOT NULL DEFAULT 10,
  `speed` int(11) NOT NULL DEFAULT 100,
  `warp_drive` int(11) NOT NULL DEFAULT 1,
  `cargo_capacity` int(11) NOT NULL DEFAULT 50,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pirate_fleets_uuid_unique` (`uuid`),
  KEY `pirate_fleets_ship_id_foreign` (`ship_id`),
  KEY `pirate_fleets_captain_id_status_index` (`captain_id`,`status`),
  CONSTRAINT `pirate_fleets_captain_id_foreign` FOREIGN KEY (`captain_id`) REFERENCES `pirate_captains` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pirate_fleets_ship_id_foreign` FOREIGN KEY (`ship_id`) REFERENCES `ships` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `plans`
--

DROP TABLE IF EXISTS `plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `plans` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` uuid NOT NULL,
  `name` varchar(255) NOT NULL,
  `component` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `additional_levels` int(11) NOT NULL DEFAULT 10,
  `price` decimal(12,2) NOT NULL,
  `rarity` varchar(255) NOT NULL DEFAULT 'rare',
  `requirements` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`requirements`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `plans_uuid_unique` (`uuid`),
  KEY `plans_component_index` (`component`),
  KEY `plans_rarity_index` (`rarity`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `player_cargos`
--

DROP TABLE IF EXISTS `player_cargos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `player_cargos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `player_ship_id` bigint(20) unsigned NOT NULL,
  `mineral_id` bigint(20) unsigned NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `player_cargos_player_ship_id_mineral_id_unique` (`player_ship_id`,`mineral_id`),
  KEY `player_cargos_mineral_id_foreign` (`mineral_id`),
  KEY `player_cargos_player_ship_id_index` (`player_ship_id`),
  CONSTRAINT `player_cargos_mineral_id_foreign` FOREIGN KEY (`mineral_id`) REFERENCES `minerals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `player_cargos_player_ship_id_foreign` FOREIGN KEY (`player_ship_id`) REFERENCES `player_ships` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `player_notifications`
--

DROP TABLE IF EXISTS `player_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `player_notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` uuid NOT NULL,
  `player_id` bigint(20) unsigned NOT NULL,
  `type` varchar(255) NOT NULL,
  `severity` varchar(255) NOT NULL DEFAULT 'info',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `colony_id` bigint(20) unsigned DEFAULT NULL,
  `poi_id` bigint(20) unsigned DEFAULT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `player_notifications_uuid_unique` (`uuid`),
  KEY `player_notifications_colony_id_foreign` (`colony_id`),
  KEY `player_notifications_poi_id_foreign` (`poi_id`),
  KEY `player_notifications_player_id_index` (`player_id`),
  KEY `player_notifications_player_id_is_read_index` (`player_id`,`is_read`),
  KEY `player_notifications_type_index` (`type`),
  CONSTRAINT `player_notifications_colony_id_foreign` FOREIGN KEY (`colony_id`) REFERENCES `colonies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `player_notifications_player_id_foreign` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE,
  CONSTRAINT `player_notifications_poi_id_foreign` FOREIGN KEY (`poi_id`) REFERENCES `points_of_interest` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=137 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `player_plans`
--

DROP TABLE IF EXISTS `player_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `player_plans` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `player_id` bigint(20) unsigned NOT NULL,
  `plan_id` bigint(20) unsigned NOT NULL,
  `acquired_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `player_plans_plan_id_foreign` (`plan_id`),
  KEY `player_plans_player_id_index` (`player_id`),
  CONSTRAINT `player_plans_plan_id_foreign` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `player_plans_player_id_foreign` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `player_ship_fighters`
--

DROP TABLE IF EXISTS `player_ship_fighters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `player_ship_fighters` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` uuid NOT NULL,
  `player_ship_id` bigint(20) unsigned NOT NULL,
  `ship_id` bigint(20) unsigned NOT NULL,
  `fighter_name` varchar(255) DEFAULT NULL,
  `hull` int(11) NOT NULL DEFAULT 0,
  `max_hull` int(11) NOT NULL DEFAULT 0,
  `weapons` int(11) NOT NULL DEFAULT 0,
  `is_deployed` tinyint(1) NOT NULL DEFAULT 0,
  `attributes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attributes`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `player_ship_fighters_uuid_unique` (`uuid`),
  KEY `player_ship_fighters_ship_id_foreign` (`ship_id`),
  KEY `player_ship_fighters_player_ship_id_index` (`player_ship_id`),
  CONSTRAINT `player_ship_fighters_player_ship_id_foreign` FOREIGN KEY (`player_ship_id`) REFERENCES `player_ships` (`id`) ON DELETE CASCADE,
  CONSTRAINT `player_ship_fighters_ship_id_foreign` FOREIGN KEY (`ship_id`) REFERENCES `ships` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `player_ships`
--

DROP TABLE IF EXISTS `player_ships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `player_ships` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` uuid NOT NULL,
  `player_id` bigint(20) unsigned NOT NULL,
  `ship_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `current_fuel` int(11) NOT NULL DEFAULT 100,
  `max_fuel` int(11) NOT NULL DEFAULT 100,
  `fuel_last_updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `hull` int(11) NOT NULL DEFAULT 100,
  `max_hull` int(11) NOT NULL DEFAULT 100,
  `weapons` int(11) NOT NULL DEFAULT 10,
  `cargo_hold` int(11) NOT NULL DEFAULT 10,
  `sensors` int(11) NOT NULL DEFAULT 1,
  `warp_drive` int(11) NOT NULL DEFAULT 1,
  `current_cargo` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `status` varchar(255) NOT NULL DEFAULT 'operational',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `player_ships_uuid_unique` (`uuid`),
  KEY `player_ships_ship_id_foreign` (`ship_id`),
  KEY `player_ships_player_id_index` (`player_id`),
  KEY `player_ships_is_active_index` (`is_active`),
  CONSTRAINT `player_ships_player_id_foreign` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE,
  CONSTRAINT `player_ships_ship_id_foreign` FOREIGN KEY (`ship_id`) REFERENCES `ships` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=362 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `player_star_charts`
--

DROP TABLE IF EXISTS `player_star_charts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `player_star_charts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `player_id` bigint(20) unsigned NOT NULL,
  `revealed_poi_id` bigint(20) unsigned NOT NULL,
  `purchased_from_poi_id` bigint(20) unsigned DEFAULT NULL,
  `price_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `purchased_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `player_star_charts_player_id_revealed_poi_id_unique` (`player_id`,`revealed_poi_id`),
  KEY `player_star_charts_revealed_poi_id_foreign` (`revealed_poi_id`),
  KEY `player_star_charts_purchased_from_poi_id_foreign` (`purchased_from_poi_id`),
  KEY `player_star_charts_player_id_index` (`player_id`),
  CONSTRAINT `player_star_charts_player_id_foreign` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE,
  CONSTRAINT `player_star_charts_purchased_from_poi_id_foreign` FOREIGN KEY (`purchased_from_poi_id`) REFERENCES `points_of_interest` (`id`) ON DELETE SET NULL,
  CONSTRAINT `player_star_charts_revealed_poi_id_foreign` FOREIGN KEY (`revealed_poi_id`) REFERENCES `points_of_interest` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `players`
--

DROP TABLE IF EXISTS `players`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `players` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` uuid NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `galaxy_id` bigint(20) unsigned NOT NULL,
  `call_sign` varchar(255) NOT NULL,
  `credits` decimal(15,2) NOT NULL DEFAULT 1000.00,
  `experience` int(11) NOT NULL DEFAULT 0,
  `level` int(11) NOT NULL DEFAULT 1,
  `ships_destroyed` int(11) NOT NULL DEFAULT 0,
  `combats_won` int(11) NOT NULL DEFAULT 0,
  `combats_lost` int(11) NOT NULL DEFAULT 0,
  `total_trade_volume` decimal(15,2) NOT NULL DEFAULT 0.00,
  `current_poi_id` bigint(20) unsigned DEFAULT NULL,
  `last_mirror_travel_at` timestamp NULL DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `last_trading_hub_poi_id` bigint(20) unsigned DEFAULT NULL,
  `mirror_universe_entry_time` timestamp NULL DEFAULT NULL COMMENT 'Timestamp when player entered mirror universe (for cooldown tracking)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `players_uuid_unique` (`uuid`),
  UNIQUE KEY `players_user_id_galaxy_id_unique` (`user_id`,`galaxy_id`),
  UNIQUE KEY `players_galaxy_call_sign_unique` (`galaxy_id`,`call_sign`),
  KEY `players_current_poi_id_foreign` (`current_poi_id`),
  KEY `players_user_id_index` (`user_id`),
  KEY `players_status_index` (`status`),
  KEY `players_last_trading_hub_poi_id_foreign` (`last_trading_hub_poi_id`),
  KEY `players_mirror_universe_entry_time_index` (`mirror_universe_entry_time`),
  CONSTRAINT `players_current_poi_id_foreign` FOREIGN KEY (`current_poi_id`) REFERENCES `points_of_interest` (`id`) ON DELETE SET NULL,
  CONSTRAINT `players_galaxy_id_foreign` FOREIGN KEY (`galaxy_id`) REFERENCES `galaxies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `players_last_trading_hub_poi_id_foreign` FOREIGN KEY (`last_trading_hub_poi_id`) REFERENCES `points_of_interest` (`id`) ON DELETE SET NULL,
  CONSTRAINT `players_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=548 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `points_of_interest`
--

DROP TABLE IF EXISTS `points_of_interest`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `points_of_interest` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` uuid NOT NULL,
  `galaxy_id` bigint(20) unsigned NOT NULL,
  `sector_id` bigint(20) unsigned DEFAULT NULL,
  `parent_poi_id` bigint(20) unsigned DEFAULT NULL,
  `orbital_index` smallint(5) unsigned DEFAULT NULL COMMENT 'Position in orbital sequence (1=innermost, higher=outer)',
  `type` tinyint(3) unsigned NOT NULL,
  `status` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `x` int(10) unsigned NOT NULL,
  `y` int(10) unsigned NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `attributes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attributes`)),
  `is_hidden` tinyint(1) NOT NULL DEFAULT 0,
  `is_inhabited` tinyint(1) NOT NULL DEFAULT 0,
  `version` varchar(20) NOT NULL DEFAULT '',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `planet_class` varchar(255) DEFAULT NULL,
  `temperature` decimal(5,2) DEFAULT NULL,
  `gravity` decimal(3,2) DEFAULT NULL,
  `atmosphere_type` varchar(255) DEFAULT NULL,
  `atmosphere_density` decimal(3,2) DEFAULT NULL,
  `water_coverage` decimal(3,2) DEFAULT NULL,
  `has_magnetic_field` tinyint(1) NOT NULL DEFAULT 0,
  `radiation_level` decimal(5,2) DEFAULT NULL,
  `mineral_deposits` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`mineral_deposits`)),
  `has_asteroid_field` tinyint(1) NOT NULL DEFAULT 0,
  `moon_count` int(11) NOT NULL DEFAULT 0,
  `habitability_score` decimal(3,2) DEFAULT NULL,
  `is_colonizable` tinyint(1) NOT NULL DEFAULT 0,
  `is_colonized` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `points_of_interest_uuid_unique` (`uuid`),
  KEY `points_of_interest_galaxy_id_foreign` (`galaxy_id`),
  KEY `points_of_interest_parent_poi_id_orbital_index_index` (`parent_poi_id`,`orbital_index`),
  KEY `points_of_interest_sector_id_index` (`sector_id`),
  KEY `points_of_interest_is_inhabited_index` (`is_inhabited`),
  CONSTRAINT `points_of_interest_galaxy_id_foreign` FOREIGN KEY (`galaxy_id`) REFERENCES `galaxies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `points_of_interest_parent_poi_id_foreign` FOREIGN KEY (`parent_poi_id`) REFERENCES `points_of_interest` (`id`) ON DELETE SET NULL,
  CONSTRAINT `points_of_interest_sector_id_foreign` FOREIGN KEY (`sector_id`) REFERENCES `sectors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9311 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `precursor_ships`
--

DROP TABLE IF EXISTS `precursor_ships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `precursor_ships` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` uuid NOT NULL,
  `galaxy_id` bigint(20) unsigned NOT NULL,
  `x` int(10) unsigned NOT NULL COMMENT 'Random coordinates in interstellar void',
  `y` int(10) unsigned NOT NULL,
  `is_discovered` tinyint(1) NOT NULL DEFAULT 0,
  `discovered_by_player_id` bigint(20) unsigned DEFAULT NULL,
  `discovered_at` timestamp NULL DEFAULT NULL,
  `claimed_by_player_id` bigint(20) unsigned DEFAULT NULL,
  `claimed_at` timestamp NULL DEFAULT NULL,
  `hull` bigint(20) unsigned NOT NULL DEFAULT 1000000 COMMENT '100x any player ship',
  `max_hull` bigint(20) unsigned NOT NULL DEFAULT 1000000,
  `weapons` int(10) unsigned NOT NULL DEFAULT 10000 COMMENT '100x best weapons',
  `sensors` int(10) unsigned NOT NULL DEFAULT 100 COMMENT '100x sensor range',
  `speed` int(10) unsigned NOT NULL DEFAULT 10000 COMMENT '100x fastest ship',
  `warp_drive` int(10) unsigned NOT NULL DEFAULT 100 COMMENT 'Interstellar flight capable',
  `cargo_capacity` bigint(20) unsigned NOT NULL DEFAULT 1000000 COMMENT 'Pocket dimension: 1M units',
  `current_cargo` bigint(20) unsigned NOT NULL DEFAULT 0,
  `fuel` bigint(20) unsigned NOT NULL DEFAULT 999999999,
  `max_fuel` bigint(20) unsigned NOT NULL DEFAULT 999999999,
  `precursor_tech` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Jump drive, shield harmonics, etc.' CHECK (json_valid(`precursor_tech`)),
  `description` text DEFAULT NULL,
  `precursor_name` varchar(255) NOT NULL DEFAULT 'Void Strider' COMMENT 'Original Precursor designation',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `precursor_ships_uuid_unique` (`uuid`),
  KEY `precursor_ships_discovered_by_player_id_foreign` (`discovered_by_player_id`),
  KEY `precursor_ships_claimed_by_player_id_foreign` (`claimed_by_player_id`),
  KEY `precursor_ships_galaxy_id_x_y_index` (`galaxy_id`,`x`,`y`),
  KEY `precursor_ships_is_discovered_galaxy_id_index` (`is_discovered`,`galaxy_id`),
  CONSTRAINT `precursor_ships_claimed_by_player_id_foreign` FOREIGN KEY (`claimed_by_player_id`) REFERENCES `players` (`id`) ON DELETE SET NULL,
  CONSTRAINT `precursor_ships_discovered_by_player_id_foreign` FOREIGN KEY (`discovered_by_player_id`) REFERENCES `players` (`id`) ON DELETE SET NULL,
  CONSTRAINT `precursor_ships_galaxy_id_foreign` FOREIGN KEY (`galaxy_id`) REFERENCES `galaxies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pvp_challenges`
--

DROP TABLE IF EXISTS `pvp_challenges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pvp_challenges` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` uuid NOT NULL,
  `challenger_id` bigint(20) unsigned NOT NULL,
  `target_id` bigint(20) unsigned NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `message` text DEFAULT NULL,
  `wager_credits` decimal(15,2) NOT NULL DEFAULT 0.00,
  `max_team_size` int(11) NOT NULL DEFAULT 1,
  `challenge_poi_id` bigint(20) unsigned DEFAULT NULL,
  `challenged_at` timestamp NOT NULL,
  `responded_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pvp_challenges_uuid_unique` (`uuid`),
  KEY `pvp_challenges_challenge_poi_id_foreign` (`challenge_poi_id`),
  KEY `pvp_challenges_challenger_id_index` (`challenger_id`),
  KEY `pvp_challenges_target_id_index` (`target_id`),
  KEY `pvp_challenges_status_index` (`status`),
  KEY `pvp_challenges_challenger_id_target_id_status_index` (`challenger_id`,`target_id`,`status`),
  CONSTRAINT `pvp_challenges_challenge_poi_id_foreign` FOREIGN KEY (`challenge_poi_id`) REFERENCES `points_of_interest` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pvp_challenges_challenger_id_foreign` FOREIGN KEY (`challenger_id`) REFERENCES `players` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pvp_challenges_target_id_foreign` FOREIGN KEY (`target_id`) REFERENCES `players` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pvp_team_invitations`
--

DROP TABLE IF EXISTS `pvp_team_invitations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pvp_team_invitations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `pvp_challenge_id` bigint(20) unsigned NOT NULL,
  `invited_player_id` bigint(20) unsigned NOT NULL,
  `invited_by_player_id` bigint(20) unsigned NOT NULL,
  `side` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `responded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pvp_team_invitations_unique` (`pvp_challenge_id`,`invited_player_id`),
  KEY `pvp_team_invitations_invited_by_player_id_foreign` (`invited_by_player_id`),
  KEY `pvp_team_invitations_invited_player_id_index` (`invited_player_id`),
  KEY `pvp_team_invitations_pvp_challenge_id_side_index` (`pvp_challenge_id`,`side`),
  CONSTRAINT `pvp_team_invitations_invited_by_player_id_foreign` FOREIGN KEY (`invited_by_player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pvp_team_invitations_invited_player_id_foreign` FOREIGN KEY (`invited_player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pvp_team_invitations_pvp_challenge_id_foreign` FOREIGN KEY (`pvp_challenge_id`) REFERENCES `pvp_challenges` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sectors`
--

DROP TABLE IF EXISTS `sectors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sectors` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` uuid NOT NULL,
  `galaxy_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `grid_x` int(11) NOT NULL,
  `grid_y` int(11) NOT NULL,
  `x_min` double NOT NULL,
  `x_max` double NOT NULL,
  `y_min` double NOT NULL,
  `y_max` double NOT NULL,
  `attributes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attributes`)),
  `danger_level` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sectors_galaxy_id_grid_x_grid_y_unique` (`galaxy_id`,`grid_x`,`grid_y`),
  UNIQUE KEY `sectors_uuid_unique` (`uuid`),
  KEY `sectors_galaxy_id_grid_x_grid_y_index` (`galaxy_id`,`grid_x`,`grid_y`),
  CONSTRAINT `sectors_galaxy_id_foreign` FOREIGN KEY (`galaxy_id`) REFERENCES `galaxies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=210 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ships`
--

DROP TABLE IF EXISTS `ships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ships` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` uuid NOT NULL,
  `galaxy_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `class` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `base_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cargo_capacity` int(11) NOT NULL DEFAULT 0,
  `speed` int(11) NOT NULL DEFAULT 0,
  `hull_strength` int(11) NOT NULL DEFAULT 0,
  `shield_strength` int(11) NOT NULL DEFAULT 0,
  `weapon_slots` int(11) NOT NULL DEFAULT 0,
  `utility_slots` int(11) NOT NULL DEFAULT 0,
  `rarity` varchar(255) NOT NULL DEFAULT 'common',
  `requirements` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`requirements`)),
  `attributes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attributes`)),
  `is_available` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ships_uuid_unique` (`uuid`),
  KEY `ships_class_index` (`class`),
  KEY `ships_rarity_index` (`rarity`),
  KEY `ships_is_available_index` (`is_available`)
) ENGINE=InnoDB AUTO_INCREMENT=300 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `stellar_cartographers`
--

DROP TABLE IF EXISTS `stellar_cartographers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `stellar_cartographers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `poi_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `chart_base_price` decimal(10,2) NOT NULL DEFAULT 1000.00,
  `markup_multiplier` decimal(4,2) NOT NULL DEFAULT 1.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stellar_cartographers_poi_id_index` (`poi_id`),
  KEY `stellar_cartographers_is_active_index` (`is_active`),
  CONSTRAINT `stellar_cartographers_poi_id_foreign` FOREIGN KEY (`poi_id`) REFERENCES `points_of_interest` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `telescope_entries`
--

DROP TABLE IF EXISTS `telescope_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `telescope_entries` (
  `sequence` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` uuid NOT NULL,
  `batch_id` uuid NOT NULL,
  `family_hash` varchar(255) DEFAULT NULL,
  `should_display_on_index` tinyint(1) NOT NULL DEFAULT 1,
  `type` varchar(20) NOT NULL,
  `content` longtext NOT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`sequence`),
  UNIQUE KEY `telescope_entries_uuid_unique` (`uuid`),
  KEY `telescope_entries_batch_id_index` (`batch_id`),
  KEY `telescope_entries_family_hash_index` (`family_hash`),
  KEY `telescope_entries_created_at_index` (`created_at`),
  KEY `telescope_entries_type_should_display_on_index_index` (`type`,`should_display_on_index`)
) ENGINE=InnoDB AUTO_INCREMENT=87090 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `telescope_entries_tags`
--

DROP TABLE IF EXISTS `telescope_entries_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `telescope_entries_tags` (
  `entry_uuid` uuid NOT NULL,
  `tag` varchar(255) NOT NULL,
  PRIMARY KEY (`entry_uuid`,`tag`),
  KEY `telescope_entries_tags_tag_index` (`tag`),
  CONSTRAINT `telescope_entries_tags_entry_uuid_foreign` FOREIGN KEY (`entry_uuid`) REFERENCES `telescope_entries` (`uuid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `telescope_monitoring`
--

DROP TABLE IF EXISTS `telescope_monitoring`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `telescope_monitoring` (
  `tag` varchar(255) NOT NULL,
  PRIMARY KEY (`tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `trading_hub_inventories`
--

DROP TABLE IF EXISTS `trading_hub_inventories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `trading_hub_inventories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `trading_hub_id` bigint(20) unsigned NOT NULL,
  `mineral_id` bigint(20) unsigned NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `current_price` decimal(15,2) NOT NULL,
  `buy_price` decimal(15,2) NOT NULL,
  `sell_price` decimal(15,2) NOT NULL,
  `demand_level` int(11) NOT NULL DEFAULT 50,
  `supply_level` int(11) NOT NULL DEFAULT 50,
  `last_price_update` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `trading_hub_inventories_trading_hub_id_mineral_id_unique` (`trading_hub_id`,`mineral_id`),
  KEY `trading_hub_inventories_mineral_id_index` (`mineral_id`),
  CONSTRAINT `trading_hub_inventories_mineral_id_foreign` FOREIGN KEY (`mineral_id`) REFERENCES `minerals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `trading_hub_inventories_trading_hub_id_foreign` FOREIGN KEY (`trading_hub_id`) REFERENCES `trading_hubs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1552 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `trading_hub_plans`
--

DROP TABLE IF EXISTS `trading_hub_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `trading_hub_plans` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `trading_hub_id` bigint(20) unsigned NOT NULL,
  `plan_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `trading_hub_plans_trading_hub_id_index` (`trading_hub_id`),
  KEY `trading_hub_plans_plan_id_index` (`plan_id`),
  CONSTRAINT `trading_hub_plans_plan_id_foreign` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `trading_hub_plans_trading_hub_id_foreign` FOREIGN KEY (`trading_hub_id`) REFERENCES `trading_hubs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=212 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `trading_hub_ships`
--

DROP TABLE IF EXISTS `trading_hub_ships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `trading_hub_ships` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `trading_hub_id` bigint(20) unsigned NOT NULL,
  `galaxy_id` bigint(20) unsigned NOT NULL,
  `ship_id` bigint(20) unsigned NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `current_price` decimal(12,2) NOT NULL,
  `demand_level` int(11) NOT NULL DEFAULT 50,
  `supply_level` int(11) NOT NULL DEFAULT 50,
  `last_price_update` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `trading_hub_ships_trading_hub_id_ship_id_unique` (`trading_hub_id`,`ship_id`),
  KEY `trading_hub_ships_ship_id_foreign` (`ship_id`),
  KEY `trading_hub_ships_trading_hub_id_index` (`trading_hub_id`),
  KEY `trading_hub_ships_galaxy_id_index` (`galaxy_id`),
  CONSTRAINT `trading_hub_ships_galaxy_id_foreign` FOREIGN KEY (`galaxy_id`) REFERENCES `galaxies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `trading_hub_ships_ship_id_foreign` FOREIGN KEY (`ship_id`) REFERENCES `ships` (`id`) ON DELETE CASCADE,
  CONSTRAINT `trading_hub_ships_trading_hub_id_foreign` FOREIGN KEY (`trading_hub_id`) REFERENCES `trading_hubs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=551 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `trading_hubs`
--

DROP TABLE IF EXISTS `trading_hubs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `trading_hubs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` uuid NOT NULL,
  `poi_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'standard',
  `has_salvage_yard` tinyint(1) NOT NULL DEFAULT 0,
  `has_plans` tinyint(1) NOT NULL DEFAULT 0,
  `gate_count` int(11) NOT NULL DEFAULT 0,
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 5.00,
  `services` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`services`)),
  `attributes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attributes`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `trading_hubs_uuid_unique` (`uuid`),
  KEY `trading_hubs_poi_id_index` (`poi_id`),
  KEY `trading_hubs_type_index` (`type`),
  KEY `trading_hubs_is_active_index` (`is_active`),
  CONSTRAINT `trading_hubs_poi_id_foreign` FOREIGN KEY (`poi_id`) REFERENCES `points_of_interest` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=191 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=560 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `warp_gates`
--

DROP TABLE IF EXISTS `warp_gates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `warp_gates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` uuid NOT NULL,
  `galaxy_id` bigint(20) unsigned NOT NULL,
  `source_poi_id` bigint(20) unsigned NOT NULL,
  `destination_poi_id` bigint(20) unsigned NOT NULL,
  `is_hidden` tinyint(1) NOT NULL DEFAULT 0,
  `distance` double DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `gate_type` varchar(255) NOT NULL DEFAULT 'standard' COMMENT 'Type of gate: standard, mirror_entry, mirror_return',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `warp_gates_source_poi_id_destination_poi_id_unique` (`source_poi_id`,`destination_poi_id`),
  UNIQUE KEY `warp_gates_uuid_unique` (`uuid`),
  KEY `warp_gates_destination_poi_id_foreign` (`destination_poi_id`),
  KEY `warp_gates_galaxy_id_source_poi_id_index` (`galaxy_id`,`source_poi_id`),
  KEY `warp_gates_galaxy_id_destination_poi_id_index` (`galaxy_id`,`destination_poi_id`),
  KEY `warp_gates_gate_type_index` (`gate_type`),
  CONSTRAINT `warp_gates_destination_poi_id_foreign` FOREIGN KEY (`destination_poi_id`) REFERENCES `points_of_interest` (`id`) ON DELETE CASCADE,
  CONSTRAINT `warp_gates_galaxy_id_foreign` FOREIGN KEY (`galaxy_id`) REFERENCES `galaxies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `warp_gates_source_poi_id_foreign` FOREIGN KEY (`source_poi_id`) REFERENCES `points_of_interest` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1664 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `warp_lane_pirates`
--

DROP TABLE IF EXISTS `warp_lane_pirates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `warp_lane_pirates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` uuid NOT NULL,
  `warp_gate_id` bigint(20) unsigned NOT NULL,
  `captain_id` bigint(20) unsigned NOT NULL,
  `fleet_size` int(11) NOT NULL DEFAULT 1,
  `difficulty_tier` int(11) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_encounter_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `warp_lane_pirates_warp_gate_id_unique` (`warp_gate_id`),
  UNIQUE KEY `warp_lane_pirates_uuid_unique` (`uuid`),
  KEY `warp_lane_pirates_captain_id_foreign` (`captain_id`),
  KEY `warp_lane_pirates_is_active_difficulty_tier_index` (`is_active`,`difficulty_tier`),
  CONSTRAINT `warp_lane_pirates_captain_id_foreign` FOREIGN KEY (`captain_id`) REFERENCES `pirate_captains` (`id`) ON DELETE CASCADE,
  CONSTRAINT `warp_lane_pirates_warp_gate_id_foreign` FOREIGN KEY (`warp_gate_id`) REFERENCES `warp_gates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

-- Dump completed on 2026-01-16  9:49:15
