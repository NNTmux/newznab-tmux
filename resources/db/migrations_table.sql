-- MySQL dump 10.16  Distrib 10.3.9-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: 127.0.0.1    Database: nntmux
-- ------------------------------------------------------
-- Server version	10.3.9-MariaDB-1:10.3.9+maria~xenial-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=81 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'2014_01_07_073615_create_tagged_table',1),(2,'2014_01_07_073615_create_tags_table',1),(3,'2014_02_01_311070_create_firewall_table',1),(4,'2016_06_29_073615_create_tag_groups_table',1),(5,'2016_06_29_073615_update_tags_table',1),(6,'2017_11_29_223842_create_countries_table',1),(7,'2018_01_16_195548_create_users_table',1),(8,'2018_01_17_150719_create_permission_tables',1),(9,'2018_01_17_154034_create_categories_table',1),(10,'2018_01_18_101314_create_category_regexes_table',1),(11,'2018_01_18_102213_create_collection_regexes_table',1),(12,'2018_01_18_102716_create_binaryblacklist_table',1),(13,'2018_01_18_103104_create_content_table',1),(14,'2018_01_18_103520_create_forumpost_table',1),(15,'2018_01_18_103816_create_genres_table',1),(16,'2018_01_18_104345_create_groups_table',1),(17,'2018_01_18_104710_create_menu_table',1),(18,'2018_01_18_105455_create_release_naming_regexes_table',1),(19,'2018_01_18_105834_create_settings_table',1),(20,'2018_01_20_195500_create_collections_table',1),(21,'2018_01_20_195528_create_releases_table',1),(22,'2018_01_20_195604_create_anidb_episodes_table',1),(23,'2018_01_20_195615_create_anidb_info_table',1),(24,'2018_01_20_195624_create_anidb_titles_table',1),(25,'2018_01_20_195636_create_audio_data_table',1),(26,'2018_01_20_195648_create_binaries_table',1),(27,'2018_01_20_195703_create_bookinfo_table',1),(28,'2018_01_20_195716_create_consoleinfo_table',1),(29,'2018_01_20_195728_create_dnzb_failures_table',1),(30,'2018_01_20_195739_create_gamesinfo_table',1),(31,'2018_01_20_195752_create_invitations_table',1),(32,'2018_01_20_195801_create_logging_table',1),(33,'2018_01_20_195812_create_missed_parts_table',1),(34,'2018_01_20_195822_create_movieinfo_table',1),(35,'2018_01_20_195832_create_musicinfo_table',1),(36,'2018_01_20_195846_create_multigroup_collections_table',1),(37,'2018_01_20_195858_create_multigroup_posters_table',1),(38,'2018_01_20_195915_create_par_hashes_table',1),(39,'2018_01_20_195925_create_parts_table',1),(40,'2018_01_20_195934_create_predb_table',1),(41,'2018_01_20_195946_create_predb_hashes_table',1),(42,'2018_01_20_195954_create_predb_imports_table',1),(43,'2018_01_20_200005_create_release_comments_table',1),(44,'2018_01_20_200018_create_releases_groups_table',1),(45,'2018_01_20_200030_create_release_regexes_table',1),(46,'2018_01_20_200038_create_release_unique_table',1),(47,'2018_01_20_200046_create_releaseextrafull_table',1),(48,'2018_01_20_200056_create_release_files_table',1),(49,'2018_01_20_200104_create_release_nfos_table',1),(50,'2018_01_20_200124_create_release_subtitles_table',1),(51,'2018_01_20_200135_create_sharing_table',1),(52,'2018_01_20_200142_create_sharing_sites_table',1),(53,'2018_01_20_200151_create_short_groups_table',1),(54,'2018_01_20_200200_create_steam_apps_table',1),(55,'2018_01_20_200211_create_tv_episodes_table',1),(56,'2018_01_20_200218_create_tv_info_table',1),(57,'2018_01_20_200237_create_users_releases_table',1),(58,'2018_01_20_200248_create_user_downloads_table',1),(59,'2018_01_20_200300_create_user_excluded_categories_table',1),(60,'2018_01_20_200309_create_role_excluded_categories_table',1),(61,'2018_01_20_200318_create_user_movies_table',1),(62,'2018_01_20_200328_create_user_requests_table',1),(63,'2018_01_20_200336_create_user_series_table',1),(64,'2018_01_20_200346_create_video_data_table',1),(65,'2018_01_20_200353_create_videos_table',1),(66,'2018_01_20_200403_create_videos_aliases_table',1),(67,'2018_01_20_200417_create_xxxinfo_table',1),(68,'2018_01_20_200431_create_multigroup_binaries_table',1),(69,'2018_01_20_200442_create_multigroup_parts_table',1),(70,'2018_01_20_200451_create_multigroup_missed_parts_table',1),(71,'2018_01_22_220858_add_stored_procedures',1),(72,'2018_04_24_132758_create_cache_table',1),(73,'2018_09_13_070520_add_verification_to_user_table',1),(74,'2018_12_20_221744_create_check_insert_trigger',1),(75,'2018_12_20_222607_create_check_update_trigger',1),(76,'2018_12_20_222736_create_check_rfinsert_trigger',1),(77,'2018_12_20_222836_create_check_rfupdate_trigger',1),(78,'2018_12_20_222936_create_insert_hashes_trigger',1),(79,'2018_12_20_223037_create_update_hashes_trigger',1),(80,'2018_12_20_223131_create_delete_hashes_trigger',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2018-12-20 22:45:59
