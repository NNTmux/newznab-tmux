/*M!999999\- enable the sandbox mode */
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `anidb_info`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `anidb_info` (
  `anidbid` int(10) unsigned NOT NULL COMMENT 'ID of title from AniDB',
  `anilist_id` int(10) unsigned DEFAULT NULL COMMENT 'ID from AniList',
  `mal_id` int(10) unsigned DEFAULT NULL COMMENT 'ID from MyAnimeList',
  `country` char(2) DEFAULT NULL COMMENT 'ISO 3166-1 alpha-2 country code',
  `media_type` varchar(10) DEFAULT NULL COMMENT 'ANIME or MANGA',
  `type` varchar(32) DEFAULT NULL,
  `episodes` int(10) unsigned DEFAULT NULL,
  `duration` int(10) unsigned DEFAULT NULL COMMENT 'Duration in minutes',
  `status` varchar(20) DEFAULT NULL COMMENT 'Media status (FINISHED, RELEASING, etc.)',
  `source` varchar(20) DEFAULT NULL COMMENT 'Original source (MANGA, ORIGINAL, etc.)',
  `hashtag` varchar(255) DEFAULT NULL COMMENT 'AniList hashtag',
  `startdate` date DEFAULT NULL,
  `enddate` date DEFAULT NULL,
  `updated` timestamp NOT NULL DEFAULT current_timestamp(),
  `related` varchar(1024) DEFAULT NULL,
  `similar` varchar(1024) DEFAULT NULL,
  `creators` varchar(1024) DEFAULT NULL,
  `description` mediumtext DEFAULT NULL,
  `rating` varchar(5) DEFAULT NULL,
  `picture` varchar(255) DEFAULT NULL,
  `categories` varchar(1024) DEFAULT NULL,
  `characters` varchar(1024) DEFAULT NULL,
  PRIMARY KEY (`anidbid`),
  KEY `ix_anidb_info_datetime` (`startdate`,`enddate`,`updated`),
  KEY `ix_anidb_info_anilist_id` (`anilist_id`),
  KEY `ix_anidb_info_mal_id` (`mal_id`),
  KEY `ix_anidb_info_country` (`country`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `anidb_titles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `anidb_titles` (
  `anidbid` int(10) unsigned NOT NULL COMMENT 'ID of title from AniDB',
  `type` varchar(25) NOT NULL COMMENT 'type of title.',
  `lang` varchar(25) NOT NULL,
  `title` varchar(255) NOT NULL,
  PRIMARY KEY (`anidbid`,`type`,`lang`,`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `api_token_ip_addresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `api_token_ip_addresses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `users_id` int(10) unsigned NOT NULL,
  `api_token` varchar(64) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `first_seen_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_seen_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `request_count` int(10) unsigned NOT NULL DEFAULT 1,
  `is_rate_limited` tinyint(1) NOT NULL DEFAULT 0,
  `rate_limited_until` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `api_token_ip_addresses_api_token_ip_address_unique` (`api_token`,`ip_address`),
  KEY `api_token_ip_addresses_users_id_index` (`users_id`),
  KEY `api_token_ip_addresses_is_rate_limited_index` (`is_rate_limited`),
  KEY `api_token_ip_addresses_last_seen_at_index` (`last_seen_at`),
  KEY `api_token_ip_addresses_api_token_index` (`api_token`),
  CONSTRAINT `api_token_ip_addresses_users_id_foreign` FOREIGN KEY (`users_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `audio_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `audio_data` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `releases_id` int(10) unsigned NOT NULL COMMENT 'FK to releases.id',
  `audioid` int(10) unsigned NOT NULL,
  `audioformat` varchar(50) DEFAULT NULL,
  `audiomode` varchar(50) DEFAULT NULL,
  `audiobitratemode` varchar(50) DEFAULT NULL,
  `audiobitrate` varchar(10) DEFAULT NULL,
  `audiochannels` varchar(25) DEFAULT NULL,
  `audiosamplerate` varchar(25) DEFAULT NULL,
  `audiolibrary` varchar(50) DEFAULT NULL,
  `audiolanguage` varchar(50) DEFAULT NULL,
  `audiotitle` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ix_releaseaudio_releaseid_audioid` (`releases_id`,`audioid`),
  CONSTRAINT `FK_ad_releases` FOREIGN KEY (`releases_id`) REFERENCES `releases` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `binaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `binaries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `binaryhash` binary(16) NOT NULL,
  `name` varchar(1000) NOT NULL DEFAULT '',
  `collections_id` int(10) unsigned NOT NULL DEFAULT 0,
  `filenumber` int(10) unsigned NOT NULL DEFAULT 0,
  `totalparts` int(10) unsigned NOT NULL DEFAULT 0,
  `currentparts` int(10) unsigned NOT NULL DEFAULT 0,
  `partcheck` tinyint(1) NOT NULL DEFAULT 0,
  `partsize` bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_binaries_collection_hash` (`collections_id`,`binaryhash`),
  KEY `ix_binaries_collection` (`collections_id`),
  KEY `ix_binaries_partcheck` (`partcheck`),
  KEY `ix_binaries_collection_filenumber` (`collections_id`,`filenumber`),
  CONSTRAINT `FK_Collections` FOREIGN KEY (`collections_id`) REFERENCES `collections` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `binaryblacklist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `binaryblacklist` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `groupname` varchar(255) DEFAULT NULL,
  `regex` varchar(2000) NOT NULL,
  `msgcol` int(10) unsigned NOT NULL DEFAULT 1,
  `optype` int(10) unsigned NOT NULL DEFAULT 1,
  `status` int(10) unsigned NOT NULL DEFAULT 1,
  `description` varchar(1000) DEFAULT NULL,
  `last_activity` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_binaryblacklist_groupname` (`groupname`),
  KEY `ix_binaryblacklist_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bookinfo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bookinfo` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `author` varchar(255) NOT NULL,
  `asin` varchar(128) DEFAULT NULL,
  `isbn` varchar(128) DEFAULT NULL,
  `ean` varchar(128) DEFAULT NULL,
  `url` varchar(1000) DEFAULT NULL,
  `salesrank` int(10) unsigned DEFAULT NULL,
  `publisher` varchar(255) DEFAULT NULL,
  `publishdate` datetime DEFAULT NULL,
  `pages` varchar(128) DEFAULT NULL,
  `overview` varchar(3000) DEFAULT NULL,
  `genre` varchar(255) NOT NULL,
  `cover` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ix_bookinfo_asin` (`asin`),
  KEY `ix_bookinfo_isbn` (`isbn`),
  KEY `ix_bookinfo_ean` (`ean`),
  FULLTEXT KEY `ix_bookinfo_author_title_ft` (`author`,`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` longtext NOT NULL,
  `expiration` int(11) NOT NULL,
  UNIQUE KEY `cache_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `root_categories_id` bigint(20) unsigned DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `description` varchar(255) DEFAULT NULL,
  `disablepreview` tinyint(1) NOT NULL DEFAULT 0,
  `minsizetoformrelease` bigint(20) unsigned NOT NULL DEFAULT 0,
  `maxsizetoformrelease` bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_categories_parentid` (`root_categories_id`),
  KEY `ix_categories_status` (`status`),
  CONSTRAINT `fk_root_categories_id` FOREIGN KEY (`root_categories_id`) REFERENCES `root_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `category_regexes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `category_regexes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `group_regex` varchar(255) NOT NULL DEFAULT '' COMMENT 'This is a regex to match against usenet groups',
  `regex` varchar(5000) NOT NULL DEFAULT '' COMMENT 'Regex used to match a release name to categorize it',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=ON 0=OFF',
  `description` varchar(1000) NOT NULL DEFAULT '' COMMENT 'Optional extra details on this regex',
  `ordinal` int(11) NOT NULL DEFAULT 0 COMMENT 'Order to run the regex in',
  `categories_id` smallint(5) unsigned NOT NULL DEFAULT 10 COMMENT 'Which categories id to put the release in',
  PRIMARY KEY (`id`),
  KEY `ix_category_regexes_group_regex` (`group_regex`),
  KEY `ix_category_regexes_status` (`status`),
  KEY `ix_category_regexes_ordinal` (`ordinal`),
  KEY `ix_category_regexes_categories_id` (`categories_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cbp_optimization_checkpoints`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cbp_optimization_checkpoints` (
  `step_name` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `completed_at` datetime NOT NULL,
  PRIMARY KEY (`step_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `collection_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `collection_groups` (
  `collections_id` int(10) unsigned NOT NULL,
  `group_name` varchar(255) NOT NULL,
  PRIMARY KEY (`collections_id`,`group_name`),
  CONSTRAINT `fk_collection_groups_collection` FOREIGN KEY (`collections_id`) REFERENCES `collections` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `collection_regexes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `collection_regexes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `group_regex` varchar(255) NOT NULL DEFAULT '' COMMENT 'This is a regex to match against usenet groups',
  `regex` varchar(5000) NOT NULL DEFAULT '' COMMENT 'Regex used for collection grouping',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=ON 0=OFF',
  `description` varchar(1000) NOT NULL COMMENT 'Optional extra details on this regex',
  `ordinal` int(11) NOT NULL DEFAULT 0 COMMENT 'Order to run the regex in',
  PRIMARY KEY (`id`),
  KEY `ix_collection_regexes_group_regex` (`group_regex`),
  KEY `ix_collection_regexes_status` (`status`),
  KEY `ix_collection_regexes_ordinal` (`ordinal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `collections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `collections` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `subject` varchar(255) NOT NULL DEFAULT '',
  `fromname` varchar(255) NOT NULL DEFAULT '',
  `date` datetime DEFAULT NULL,
  `xref` varchar(2000) NOT NULL DEFAULT '',
  `totalfiles` int(10) unsigned NOT NULL DEFAULT 0,
  `groups_id` int(10) unsigned NOT NULL DEFAULT 0,
  `collectionhash` binary(20) NOT NULL,
  `collection_regexes_id` int(11) NOT NULL DEFAULT 0 COMMENT 'FK to collection_regexes.id',
  `dateadded` datetime DEFAULT NULL,
  `last_seen_at` datetime DEFAULT NULL,
  `added` timestamp NOT NULL DEFAULT current_timestamp(),
  `filecheck` tinyint(1) NOT NULL DEFAULT 0,
  `filesize` bigint(20) unsigned NOT NULL DEFAULT 0,
  `releases_id` int(11) DEFAULT NULL,
  `release_creation_claimed_at` timestamp NULL DEFAULT NULL,
  `release_creation_claim_token` varchar(64) DEFAULT NULL,
  `release_creation_failures` smallint(5) unsigned NOT NULL DEFAULT 0,
  `release_creation_failed_at` timestamp NULL DEFAULT NULL,
  `release_creation_error` varchar(512) DEFAULT NULL,
  `noise` char(32) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ix_collection_collectionhash` (`collectionhash`),
  KEY `fromname` (`fromname`),
  KEY `date` (`date`),
  KEY `groups_id` (`groups_id`),
  KEY `ix_collection_dateadded` (`dateadded`),
  KEY `ix_collection_filecheck` (`filecheck`),
  KEY `ix_collection_releaseid` (`releases_id`),
  KEY `collections_groups_added_idx` (`groups_id`,`added`),
  KEY `collections_dateadded_idx` (`dateadded`),
  KEY `ix_collections_filecheck_filesize_groups` (`filecheck`,`filesize`,`groups_id`),
  KEY `ix_collections_release_creation_claim_queue` (`filecheck`,`groups_id`,`release_creation_claimed_at`,`id`),
  KEY `ix_collections_group_filecheck_seen_id` (`groups_id`,`filecheck`,`last_seen_at`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `consoleinfo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `consoleinfo` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `asin` varchar(128) DEFAULT NULL,
  `url` varchar(1000) DEFAULT NULL,
  `salesrank` int(10) unsigned DEFAULT NULL,
  `platform` varchar(255) DEFAULT NULL,
  `publisher` varchar(255) DEFAULT NULL,
  `genres_id` int(11) DEFAULT NULL,
  `esrb` varchar(255) DEFAULT NULL,
  `releasedate` datetime DEFAULT NULL,
  `review` varchar(3000) DEFAULT NULL,
  `cover` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ix_consoleinfo_asin` (`asin`),
  KEY `ix_consoleinfo_genres_id` (`genres_id`),
  FULLTEXT KEY `ix_consoleinfo_title_platform_ft` (`title`,`platform`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `content`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `content` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `url` varchar(2000) DEFAULT NULL,
  `body` mediumtext DEFAULT NULL,
  `metadescription` varchar(1000) NOT NULL,
  `metakeywords` varchar(1000) NOT NULL,
  `contenttype` int(11) NOT NULL,
  `status` int(11) NOT NULL,
  `ordinal` int(11) DEFAULT NULL,
  `role` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_status_contenttype_role` (`status`,`contenttype`,`role`),
  KEY `ix_content_type_ordinal` (`contenttype`,`ordinal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `countries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `countries` (
  `iso_3166_2` char(2) NOT NULL,
  `name` varchar(255) NOT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`iso_3166_2`),
  KEY `countries_name_index` (`name`),
  KEY `countries_full_name_index` (`full_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dnzb_failures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dnzb_failures` (
  `release_id` int(10) unsigned NOT NULL,
  `users_id` int(10) unsigned NOT NULL,
  `failed` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`release_id`,`users_id`),
  KEY `FK_users_df` (`users_id`),
  CONSTRAINT `FK_df_releases` FOREIGN KEY (`release_id`) REFERENCES `releases` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `FK_users_df` FOREIGN KEY (`users_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `download_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `download_stats` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `searchname` varchar(255) DEFAULT NULL,
  `grabs` int(11) NOT NULL DEFAULT 0,
  `guid` varchar(255) DEFAULT NULL,
  `adddate` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_download_stats_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `connection` mediumtext NOT NULL,
  `queue` mediumtext NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `uuid` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `firewall`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `firewall` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(39) NOT NULL,
  `whitelisted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `firewall_ip_address_unique` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `forum_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `forum_categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `accepts_threads` tinyint(1) NOT NULL DEFAULT 0,
  `newest_thread_id` int(10) unsigned DEFAULT NULL,
  `latest_active_thread_id` int(10) unsigned DEFAULT NULL,
  `thread_count` int(11) NOT NULL DEFAULT 0,
  `post_count` int(11) NOT NULL DEFAULT 0,
  `is_private` tinyint(1) NOT NULL DEFAULT 0,
  `thread_approval_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `post_approval_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `_lft` int(10) unsigned NOT NULL DEFAULT 0,
  `_rgt` int(10) unsigned NOT NULL DEFAULT 0,
  `parent_id` int(10) unsigned DEFAULT NULL,
  `color_light_mode` varchar(255) DEFAULT NULL,
  `color_dark_mode` varchar(255) DEFAULT NULL,
  `depth` smallint(6) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `forum_categories__lft__rgt_parent_id_index` (`_lft`,`_rgt`,`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `forum_posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `forum_posts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `thread_id` int(10) unsigned NOT NULL,
  `author_id` bigint(20) unsigned NOT NULL,
  `content` mediumtext NOT NULL,
  `post_id` int(10) unsigned DEFAULT NULL,
  `sequence` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `forum_posts_thread_id_index` (`thread_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `forum_threads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `forum_threads` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int(10) unsigned NOT NULL,
  `author_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `pinned` tinyint(1) DEFAULT 0,
  `locked` tinyint(1) DEFAULT 0,
  `first_post_id` int(10) unsigned DEFAULT NULL,
  `last_post_id` int(10) unsigned DEFAULT NULL,
  `reply_count` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `forum_threads_category_id_index` (`category_id`),
  KEY `ix_forum_threads_author_id` (`author_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `forum_threads_read`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `forum_threads_read` (
  `thread_id` int(10) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  KEY `ix_forum_threads_read_user_thread` (`user_id`,`thread_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `forumpost`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `forumpost` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `forumid` int(11) NOT NULL DEFAULT 1,
  `parentid` int(11) NOT NULL DEFAULT 0,
  `users_id` int(10) unsigned NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` mediumtext NOT NULL,
  `locked` tinyint(1) NOT NULL DEFAULT 0,
  `sticky` tinyint(1) NOT NULL DEFAULT 0,
  `replies` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parentid` (`parentid`),
  KEY `userid` (`users_id`),
  CONSTRAINT `FK_users_fp` FOREIGN KEY (`users_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gamesinfo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gamesinfo` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `asin` varchar(128) DEFAULT NULL,
  `url` varchar(1000) DEFAULT NULL,
  `publisher` varchar(255) DEFAULT NULL,
  `genres_id` int(11) DEFAULT NULL,
  `esrb` varchar(255) DEFAULT NULL,
  `releasedate` datetime DEFAULT NULL,
  `review` varchar(3000) DEFAULT NULL,
  `cover` tinyint(1) NOT NULL DEFAULT 0,
  `backdrop` tinyint(1) NOT NULL DEFAULT 0,
  `trailer` varchar(1000) NOT NULL DEFAULT '',
  `classused` varchar(10) NOT NULL DEFAULT 'steam',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ix_gamesinfo_asin` (`asin`),
  KEY `ix_gamesinfo_genres_id` (`genres_id`),
  FULLTEXT KEY `ix_title_ft` (`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gdpr_audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gdpr_audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `gdpr_request_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `actor_id` bigint(20) unsigned DEFAULT NULL,
  `event` varchar(64) NOT NULL,
  `description` text NOT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_gdpr_audit_request_created` (`gdpr_request_id`,`created_at`),
  KEY `ix_gdpr_audit_user_created` (`user_id`,`created_at`),
  KEY `ix_gdpr_audit_event_created` (`event`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gdpr_consents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gdpr_consents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `consent_type` varchar(64) NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'granted',
  `policy_version` varchar(64) DEFAULT NULL,
  `consented_at` timestamp NULL DEFAULT NULL,
  `withdrawn_at` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent_hash` varchar(64) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_gdpr_consents_user_type` (`user_id`,`consent_type`),
  KEY `ix_gdpr_consents_type_status` (`consent_type`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gdpr_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gdpr_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `requester_username` varchar(255) DEFAULT NULL,
  `requester_email` varchar(255) DEFAULT NULL,
  `type` varchar(32) NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'pending',
  `request_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`request_payload`)),
  `response_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`response_payload`)),
  `notes` text DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `export_disk` varchar(255) DEFAULT NULL,
  `export_path` varchar(255) DEFAULT NULL,
  `export_expires_at` timestamp NULL DEFAULT NULL,
  `processed_by` bigint(20) unsigned DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_gdpr_requests_user_type_status` (`user_id`,`type`,`status`),
  KEY `ix_gdpr_requests_status_created` (`status`,`created_at`),
  KEY `ix_gdpr_requests_type_created` (`type`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `genres`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `genres` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `type` int(11) DEFAULT NULL,
  `disabled` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_genres_type_disabled` (`type`,`disabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `grab_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `grab_stats` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `grabs` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_grab_stats_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `invitations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `invitations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `token` varchar(64) NOT NULL,
  `email` varchar(255) NOT NULL,
  `invited_by` bigint(20) unsigned NOT NULL,
  `expires_at` timestamp NOT NULL,
  `used_at` timestamp NULL DEFAULT NULL,
  `used_by` bigint(20) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `metadata` longtext DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invitations_token_unique` (`token`),
  KEY `invitations_invited_by_index` (`invited_by`),
  KEY `invitations_used_by_index` (`used_by`),
  KEY `invitations_token_is_active_index` (`token`,`is_active`),
  KEY `invitations_email_is_active_index` (`email`,`is_active`),
  KEY `invitations_expires_at_index` (`expires_at`),
  KEY `ix_invitations_active_expires` (`is_active`,`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `logging`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `logging` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `time` datetime DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `host` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `media_infos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `media_infos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `releases_id` bigint(20) unsigned NOT NULL,
  `movie_name` varchar(255) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `unique_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_media_infos_unique_id` (`unique_id`),
  KEY `ix_media_infos_releases_id` (`releases_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `missed_parts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `missed_parts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `numberid` bigint(20) unsigned NOT NULL,
  `groups_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'FK to groups.id',
  `attempts` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ix_missed_parts_numberid_groupsid` (`numberid`,`groups_id`),
  KEY `ix_missed_parts_groupid_attempts` (`groups_id`,`attempts`),
  KEY `ix_missed_parts_numberid_groupsid_attempts` (`numberid`,`groups_id`,`attempts`),
  KEY `ix_missed_parts_attempts` (`attempts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` int(10) unsigned NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_type_model_id_index` (`model_type`,`model_id`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_roles` (
  `role_id` int(10) unsigned NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_type_model_id_index` (`model_type`,`model_id`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `movieinfo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `movieinfo` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `imdbid` varchar(100) NOT NULL,
  `tmdbid` int(10) unsigned NOT NULL DEFAULT 0,
  `traktid` int(10) unsigned NOT NULL DEFAULT 0,
  `title` varchar(255) NOT NULL DEFAULT '',
  `tagline` varchar(1024) NOT NULL DEFAULT '',
  `rating` varchar(4) NOT NULL DEFAULT '',
  `rtrating` varchar(10) NOT NULL DEFAULT '' COMMENT 'RottenTomatoes rating score',
  `plot` varchar(1024) NOT NULL DEFAULT '',
  `year` varchar(4) NOT NULL DEFAULT '',
  `genre` varchar(64) NOT NULL DEFAULT '',
  `type` varchar(32) NOT NULL DEFAULT '',
  `director` varchar(64) NOT NULL DEFAULT '',
  `actors` varchar(2000) NOT NULL DEFAULT '',
  `language` varchar(64) NOT NULL DEFAULT '',
  `cover` tinyint(1) NOT NULL DEFAULT 0,
  `backdrop` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `trailer` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ix_movieinfo_imdbid` (`imdbid`),
  KEY `ix_movieinfo_title` (`title`),
  KEY `ix_movieinfo_tmdbid` (`tmdbid`),
  KEY `ix_movieinfo_traktid` (`traktid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `musicinfo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `musicinfo` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `asin` varchar(128) DEFAULT NULL,
  `url` varchar(1000) DEFAULT NULL,
  `salesrank` int(10) unsigned DEFAULT NULL,
  `artist` varchar(255) DEFAULT NULL,
  `publisher` varchar(255) DEFAULT NULL,
  `releasedate` datetime DEFAULT NULL,
  `review` varchar(3000) DEFAULT NULL,
  `year` varchar(4) NOT NULL,
  `genres_id` int(11) DEFAULT NULL,
  `tracks` varchar(3000) DEFAULT NULL,
  `cover` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ix_musicinfo_asin` (`asin`),
  KEY `ix_musicinfo_genres_id` (`genres_id`),
  FULLTEXT KEY `ix_musicinfo_artist_title_ft` (`artist`,`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `nzb_backup_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nzb_backup_runs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `started_at` timestamp NOT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `status` enum('running','completed','failed','cancelled') NOT NULL DEFAULT 'running',
  `files_found` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Files checked by rclone',
  `files_uploaded` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Files transferred',
  `files_skipped` int(10) unsigned NOT NULL DEFAULT 0,
  `files_failed` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Errors during transfer',
  `bytes_uploaded` bigint(20) unsigned NOT NULL DEFAULT 0,
  `error_message` text DEFAULT NULL,
  `stats` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Rclone statistics' CHECK (json_valid(`stats`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `nzb_backup_runs_started_at_index` (`started_at`),
  KEY `nzb_backup_runs_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `par_hashes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `par_hashes` (
  `releases_id` int(10) unsigned NOT NULL COMMENT 'FK to releases.id',
  `hash` varchar(32) NOT NULL COMMENT 'hash_16k block of par2',
  PRIMARY KEY (`releases_id`,`hash`),
  KEY `ix_par_hashes_hash_releases_id` (`hash`,`releases_id`),
  CONSTRAINT `FK_ph_releases` FOREIGN KEY (`releases_id`) REFERENCES `releases` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `parts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `parts` (
  `binaries_id` bigint(20) unsigned NOT NULL,
  `messageid` varchar(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
  `number` bigint(20) unsigned NOT NULL DEFAULT 0,
  `partnumber` int(10) unsigned NOT NULL DEFAULT 0,
  `size` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`binaries_id`,`partnumber`),
  KEY `ix_parts_number` (`number`),
  CONSTRAINT `FK_binaries` FOREIGN KEY (`binaries_id`) REFERENCES `binaries` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `passkeys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `passkeys` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `authenticatable_id` int(10) unsigned NOT NULL,
  `name` text NOT NULL,
  `credential_id` text NOT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`data`)),
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `passkeys_authenticatable_fk` (`authenticatable_id`),
  CONSTRAINT `passkeys_authenticatable_fk` FOREIGN KEY (`authenticatable_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_securities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_securities` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `google2fa_enable` tinyint(1) NOT NULL DEFAULT 0,
  `google2fa_secret` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_password_securities_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `item_description` varchar(255) NOT NULL,
  `order_id` varchar(255) NOT NULL,
  `payment_id` varchar(255) NOT NULL,
  `payment_status` varchar(255) NOT NULL,
  `invoice_status` varchar(255) DEFAULT 'Pending',
  `invoice_amount` varchar(255) NOT NULL,
  `payment_method` varchar(255) NOT NULL,
  `payment_value` varchar(255) NOT NULL,
  `webhook_id` varchar(255) NOT NULL,
  `invoice_id` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `paypal_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `paypal_payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `users_id` int(11) NOT NULL,
  `transaction_id` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_paypal_payments_users_id` (`users_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` mediumtext DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `poster_renames`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `poster_renames` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `title` varchar(500) NOT NULL,
  `poster` varchar(255) NOT NULL,
  `source` varchar(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `poster_title` (`poster`,`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `predb`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `predb` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary key',
  `title` varchar(255) NOT NULL DEFAULT '',
  `nfo` varchar(255) DEFAULT NULL,
  `size` varchar(50) DEFAULT NULL,
  `category` varchar(255) DEFAULT NULL,
  `predate` datetime DEFAULT NULL,
  `source` varchar(50) NOT NULL DEFAULT '',
  `requestid` int(10) unsigned NOT NULL DEFAULT 0,
  `groups_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'FK to groups',
  `nuked` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Is this pre nuked? 0 no 2 yes 1 un nuked 3 mod nuked',
  `nukereason` varchar(255) DEFAULT NULL COMMENT 'If this pre is nuked, what is the reason?',
  `files` varchar(50) DEFAULT NULL COMMENT 'How many files does this pre have ?',
  `filename` varchar(255) NOT NULL DEFAULT '',
  `searched` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ix_predb_title` (`title`),
  KEY `ix_predb_requestid` (`requestid`,`groups_id`),
  KEY `ix_predb_nfo` (`nfo`),
  KEY `ix_predb_predate` (`predate`),
  KEY `ix_predb_source` (`source`),
  KEY `ix_predb_searched` (`searched`),
  KEY `ix_predb_searched_predate_id` (`searched`,`predate`,`id`),
  FULLTEXT KEY `ft_predb_filename` (`filename`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `predb_crcs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `predb_crcs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `predb_id` int(10) unsigned NOT NULL COMMENT 'FK to predb.id',
  `crchash` varchar(255) NOT NULL DEFAULT '' COMMENT 'CRC hash',
  `filesize` bigint(20) NOT NULL DEFAULT 0 COMMENT 'Release file size in bytes',
  `filedate` datetime DEFAULT NULL COMMENT 'The file modified date',
  `osohash` varchar(255) NOT NULL DEFAULT '' COMMENT 'OpenSubtitles hash',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `predb_crcs_crchash_filesize_filedate_index` (`crchash`,`filesize`,`filedate`),
  KEY `predb_crcs_osohash_index` (`osohash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `predb_imports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `predb_imports` (
  `title` varchar(255) NOT NULL DEFAULT '',
  `nfo` varchar(255) DEFAULT NULL,
  `size` varchar(50) DEFAULT NULL,
  `category` varchar(255) DEFAULT NULL,
  `predate` datetime DEFAULT NULL,
  `source` varchar(50) NOT NULL DEFAULT '',
  `requestid` int(10) unsigned NOT NULL DEFAULT 0,
  `groups_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'FK to groups',
  `nuked` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Is this pre nuked? 0 no 2 yes 1 un nuked 3 mod nuked',
  `nukereason` varchar(255) DEFAULT NULL COMMENT 'If this pre is nuked, what is the reason?',
  `files` varchar(50) DEFAULT NULL COMMENT 'How many files does this pre have ?',
  `filename` varchar(255) NOT NULL DEFAULT '',
  `searched` tinyint(1) NOT NULL DEFAULT 0,
  `groupname` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pulse_aggregates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pulse_aggregates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `bucket` int(10) unsigned NOT NULL,
  `period` mediumint(8) unsigned NOT NULL,
  `type` varchar(255) NOT NULL,
  `key` mediumtext NOT NULL,
  `key_hash` binary(16) GENERATED ALWAYS AS (unhex(md5(`key`))) VIRTUAL,
  `aggregate` varchar(255) NOT NULL,
  `value` decimal(20,2) NOT NULL,
  `count` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pulse_aggregates_bucket_period_type_aggregate_key_hash_unique` (`bucket`,`period`,`type`,`aggregate`,`key_hash`),
  KEY `pulse_aggregates_period_bucket_index` (`period`,`bucket`),
  KEY `pulse_aggregates_type_index` (`type`),
  KEY `pulse_aggregates_period_type_aggregate_bucket_index` (`period`,`type`,`aggregate`,`bucket`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pulse_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pulse_entries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `timestamp` int(10) unsigned NOT NULL,
  `type` varchar(255) NOT NULL,
  `key` mediumtext NOT NULL,
  `key_hash` binary(16) GENERATED ALWAYS AS (unhex(md5(`key`))) VIRTUAL,
  `value` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pulse_entries_timestamp_index` (`timestamp`),
  KEY `pulse_entries_type_index` (`type`),
  KEY `pulse_entries_key_hash_index` (`key_hash`),
  KEY `pulse_entries_timestamp_type_key_hash_value_index` (`timestamp`,`type`,`key_hash`,`value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pulse_values`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pulse_values` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `timestamp` int(10) unsigned NOT NULL,
  `type` varchar(255) NOT NULL,
  `key` mediumtext NOT NULL,
  `key_hash` binary(16) GENERATED ALWAYS AS (unhex(md5(`key`))) VIRTUAL,
  `value` mediumtext NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pulse_values_type_key_hash_unique` (`type`,`key_hash`),
  KEY `pulse_values_timestamp_index` (`timestamp`),
  KEY `pulse_values_type_index` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `registration_periods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `registration_periods` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `registration_periods_starts_at_index` (`starts_at`),
  KEY `registration_periods_ends_at_index` (`ends_at`),
  KEY `registration_periods_is_enabled_index` (`is_enabled`),
  KEY `registration_periods_created_by_index` (`created_by`),
  KEY `registration_periods_updated_by_index` (`updated_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `registration_status_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `registration_status_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `action` varchar(100) NOT NULL,
  `old_status` tinyint(3) unsigned DEFAULT NULL,
  `new_status` tinyint(3) unsigned DEFAULT NULL,
  `registration_period_id` bigint(20) unsigned DEFAULT NULL,
  `changed_by` bigint(20) unsigned DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `registration_status_history_action_index` (`action`),
  KEY `registration_status_history_registration_period_id_index` (`registration_period_id`),
  KEY `registration_status_history_changed_by_index` (`changed_by`),
  KEY `registration_status_history_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `release_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `release_comments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `releases_id` int(10) unsigned NOT NULL COMMENT 'FK to releases.id',
  `text` varchar(2000) NOT NULL DEFAULT '',
  `isvisible` tinyint(1) NOT NULL DEFAULT 1,
  `username` varchar(255) NOT NULL DEFAULT '',
  `users_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `host` varchar(15) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_releasecomment_releases_id` (`releases_id`),
  KEY `ix_releasecomment_userid` (`users_id`),
  CONSTRAINT `FK_rc_releases` FOREIGN KEY (`releases_id`) REFERENCES `releases` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `release_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `release_files` (
  `releases_id` int(10) unsigned NOT NULL COMMENT 'FK to releases.id',
  `name` varchar(255) NOT NULL,
  `size` bigint(20) unsigned NOT NULL DEFAULT 0,
  `crc32` varchar(255) NOT NULL DEFAULT '',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `passworded` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`releases_id`,`name`),
  KEY `ix_release_files_crc32_releases_id` (`crc32`,`releases_id`),
  CONSTRAINT `FK_rf_releases` FOREIGN KEY (`releases_id`) REFERENCES `releases` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `release_informs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `release_informs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `relOName` varchar(255) NOT NULL,
  `relPName` varchar(255) NOT NULL,
  `api_token` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `release_naming_regexes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `release_naming_regexes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `group_regex` varchar(255) NOT NULL DEFAULT '' COMMENT 'This is a regex to match against usenet groups',
  `regex` varchar(5000) NOT NULL DEFAULT '' COMMENT 'Regex used for extracting name from subject',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=ON 0=OFF',
  `description` varchar(1000) NOT NULL DEFAULT '' COMMENT 'Optional extra details on this regex',
  `ordinal` int(11) NOT NULL DEFAULT 0 COMMENT 'Order to run the regex in',
  PRIMARY KEY (`id`),
  KEY `ix_release_naming_regexes_group_regex` (`group_regex`),
  KEY `ix_release_naming_regexes_status` (`status`),
  KEY `ix_release_naming_regexes_ordinal` (`ordinal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `release_nfos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `release_nfos` (
  `releases_id` int(10) unsigned NOT NULL COMMENT 'FK to releases.id',
  `nfo` blob DEFAULT NULL,
  PRIMARY KEY (`releases_id`),
  CONSTRAINT `FK_rn_releases` FOREIGN KEY (`releases_id`) REFERENCES `releases` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `release_nzb_creation_failures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `release_nzb_creation_failures` (
  `releases_id` int(10) unsigned NOT NULL,
  `attempts` smallint(5) unsigned NOT NULL DEFAULT 0,
  `last_error` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`releases_id`),
  CONSTRAINT `FK_rncf_releases` FOREIGN KEY (`releases_id`) REFERENCES `releases` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `release_nzb_passwords`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `release_nzb_passwords` (
  `releases_id` int(10) unsigned NOT NULL,
  `password` varchar(255) NOT NULL,
  PRIMARY KEY (`releases_id`),
  CONSTRAINT `FK_rnp_releases` FOREIGN KEY (`releases_id`) REFERENCES `releases` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `release_regexes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `release_regexes` (
  `releases_id` int(10) unsigned NOT NULL DEFAULT 0,
  `collection_regex_id` int(11) NOT NULL DEFAULT 0,
  `naming_regex_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`releases_id`,`collection_regex_id`,`naming_regex_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `release_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `release_reports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `releases_id` int(10) unsigned NOT NULL,
  `users_id` int(10) unsigned NOT NULL,
  `reason` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `response` text DEFAULT NULL,
  `status` enum('pending','reviewed','resolved','dismissed') NOT NULL DEFAULT 'pending',
  `reviewed_by` int(10) unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `responded_by` int(10) unsigned DEFAULT NULL,
  `responded_at` timestamp NULL DEFAULT NULL,
  `response_is_public` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `release_reports_users_id_foreign` (`users_id`),
  KEY `release_reports_reviewed_by_foreign` (`reviewed_by`),
  KEY `release_reports_releases_id_status_index` (`releases_id`,`status`),
  KEY `release_reports_status_created_at_index` (`status`,`created_at`),
  KEY `release_reports_responded_by_foreign` (`responded_by`),
  KEY `release_reports_response_lookup_idx` (`releases_id`,`response_is_public`,`responded_at`),
  KEY `ix_release_reports_status_created_admin` (`status`,`created_at`),
  CONSTRAINT `release_reports_releases_id_foreign` FOREIGN KEY (`releases_id`) REFERENCES `releases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `release_reports_responded_by_foreign` FOREIGN KEY (`responded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `release_reports_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `release_reports_users_id_foreign` FOREIGN KEY (`users_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `release_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `release_stats` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `category` varchar(255) NOT NULL,
  `count` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `release_stats_category_index` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `release_subtitles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `release_subtitles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `releases_id` int(10) unsigned NOT NULL COMMENT 'FK to releases.id',
  `subsid` int(10) unsigned NOT NULL,
  `subslanguage` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ix_releasesubs_releases_id_subsid` (`releases_id`,`subsid`),
  CONSTRAINT `FK_rs_releases` FOREIGN KEY (`releases_id`) REFERENCES `releases` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `release_unique`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `release_unique` (
  `releases_id` int(10) unsigned NOT NULL COMMENT 'FK to releases.id.',
  `uniqueid` varchar(255) NOT NULL COMMENT 'Unique_ID from mediainfo.',
  PRIMARY KEY (`releases_id`,`uniqueid`),
  CONSTRAINT `FK_ru_releases` FOREIGN KEY (`releases_id`) REFERENCES `releases` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `releases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `releases` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `searchname` varchar(255) NOT NULL DEFAULT '',
  `totalpart` int(11) DEFAULT 0,
  `groups_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'FK to groups.id',
  `size` bigint(20) unsigned NOT NULL DEFAULT 0,
  `postdate` datetime DEFAULT NULL,
  `adddate` datetime DEFAULT NULL,
  `guid` char(40) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `leftguid` char(1) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL COMMENT 'The first letter of the release guid',
  `fromname` varchar(255) DEFAULT NULL,
  `completion` double NOT NULL DEFAULT 0,
  `categories_id` int(11) NOT NULL DEFAULT 10,
  `videos_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'FK to videos.id of the parent series.',
  `tv_episodes_id` int(11) NOT NULL DEFAULT 0 COMMENT 'FK to tv_episodes.id for the episode.',
  `imdbid` varchar(100) DEFAULT NULL,
  `musicinfo_id` int(11) DEFAULT NULL COMMENT 'FK to musicinfo.id',
  `consoleinfo_id` int(11) DEFAULT NULL COMMENT 'FK to consoleinfo.id',
  `gamesinfo_id` int(11) NOT NULL DEFAULT 0,
  `bookinfo_id` int(11) DEFAULT NULL COMMENT 'FK to bookinfo.id',
  `anidbid` int(11) DEFAULT NULL COMMENT 'FK to anidb_titles.anidbid',
  `movieinfo_id` int(11) DEFAULT NULL COMMENT 'FK to movieinfo.id',
  `predb_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'FK to predb.id',
  `grabs` int(10) unsigned NOT NULL DEFAULT 0,
  `comments` int(11) NOT NULL DEFAULT 0,
  `passwordstatus` smallint(6) NOT NULL DEFAULT -1,
  `rarinnerfilecount` int(11) NOT NULL DEFAULT 0,
  `haspreview` tinyint(1) NOT NULL DEFAULT 0,
  `nfostatus` tinyint(1) NOT NULL DEFAULT 0,
  `jpgstatus` tinyint(1) NOT NULL DEFAULT 0,
  `videostatus` tinyint(1) NOT NULL DEFAULT 0,
  `nzbstatus` tinyint(1) NOT NULL DEFAULT 0,
  `nzb_creation_claimed_at` timestamp NULL DEFAULT NULL,
  `nzb_creation_claim_token` char(32) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `iscategorized` tinyint(1) NOT NULL DEFAULT 0,
  `isrenamed` tinyint(1) NOT NULL DEFAULT 0,
  `proc_pp` tinyint(1) NOT NULL DEFAULT 0,
  `proc_par2` tinyint(1) NOT NULL DEFAULT 0,
  `proc_nfo` tinyint(1) NOT NULL DEFAULT 0,
  `proc_files` tinyint(1) NOT NULL DEFAULT 0,
  `proc_uid` tinyint(1) NOT NULL DEFAULT 0,
  `proc_srr` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Has the release been srr\nprocessed',
  `proc_hash16k` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Has the release been hash16k\nprocessed',
  `proc_crc32` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Has the release been crc32 processed',
  `pp_timeout_count` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Number of times this release timed out during additional post-processing',
  `additional_pp_claimed_at` timestamp NULL DEFAULT NULL,
  `additional_pp_claim_token` char(32) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_releases_guid` (`guid`),
  KEY `ix_releases_groupsid` (`groups_id`,`passwordstatus`),
  KEY `ix_releases_leftguid` (`leftguid`,`predb_id`),
  KEY `ix_releases_musicinfo_id` (`musicinfo_id`,`passwordstatus`),
  KEY `ix_releases_nfostatus` (`nfostatus`,`size`),
  KEY `ix_releases_name` (`name`),
  KEY `ix_releases_tv_episodes_id` (`tv_episodes_id`),
  KEY `ix_releases_consoleinfo_id` (`consoleinfo_id`),
  KEY `ix_releases_gamesinfo_id` (`gamesinfo_id`),
  KEY `ix_releases_bookinfo_id` (`bookinfo_id`),
  KEY `ix_releases_anidbid` (`anidbid`),
  KEY `ix_releases_password_categories_postdate` (`passwordstatus`,`categories_id`,`postdate` DESC),
  KEY `ix_releases_adddate` (`adddate`,`categories_id`),
  KEY `ix_releases_grabs` (`grabs`,`categories_id`,`postdate`),
  KEY `ix_releases_movieinfo_cat` (`movieinfo_id`,`categories_id`,`passwordstatus`,`postdate`),
  KEY `ix_releases_videos_categories` (`videos_id`,`categories_id`),
  KEY `ix_releases_imdbid_password_cat_postdate` (`imdbid`,`passwordstatus`,`categories_id`,`postdate` DESC),
  KEY `ix_releases_searchname` (`searchname`),
  KEY `ix_releases_categories_postdate_admin` (`categories_id`,`postdate`),
  KEY `ix_releases_postdate_admin` (`postdate`),
  KEY `ix_releases_adddate_id` (`adddate`,`id`),
  KEY `ix_releases_predb_id` (`predb_id`),
  KEY `ix_releases_size` (`size`),
  KEY `ix_releases_add_pp_claim_queue` (`passwordstatus`,`haspreview`,`nzbstatus`,`leftguid`,`postdate` DESC,`id`,`additional_pp_claimed_at`,`size`),
  KEY `ix_releases_nzb_creation_group_queue` (`nzbstatus`,`groups_id`,`postdate` DESC,`id`,`nzb_creation_claimed_at`),
  KEY `ix_releases_nzb_creation_global_queue` (`nzbstatus`,`postdate` DESC,`id`,`nzb_creation_claimed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `releases_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `releases_groups` (
  `releases_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'FK to releases.id',
  `groups_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'FK to groups.id',
  PRIMARY KEY (`releases_id`,`groups_id`),
  CONSTRAINT `FK_rg_releases` FOREIGN KEY (`releases_id`) REFERENCES `releases` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role_expiration_emails`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_expiration_emails` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `users_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `day` tinyint(1) NOT NULL DEFAULT 0,
  `week` tinyint(1) NOT NULL DEFAULT 0,
  `month` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_expiration_emails_users_id_unique` (`users_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` int(10) unsigned NOT NULL,
  `role_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role_promotion_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_promotion_stats` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `role_promotion_id` bigint(20) unsigned NOT NULL,
  `role_id` int(10) unsigned NOT NULL,
  `days_added` int(11) NOT NULL COMMENT 'Number of days added to role expiry',
  `previous_expiry_date` datetime DEFAULT NULL COMMENT 'Previous role expiry date before promotion',
  `new_expiry_date` datetime DEFAULT NULL COMMENT 'New role expiry date after promotion',
  `applied_at` timestamp NOT NULL COMMENT 'When the promotion was applied',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `role_promotion_stats_user_id_index` (`user_id`),
  KEY `role_promotion_stats_role_promotion_id_index` (`role_promotion_id`),
  KEY `role_promotion_stats_role_id_index` (`role_id`),
  KEY `role_promotion_stats_applied_at_index` (`applied_at`),
  CONSTRAINT `role_promotion_stats_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_promotion_stats_role_promotion_id_foreign` FOREIGN KEY (`role_promotion_id`) REFERENCES `role_promotions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_promotion_stats_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role_promotions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_promotions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `applicable_roles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'JSON array of role IDs this promotion applies to' CHECK (json_valid(`applicable_roles`)),
  `additional_days` int(11) NOT NULL DEFAULT 0 COMMENT 'Additional days added to role expiry',
  `start_date` date DEFAULT NULL COMMENT 'Promotion start date',
  `end_date` date DEFAULT NULL COMMENT 'Promotion end date',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_role_promotions_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_stats` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `role` varchar(255) DEFAULT NULL,
  `users` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_role_stats_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `apirequests` int(10) unsigned NOT NULL,
  `rate_limit` int(11) NOT NULL DEFAULT 60,
  `downloadrequests` int(10) unsigned NOT NULL,
  `defaultinvites` int(10) unsigned NOT NULL,
  `isdefault` tinyint(1) NOT NULL DEFAULT 0,
  `donation` int(11) NOT NULL DEFAULT 0,
  `addyears` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `root_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `root_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `disablepreview` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_root_categories_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `search_index_failures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `search_index_failures` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `release_id` bigint(20) unsigned NOT NULL,
  `operation` varchar(32) NOT NULL DEFAULT 'upsert',
  `attempts` int(10) unsigned NOT NULL DEFAULT 0,
  `last_error` text DEFAULT NULL,
  `next_attempt_at` timestamp NULL DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `search_index_failures_release_id_unique` (`release_id`),
  KEY `search_index_failures_next_attempt_at_index` (`next_attempt_at`),
  KEY `search_index_failures_resolved_at_index` (`resolved_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `service_incident_service_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `service_incident_service_status` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `service_incident_id` bigint(20) unsigned NOT NULL,
  `service_status_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `svc_incident_svc_pair_unique` (`service_incident_id`,`service_status_id`),
  KEY `service_incident_service_status_service_status_id_index` (`service_status_id`),
  CONSTRAINT `service_incident_service_status_service_incident_id_foreign` FOREIGN KEY (`service_incident_id`) REFERENCES `service_incidents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `service_incident_service_status_service_status_id_foreign` FOREIGN KEY (`service_status_id`) REFERENCES `service_statuses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `service_incidents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `service_incidents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `status` varchar(255) NOT NULL,
  `impact` varchar(255) NOT NULL,
  `started_at` timestamp NOT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `is_auto` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `service_incidents_status_index` (`status`),
  KEY `service_incidents_impact_index` (`impact`),
  KEY `service_incidents_started_at_index` (`started_at`),
  KEY `service_incidents_resolved_at_index` (`resolved_at`),
  KEY `service_incidents_status_started_at_index` (`status`,`started_at`),
  KEY `service_incidents_created_by_index` (`created_by`),
  KEY `service_incidents_is_auto_index` (`is_auto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `service_statuses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `service_statuses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `endpoint_url` varchar(255) DEFAULT NULL,
  `check_type` varchar(255) NOT NULL DEFAULT 'http',
  `probe_identifier` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL,
  `last_checked_at` timestamp NULL DEFAULT NULL,
  `uptime_percentage` decimal(5,2) NOT NULL DEFAULT 100.00,
  `response_time_ms` int(10) unsigned DEFAULT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `service_statuses_slug_unique` (`slug`),
  KEY `service_statuses_status_index` (`status`),
  KEY `service_statuses_is_enabled_sort_order_index` (`is_enabled`,`sort_order`),
  KEY `service_statuses_check_type_index` (`check_type`),
  KEY `service_statuses_probe_identifier_index` (`probe_identifier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `name` varchar(25) NOT NULL DEFAULT '',
  `value` varchar(1000) NOT NULL DEFAULT '',
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `short_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `short_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `first_record` bigint(20) unsigned NOT NULL DEFAULT 0,
  `last_record` bigint(20) unsigned NOT NULL DEFAULT 0,
  `updated` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_shortgroups_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `signup_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `signup_stats` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `month` varchar(255) DEFAULT NULL,
  `sort_date` date DEFAULT NULL,
  `signups` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `signup_stats_sort_date_index` (`sort_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `steam_apps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `steam_apps` (
  `name` varchar(255) NOT NULL DEFAULT '' COMMENT 'Steam application name',
  `appid` int(10) unsigned NOT NULL COMMENT 'Steam application id',
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  KEY `ix_name_appid` (`name`,`appid`),
  FULLTEXT KEY `ix_name_ft` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `system_metrics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_metrics` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `metric_type` varchar(50) NOT NULL,
  `value` decimal(8,2) NOT NULL,
  `recorded_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `system_metrics_metric_type_recorded_at_index` (`metric_type`,`recorded_at`),
  KEY `system_metrics_metric_type_index` (`metric_type`),
  KEY `system_metrics_recorded_at_index` (`recorded_at`),
  KEY `ix_system_metrics_type_recorded` (`metric_type`,`recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `telescope_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `telescope_entries` (
  `sequence` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `batch_id` char(36) NOT NULL,
  `family_hash` varchar(255) DEFAULT NULL,
  `should_display_on_index` tinyint(1) NOT NULL DEFAULT 1,
  `type` varchar(20) NOT NULL,
  `content` longtext NOT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`sequence`),
  UNIQUE KEY `telescope_entries_uuid_unique` (`uuid`),
  KEY `telescope_entries_batch_id_index` (`batch_id`),
  KEY `telescope_entries_type_should_display_on_index_index` (`type`,`should_display_on_index`),
  KEY `telescope_entries_family_hash_index` (`family_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `telescope_entries_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `telescope_entries_tags` (
  `entry_uuid` char(36) NOT NULL,
  `tag` varchar(255) NOT NULL,
  KEY `telescope_entries_tags_entry_uuid_tag_index` (`entry_uuid`,`tag`),
  KEY `telescope_entries_tags_tag_index` (`tag`),
  CONSTRAINT `telescope_entries_tags_entry_uuid_foreign` FOREIGN KEY (`entry_uuid`) REFERENCES `telescope_entries` (`uuid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `telescope_monitoring`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `telescope_monitoring` (
  `tag` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `trusted_devices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `trusted_devices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `expires_at` timestamp NOT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `trusted_devices_token_hash_unique` (`token_hash`),
  KEY `trusted_devices_user_id_expires_at_index` (`user_id`,`expires_at`),
  KEY `trusted_devices_expires_at_index` (`expires_at`),
  CONSTRAINT `trusted_devices_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tv_episodes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tv_episodes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `videos_id` int(10) unsigned NOT NULL COMMENT 'FK to videos.id of the parent series.',
  `series` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT 'Number of series/season.',
  `episode` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT 'Number of episode within series',
  `se_complete` varchar(10) NOT NULL COMMENT 'String version of Series/Episode as taken from release subject (i.e. S02E21+22).',
  `title` varchar(180) NOT NULL COMMENT 'Title of the episode.',
  `firstaired` date DEFAULT NULL COMMENT 'Date of original airing/release.',
  `summary` mediumtext NOT NULL COMMENT 'Description/summary of the episode.',
  PRIMARY KEY (`id`),
  UNIQUE KEY `videos_id` (`videos_id`,`series`,`episode`,`firstaired`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tv_info`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tv_info` (
  `videos_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'FK to video.id',
  `summary` mediumtext NOT NULL COMMENT 'Description/summary of the show.',
  `publisher` varchar(50) NOT NULL COMMENT 'The channel/network of production/release (ABC, BBC, Showtime, etc.).',
  `localzone` varchar(50) NOT NULL DEFAULT '' COMMENT 'The linux tz style identifier',
  `image` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Does the video have a cover image?',
  PRIMARY KEY (`videos_id`),
  KEY `ix_tv_info_image` (`image`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `usenet_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `usenet_groups` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `backfill_target` int(11) NOT NULL DEFAULT 1,
  `first_record` bigint(20) unsigned NOT NULL DEFAULT 0,
  `first_record_postdate` datetime DEFAULT NULL,
  `last_record` bigint(20) unsigned NOT NULL DEFAULT 0,
  `last_record_postdate` datetime DEFAULT NULL,
  `last_updated` datetime DEFAULT NULL,
  `minfilestoformrelease` int(11) DEFAULT NULL,
  `minsizetoformrelease` bigint(20) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 0,
  `backfill` tinyint(1) NOT NULL DEFAULT 0,
  `description` varchar(255) DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ix_groups_name` (`name`),
  KEY `active` (`active`),
  KEY `ix_usenet_groups_active_name_admin` (`active`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_activities` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `username` varchar(255) NOT NULL,
  `activity_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `is_permanent` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_activities_activity_type_created_at_index` (`activity_type`,`created_at`),
  KEY `user_activities_created_at_index` (`created_at`),
  KEY `ix_user_activities_type_permanent` (`activity_type`,`is_permanent`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_activity_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_activity_stats` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `stat_date` date NOT NULL,
  `downloads_count` int(11) NOT NULL DEFAULT 0,
  `api_hits_count` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_activity_stats_stat_date_unique` (`stat_date`),
  KEY `user_activity_stats_stat_date_index` (`stat_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_activity_stats_hourly`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_activity_stats_hourly` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `stat_hour` datetime NOT NULL,
  `downloads_count` int(11) NOT NULL DEFAULT 0,
  `api_hits_count` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_activity_stats_hourly_stat_hour_unique` (`stat_hour`),
  KEY `user_activity_stats_hourly_stat_hour_index` (`stat_hour`),
  KEY `ix_hourly_stats_hour` (`stat_hour`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_downloads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_downloads` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `users_id` int(10) unsigned NOT NULL,
  `hosthash` varchar(50) NOT NULL DEFAULT '',
  `timestamp` datetime NOT NULL,
  `releases_id` int(10) unsigned NOT NULL COMMENT 'FK to releases.id',
  PRIMARY KEY (`id`),
  KEY `userid` (`users_id`),
  KEY `timestamp` (`timestamp`),
  KEY `ix_user_downloads_users_timestamp` (`users_id`,`timestamp`),
  KEY `ix_user_downloads_releases_id` (`releases_id`),
  CONSTRAINT `FK_users_ud` FOREIGN KEY (`users_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_excluded_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_excluded_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `users_id` int(10) unsigned NOT NULL,
  `categories_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_excluded_categories_users_id_categories_id_unique` (`users_id`,`categories_id`),
  KEY `user_excluded_categories_categories_id_foreign` (`categories_id`),
  KEY `user_excluded_categories_users_id_index` (`users_id`),
  CONSTRAINT `user_excluded_categories_categories_id_foreign` FOREIGN KEY (`categories_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_excluded_categories_users_id_foreign` FOREIGN KEY (`users_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_invitations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_invitations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `status` enum('pending','successful','canceled','expired') NOT NULL DEFAULT 'pending',
  `valid_till` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_invitations_code_index` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_movies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_movies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `users_id` int(10) unsigned NOT NULL,
  `imdbid` varchar(100) DEFAULT NULL,
  `categories` varchar(64) DEFAULT NULL COMMENT 'List of categories for user movies',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_usermovies_userid` (`users_id`,`imdbid`),
  CONSTRAINT `FK_users_um` FOREIGN KEY (`users_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `users_id` int(10) unsigned NOT NULL,
  `hosthash` varchar(50) NOT NULL DEFAULT '',
  `request` varchar(255) NOT NULL,
  `timestamp` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `userid` (`users_id`),
  KEY `timestamp` (`timestamp`),
  KEY `ix_user_requests_users_timestamp` (`users_id`,`timestamp`),
  CONSTRAINT `FK_users_urq` FOREIGN KEY (`users_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_role_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_role_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `old_role_id` int(11) DEFAULT NULL,
  `new_role_id` int(11) NOT NULL,
  `old_expiry_date` datetime DEFAULT NULL COMMENT 'Previous role expiry date',
  `new_expiry_date` datetime DEFAULT NULL COMMENT 'New role expiry date',
  `effective_date` datetime NOT NULL COMMENT 'When this role change became active',
  `is_stacked` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Was this role stacked after previous expiry',
  `change_reason` varchar(255) DEFAULT NULL COMMENT 'Reason for role change (upgrade, downgrade, expiry, admin, etc)',
  `changed_by` bigint(20) unsigned DEFAULT NULL COMMENT 'Admin user ID who made the change',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_role_history_user_id_index` (`user_id`),
  KEY `ix_user_role_history_changed_by` (`changed_by`),
  KEY `ix_user_role_history_roles` (`old_role_id`,`new_role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_series`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_series` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `users_id` int(10) unsigned NOT NULL,
  `videos_id` int(11) NOT NULL COMMENT 'FK to videos.id',
  `categories` varchar(64) DEFAULT NULL COMMENT 'List of categories for user tv shows',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_userseries_videos_id` (`users_id`,`videos_id`),
  CONSTRAINT `FK_users_us` FOREIGN KEY (`users_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `firstname` varchar(255) DEFAULT NULL,
  `lastname` varchar(255) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `roles_id` int(11) NOT NULL DEFAULT 1 COMMENT 'FK to roles.id',
  `next_roles_id` int(11) DEFAULT NULL,
  `host` varchar(40) DEFAULT NULL,
  `grabs` int(11) NOT NULL DEFAULT 0,
  `api_token` varchar(64) NOT NULL,
  `resetguid` varchar(50) DEFAULT NULL,
  `lastlogin` datetime DEFAULT NULL,
  `apiaccess` datetime DEFAULT NULL,
  `lastdownload` datetime DEFAULT NULL,
  `invites` int(11) NOT NULL DEFAULT 0,
  `invitedby` int(11) DEFAULT NULL,
  `movieview` int(11) NOT NULL DEFAULT 1,
  `xxxview` int(11) NOT NULL DEFAULT 1,
  `musicview` int(11) NOT NULL DEFAULT 1,
  `consoleview` int(11) NOT NULL DEFAULT 1,
  `bookview` int(11) NOT NULL DEFAULT 1,
  `gameview` int(11) NOT NULL DEFAULT 1,
  `rate_limit` int(11) NOT NULL DEFAULT 60,
  `notes` varchar(255) DEFAULT NULL,
  `theme_preference` varchar(10) NOT NULL DEFAULT 'light',
  `color_scheme` varchar(20) NOT NULL DEFAULT 'blue',
  `style` varchar(255) DEFAULT NULL,
  `rolechangedate` datetime DEFAULT NULL COMMENT 'When does the role expire',
  `pending_role_start_date` datetime DEFAULT NULL COMMENT 'When the pending role change takes effect',
  `pending_roles_id` int(11) DEFAULT NULL COMMENT 'The role that will be applied after current role expires',
  `next_rolechangedate` datetime DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `session_token` varchar(60) DEFAULT NULL,
  `timezone` varchar(255) DEFAULT NULL,
  `movie_layout` tinyint(4) NOT NULL DEFAULT 2 COMMENT '1=1-column, 2=2-columns',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `verified` tinyint(1) NOT NULL DEFAULT 0,
  `verification_token` varchar(255) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` varchar(255) DEFAULT NULL,
  `bad_user` tinyint(1) NOT NULL DEFAULT 0,
  `can_post` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_users_api_token` (`api_token`),
  KEY `ix_user_roles` (`roles_id`),
  KEY `ix_users_created_at` (`created_at`),
  KEY `ix_users_roles_created` (`roles_id`,`created_at`),
  KEY `ix_users_deleted_at` (`deleted_at`),
  KEY `ix_users_username` (`username`),
  KEY `ix_users_email` (`email`),
  KEY `ix_users_host` (`host`),
  KEY `ix_users_lastlogin` (`lastlogin`),
  KEY `ix_users_apiaccess` (`apiaccess`),
  KEY `ix_users_grabs` (`grabs`),
  KEY `ix_users_rolechangedate` (`rolechangedate`),
  KEY `ix_users_verified` (`verified`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users_releases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users_releases` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `users_id` int(10) unsigned NOT NULL,
  `releases_id` int(10) unsigned NOT NULL COMMENT 'FK to releases.id',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ix_usercart_userrelease` (`users_id`,`releases_id`),
  KEY `FK_ur_releases` (`releases_id`),
  CONSTRAINT `FK_ur_releases` FOREIGN KEY (`releases_id`) REFERENCES `releases` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `FK_users_ur` FOREIGN KEY (`users_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `video_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `video_data` (
  `releases_id` int(10) unsigned NOT NULL COMMENT 'FK to releases.id',
  `containerformat` varchar(50) DEFAULT NULL,
  `overallbitrate` varchar(20) DEFAULT NULL,
  `videoduration` varchar(20) DEFAULT NULL,
  `videoformat` varchar(50) DEFAULT NULL,
  `videocodec` varchar(50) DEFAULT NULL,
  `videowidth` int(11) DEFAULT NULL,
  `videoheight` int(11) DEFAULT NULL,
  `videoaspect` varchar(10) DEFAULT NULL,
  `videoframerate` double(7,4) DEFAULT NULL,
  `videolibrary` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`releases_id`),
  CONSTRAINT `FK_vd_releases` FOREIGN KEY (`releases_id`) REFERENCES `releases` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `videos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `videos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Show ID to be used in other tables as reference ',
  `type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 = TV, 1 = Film, 2 = Anime',
  `title` varchar(180) NOT NULL COMMENT 'Name of the video.',
  `countries_id` char(2) NOT NULL DEFAULT '' COMMENT 'Two character country code (FK to countries table).',
  `started` datetime NOT NULL COMMENT 'Date (UTC) of production''s first airing.',
  `anidb` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'ID number for anidb site',
  `imdb` varchar(100) NOT NULL DEFAULT '0' COMMENT 'ID number for IMDB site (without the ''tt'' prefix).',
  `tmdb` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'ID number for TMDB site.',
  `trakt` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'ID number for TraktTV site.',
  `tvdb` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'ID number for TVDB site',
  `tvmaze` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'ID number for TVMaze site.',
  `tvrage` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'ID number for TVRage site.',
  `source` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Which site did we use for info?',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ix_videos_title` (`title`,`type`,`started`,`countries_id`),
  KEY `ix_videos_type_source` (`type`,`source`),
  KEY `ix_videos_imdb` (`imdb`),
  KEY `ix_videos_tmdb` (`tmdb`),
  KEY `ix_videos_trakt` (`trakt`),
  KEY `ix_videos_tvdb` (`tvdb`),
  KEY `ix_videos_tvmaze` (`tvmaze`),
  KEY `ix_videos_tvrage` (`tvrage`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `videos_aliases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `videos_aliases` (
  `videos_id` int(10) unsigned NOT NULL COMMENT 'FK to videos.id of the parent title.',
  `title` varchar(180) NOT NULL COMMENT 'AKA of the video.',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`videos_id`,`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

/*M!999999\- enable the sandbox mode */
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'2014_01_16_195548_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'2014_02_01_311070_create_firewall_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'2017_11_29_223842_create_countries_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2018_01_17_150719_create_permission_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2018_01_17_154034_create_categories_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2018_01_18_101314_create_category_regexes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2018_01_18_102213_create_collection_regexes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2018_01_18_102716_create_binaryblacklist_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2018_01_18_103104_create_content_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2018_01_18_103520_create_forumpost_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2018_01_18_103816_create_genres_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2018_01_18_104345_create_usenet_groups_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2018_01_18_105455_create_release_naming_regexes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2018_01_18_105834_create_settings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2018_01_20_195500_create_collections_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2018_01_20_195528_create_releases_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2018_01_20_195604_create_anidb_episodes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2018_01_20_195615_create_anidb_info_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2018_01_20_195624_create_anidb_titles_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2018_01_20_195636_create_audio_data_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2018_01_20_195648_create_binaries_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2018_01_20_195703_create_bookinfo_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2018_01_20_195716_create_consoleinfo_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2018_01_20_195728_create_dnzb_failures_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2018_01_20_195739_create_gamesinfo_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2018_01_20_195752_create_invitations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2018_01_20_195801_create_logging_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2018_01_20_195812_create_missed_parts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2018_01_20_195822_create_movieinfo_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2018_01_20_195832_create_musicinfo_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2018_01_20_195915_create_par_hashes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2018_01_20_195925_create_parts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2018_01_20_195934_create_predb_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2018_01_20_195954_create_predb_imports_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2018_01_20_200005_create_release_comments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2018_01_20_200018_create_releases_groups_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2018_01_20_200030_create_release_regexes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2018_01_20_200038_create_release_unique_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2018_01_20_200056_create_release_files_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2018_01_20_200104_create_release_nfos_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2018_01_20_200124_create_release_subtitles_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2018_01_20_200151_create_short_groups_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2018_01_20_200200_create_steam_apps_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (46,'2018_01_20_200211_create_tv_episodes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2018_01_20_200218_create_tv_info_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (48,'2018_01_20_200237_create_users_releases_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (49,'2018_01_20_200248_create_user_downloads_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (50,'2018_01_20_200318_create_user_movies_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (51,'2018_01_20_200328_create_user_requests_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (52,'2018_01_20_200336_create_user_series_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (53,'2018_01_20_200346_create_video_data_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (54,'2018_01_20_200353_create_videos_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (55,'2018_01_20_200403_create_videos_aliases_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (56,'2018_01_20_200417_create_xxxinfo_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (58,'2018_04_24_132758_create_cache_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (59,'2018_08_08_100000_create_telescope_entries_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (60,'2018_09_13_070520_add_verification_to_user_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (61,'2019_02_20_102034_create_failed_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (62,'2019_03_11_234818_create_root_categories_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (63,'2019_03_12_090532_change_categories_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (64,'2019_03_12_093837_add_foreign_categories_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (65,'2019_04_04_130055_update_releases_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (66,'2019_04_04_150842_update_movieinfo_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (67,'2019_04_04_152238_update_user_movies_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (68,'2019_06_14_095012_create_role_expiration_emails_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (69,'2019_08_06_140408_create_invitation_user_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (70,'2019_08_23_132941_change_passwordststatus_releases_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (71,'2019_10_10_231045_create_paypal_payments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (72,'2019_10_15_215953_update_tv_episodes_firstaired_column',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (73,'2019_10_18_205920_add_timestamps_to_videos_aliases',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (74,'2019_12_14_000001_create_personal_access_tokens_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (75,'2019_12_30_190950_update_imdb_column_videos_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (76,'2020_01_07_001831_add_unique_index_to_api_token',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (77,'2020_02_17_213449_add_timezone_column_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (78,'2020_03_07_213224_remove_text_hash',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (79,'2020_07_09_223527_create_release_informs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (80,'2020_08_08_212118_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (81,'2020_09_27_163455_add_uuid_to_failed_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (82,'2020_12_27_214949_create_password_securities_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (83,'2022_02_07_220221_add_timestamps_columns_to_missed_parts',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (84,'2023_06_07_000001_create_pulse_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (85,'2023_07_04_211406_add_next_roles_and_rolechangedate_columns_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (86,'2023_12_08_191845_update_users_table_with_name_column',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (87,'2024_01_06_173518_create_payments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (88,'2019_08_14_123627_create_poster_renames_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (89,'2019_08_15_145634_add_source_to_releases_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (91,'2024_01_11_203725_create_predb_crcs_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (92,'2024_01_12_193533_alter_filedate_column_predb_crcs_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (93,'2024_01_12_194256_add_back_timestamps_column_to__predb_crcs_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (94,'2024_02_13_234425_add_indexes_to_movieinfo_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (95,'2024_02_25_162628_add_id_column_to_steam_apps_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (96,'2014_05_19_151759_create_forum_table_categories',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (97,'2014_05_19_152425_create_forum_table_threads',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (98,'2014_05_19_152611_create_forum_table_posts',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (99,'2015_04_14_180344_create_forum_table_threads_read',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (100,'2015_07_22_181406_update_forum_table_categories',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (101,'2015_07_22_181409_update_forum_table_threads',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (102,'2015_07_22_181417_update_forum_table_posts',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (103,'2016_05_24_114302_add_defaults_to_forum_table_threads_columns',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (104,'2016_07_09_111441_add_counts_to_categories_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (105,'2016_07_09_122706_add_counts_to_threads_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (106,'2016_07_10_134700_add_sequence_to_posts_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (107,'2018_11_04_211718_update_categories_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (108,'2019_09_07_210904_update_forum_category_booleans',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (109,'2019_09_07_230148_add_color_to_categories',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (110,'2020_03_22_050710_add_thread_ids_to_categories',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (111,'2020_03_22_055827_add_post_id_to_threads',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (112,'2020_12_02_233754_add_first_post_id_to_threads',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (113,'2021_07_31_094750_add_fk_indices',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (114,'2024_03_30_095622_update_forum_category_colors',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (115,'2024_04_27_124202_create_media_infos_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (116,'2024_05_12_192646_add_uuid_column_to_failed_jobs_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (117,'2024_05_25_122046_drop_stored_procedure',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (118,'2024_09_08_151127_create_grab_stats_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (119,'2024_09_08_151135_create_signup_stats_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (120,'2024_09_08_151158_create_role_stats_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (121,'2024_09_08_151214_create_download_stats_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (122,'2024_09_08_151223_create_release_stats_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (123,'2024_09_15_184830_add_xxx_vr_category',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (124,'2024_10_28_092803_drop_columns_from_settings_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (125,'2024_10_28_115551_rename_lookuptvrage_to_lookuptv',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (126,'2024_11_16_114640_add_invoice_status_to_payments_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (127,'2025_01_01_000000_create_invitations_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (128,'2025_01_30_115835_drop_triggers',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (129,'2025_04_10_202844_add_created_at_updated_at_columns',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (130,'2025_05_04_212955_drop_userseed',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (131,'2025_06_06_000000_add_soft_deletes_to_users_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (132,'2025_08_10_195505_fix_invitations_column_names',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (133,'2025_08_14_000001_add_indexes_to_collections_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (134,'2025_08_22_133811_drop_reqidstatus_column',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (135,'2025_08_28_000000_add_onlyfans_category_to_categories_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (136,'2025_10_16_101145_add_dark_mode_to_users_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (137,'2025_10_16_120000_update_dark_mode_to_theme_preference',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (138,'2025_10_20_132950_convert_content_table_to_utf8mb4',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (139,'2025_10_22_000000_create_system_metrics_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (140,'2025_10_22_204937_add_sort_date_to_signup_stats_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (141,'2025_10_23_094253_add_timezone_to_users_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (142,'2025_10_23_100000_add_movie_layout_to_users_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (143,'2025_10_24_000000_create_user_activity_stats_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (144,'2025_10_27_165328_create_user_activities_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (145,'2025_10_29_000000_create_user_activity_stats_hourly_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (146,'2025_11_05_095815_fix_release_files_collation',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (147,'2025_11_05_104146_fix_all_tables_collation_to_utf8mb4',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (148,'2025_11_05_120504_fix_telescope_tables_collation',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (149,'2025_11_28_000000_create_role_promotions_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (150,'2025_11_29_000000_create_role_promotion_stats_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (151,'2025_11_29_120000_add_role_stacking_fields_to_users_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (152,'2025_11_29_120001_create_user_role_history_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (153,'2025_12_04_000000_add_redis_args_to_settings_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (154,'2025_12_05_000000_replace_anidb_with_anilist',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (155,'2025_12_21_000000_add_categories_postdate_index_to_releases_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (156,'2025_12_21_000001_add_covering_index_for_tv_search',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (157,'2025_12_22_000000_add_additional_performance_indexes_to_releases_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (158,'2025_12_22_120000_add_composite_indexes_for_admin_performance',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (159,'2026_01_15_000000_fix_invitations_foreign_key',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (160,'2026_01_15_000001_cleanup_invitations_legacy_columns',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (161,'2026_01_23_150053_create_user_excluded_categories_table',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (162,'2026_01_26_105835_add_releases_categories_videos_index',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (163,'2019_10_03_112445_add_bad_user_to_users_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (164,'2024_03_14_201011_add_nzb_password_column_to_releases_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (165,'2024_08_13_232303_add_index_to_media_infos_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (166,'2024_12_20_113606_add_can_post_column_to_users_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (167,'2025_01_13_000000_create_nzb_backup_tables',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (168,'2025_11_07_164001__add_predb_scrape_setting',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (169,'2026_01_21_134835_create_api_token_ip_addresses_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (170,'2026_02_01_000000_create_release_reports_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (171,'2024_08_31_084308_add_content_approval_support',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (172,'2026_02_09_114731_add_deleted_by_to_users_table',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (173,'2026_02_11_000000_remove_ishashed_and_dehashstatus_columns',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (174,'2026_02_12_000000_add_covering_index_for_movie_browse',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (175,'2026_02_13_000000_drop_xxxinfo_and_releases_xxxinfo_id',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (176,'2026_02_19_000000_add_pp_timeout_count_to_releases_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (180,'2026_03_03_120000_add_color_scheme_to_users_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (181,'2026_03_10_000000_create_registration_periods_table',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (182,'2026_03_10_000001_create_registration_status_history_table',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (183,'2026_03_11_120000_add_lastdownload_to_users_table',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (184,'2026_03_18_000000_replace_vendor_countries_table',20);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (185,'2026_03_31_000000_add_missing_performance_indexes',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (186,'2026_04_01_000000_create_service_statuses_table',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (187,'2026_04_01_000001_create_service_incidents_table',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (188,'2026_04_01_120000_add_user_sort_column_indexes',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (189,'2026_04_01_120000_service_incidents_many_services',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (190,'2026_04_01_130000_add_endpoint_url_to_service_statuses',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (191,'2026_04_01_130001_add_is_auto_to_service_incidents',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (192,'2026_03_19_150944_add_depth_to_categories',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (193,'2026_04_02_170000_fix_add_depth_to_forum_categories',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (194,'2026_04_09_120000_normalize_padded_imdb_ids',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (195,'2026_04_13_000002_add_probe_fields_to_service_statuses_table',24);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (196,'2026_04_24_000000_create_passkeys_table',24);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (197,'2026_04_27_000000_add_is_permanent_to_user_activities',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (198,'2026_05_05_123242_add_cbp_query_indexes',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (199,'2026_05_05_124540_phase2_shrink_binaries_binaryhash',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (200,'2026_05_06_093900_restore_cbp_foreign_keys',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (201,'2026_05_08_154620_add_session_token_to_users_table',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (202,'2026_05_08_180000_drop_nzb_guid_columns',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (203,'2026_05_12_000000_add_ix_releases_searchname',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (204,'2026_06_08_000000_add_response_fields_to_release_reports_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (205,'2026_06_10_000000_create_trusted_devices_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (206,'2026_06_11_000000_create_password_reset_tokens_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (207,'2026_06_11_000001_add_resetguid_to_users_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (208,'2026_06_17_000000_create_gdpr_requests_table',27);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (209,'2026_06_17_000001_create_gdpr_consents_table',27);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (210,'2026_06_17_000002_create_gdpr_audit_logs_table',27);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (211,'2026_06_17_000000_update_rss_service_status_endpoint_to_health',28);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (212,'2026_06_17_000001_neutralize_rss_http_400_false_positive_incidents',28);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (213,'2026_07_12_000000_add_additional_postprocessing_claim_fields',29);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (214,'2026_07_12_000001_add_release_creation_claim_fields_to_collections_table',30);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (215,'2026_07_12_000002_add_release_creation_failure_fields_to_collections_table',31);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (216,'2026_07_13_000000_add_admin_list_performance_indexes',32);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (217,'2026_07_13_000000_add_nzb_creation_claim_fields',32);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (218,'2026_07_15_000000_add_releases_adddate_id_index',33);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (219,'2026_08_02_000000_create_search_index_failures_table',34);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (220,'2026_08_03_000000_prepare_cbp_optimized_storage',35);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (221,'2026_08_03_000001_finalize_cbp_binary_hash_storage',36);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (222,'2026_08_04_082439_add_fix_release_name_query_indexes',37);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (223,'2026_08_10_192527_convert_size_settings_to_bytes',38);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (224,'2026_08_13_001652_normalize_and_optimize_releases_table',39);
