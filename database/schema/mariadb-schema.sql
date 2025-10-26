SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `anidb_episodes`;

CREATE TABLE `anidb_episodes` (
  `anidbid` int(10) unsigned NOT NULL COMMENT 'ID of title from AniDB',
  `episodeid` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'anidb id for this episode',
  `episode_no` smallint(5) unsigned NOT NULL COMMENT 'Numeric version of episode (leave 0 for combined episodes).',
  `episode_title` varchar(255) NOT NULL COMMENT 'Title of the episode (en, x-jat)',
  `airdate` date NOT NULL,
  PRIMARY KEY (`anidbid`,`episodeid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

DROP TABLE IF EXISTS `anidb_info`;

CREATE TABLE `anidb_info` (
  `anidbid` int(10) unsigned NOT NULL COMMENT 'ID of title from AniDB',
  `type` varchar(32) DEFAULT NULL,
  `startdate` date DEFAULT NULL,
  `enddate` date DEFAULT NULL,
  `updated` timestamp NOT NULL DEFAULT current_timestamp(),
  `related` varchar(1024) DEFAULT NULL,
  `similar` varchar(1024) DEFAULT NULL,
  `creators` varchar(1024) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `rating` varchar(5) DEFAULT NULL,
  `picture` varchar(255) DEFAULT NULL,
  `categories` varchar(1024) DEFAULT NULL,
  `characters` varchar(1024) DEFAULT NULL,
  PRIMARY KEY (`anidbid`),
  KEY `ix_anidb_info_datetime` (`startdate`,`enddate`,`updated`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

DROP TABLE IF EXISTS `anidb_titles`;

CREATE TABLE `anidb_titles` (
  `anidbid` int(10) unsigned NOT NULL COMMENT 'ID of title from AniDB',
  `type` varchar(25) NOT NULL COMMENT 'type of title.',
  `lang` varchar(25) NOT NULL,
  `title` varchar(255) NOT NULL,
  PRIMARY KEY (`anidbid`,`type`,`lang`,`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

DROP TABLE IF EXISTS `audio_data`;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

DROP TABLE IF EXISTS `binaries`;

CREATE TABLE `binaries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `binaryhash` blob NOT NULL DEFAULT '0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0',
  `name` varchar(1000) NOT NULL DEFAULT '',
  `collections_id` int(10) unsigned NOT NULL DEFAULT 0,
  `filenumber` int(10) unsigned NOT NULL DEFAULT 0,
  `totalparts` int(10) unsigned NOT NULL DEFAULT 0,
  `currentparts` int(10) unsigned NOT NULL DEFAULT 0,
  `partcheck` tinyint(1) NOT NULL DEFAULT 0,
  `partsize` bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_collection_id_filenumber` (`collections_id`,`filenumber`),
  KEY `ix_binaries_binaryhash` (`binaryhash`(3072)),
  KEY `ix_binaries_collection` (`collections_id`),
  KEY `ix_binaries_partcheck` (`partcheck`),
  CONSTRAINT `FK_Collections` FOREIGN KEY (`collections_id`) REFERENCES `collections` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

DROP TABLE IF EXISTS `binaryblacklist`;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

DROP TABLE IF EXISTS `bookinfo`;

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
  FULLTEXT KEY `ix_bookinfo_author_title_ft` (`author`,`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

DROP TABLE IF EXISTS `cache`;

CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  UNIQUE KEY `cache_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci ROW_FORMAT=DYNAMIC;

DROP TABLE IF EXISTS `categories`;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

DROP TABLE IF EXISTS `category_regexes`;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

DROP TABLE IF EXISTS `collection_regexes`;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

DROP TABLE IF EXISTS `collections`;

CREATE TABLE `collections` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `subject` varchar(255) NOT NULL DEFAULT '',
  `fromname` varchar(255) NOT NULL DEFAULT '',
  `date` datetime DEFAULT NULL,
  `xref` varchar(2000) NOT NULL DEFAULT '',
  `totalfiles` int(10) unsigned NOT NULL DEFAULT 0,
  `groups_id` int(10) unsigned NOT NULL DEFAULT 0,
  `collectionhash` varchar(255) NOT NULL DEFAULT '0',
  `collection_regexes_id` int(11) NOT NULL DEFAULT 0 COMMENT 'FK to collection_regexes.id',
  `dateadded` datetime DEFAULT NULL,
  `added` timestamp NOT NULL DEFAULT current_timestamp(),
  `filecheck` tinyint(1) NOT NULL DEFAULT 0,
  `filesize` bigint(20) unsigned NOT NULL DEFAULT 0,
  `releases_id` int(11) DEFAULT NULL,
  `noise` char(32) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ix_collection_collectionhash` (`collectionhash`),
  KEY `fromname` (`fromname`),
  KEY `date` (`date`),
  KEY `groups_id` (`groups_id`),
  KEY `ix_collection_dateadded` (`dateadded`),
  KEY `ix_collection_filecheck` (`filecheck`),
  KEY `ix_collection_releaseid` (`releases_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

DROP TABLE IF EXISTS `consoleinfo`;

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
  FULLTEXT KEY `ix_consoleinfo_title_platform_ft` (`title`,`platform`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

DROP TABLE IF EXISTS `content`;

CREATE TABLE `content` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `url` varchar(2000) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `metadescription` varchar(1000) NOT NULL,
  `metakeywords` varchar(1000) NOT NULL,
  `contenttype` int(11) NOT NULL,
  `status` int(11) NOT NULL,
  `ordinal` int(11) DEFAULT NULL,
  `role` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_status_contenttype_role` (`status`,`contenttype`,`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

DROP TABLE IF EXISTS `countries`;

CREATE TABLE `countries` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `capital` varchar(255) DEFAULT NULL,
  `citizenship` varchar(255) DEFAULT NULL,
  `country_code` char(3) NOT NULL DEFAULT '',
  `currency` varchar(255) DEFAULT NULL,
  `currency_code` varchar(255) DEFAULT NULL,
  `currency_sub_unit` varchar(255) DEFAULT NULL,
  `currency_symbol` varchar(3) DEFAULT NULL,
  `currency_decimals` int(11) DEFAULT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `iso_3166_2` char(2) NOT NULL DEFAULT '',
  `iso_3166_3` char(3) NOT NULL DEFAULT '',
  `name` varchar(255) NOT NULL DEFAULT '',
  `region_code` char(3) NOT NULL DEFAULT '',
  `sub_region_code` char(3) NOT NULL DEFAULT '',
  `eea` tinyint(1) NOT NULL DEFAULT 0,
  `calling_code` varchar(3) DEFAULT NULL,
  `flag` varchar(6) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci ROW_FORMAT=DYNAMIC;

DROP TABLE IF EXISTS `dnzb_failures`;

CREATE TABLE `dnzb_failures` (
  `release_id` int(10) unsigned NOT NULL,
  `users_id` int(10) unsigned NOT NULL,
  `failed` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`release_id`,`users_id`),
  KEY `FK_users_df` (`users_id`),
  CONSTRAINT `FK_df_releases` FOREIGN KEY (`release_id`) REFERENCES `releases` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `FK_users_df` FOREIGN KEY (`users_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

DROP TABLE IF EXISTS `failed_jobs`;

CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci ROW_FORMAT=DYNAMIC;

DROP TABLE IF EXISTS `firewall`;

CREATE TABLE `firewall` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(39) NOT NULL,
  `whitelisted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `firewall_ip_address_unique` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci ROW_FORMAT=DYNAMIC;

DROP TABLE IF EXISTS `forum_categories`;

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
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `_lft` int(10) unsigned NOT NULL DEFAULT 0,
  `_rgt` int(10) unsigned NOT NULL DEFAULT 0,
  `parent_id` int(10) unsigned DEFAULT NULL,
  `color` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `forum_categories__lft__rgt_parent_id_index` (`_lft`,`_rgt`,`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci ROW_FORMAT=DYNAMIC;

DROP TABLE IF EXISTS `forum_posts`;

CREATE TABLE `forum_posts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `thread_id` int(10) unsigned NOT NULL,
  `author_id` bigint(20) unsigned NOT NULL,
  `content` text NOT NULL,
  `post_id` int(10) unsigned DEFAULT NULL,
  `sequence` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `forum_posts_thread_id_index` (`thread_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci ROW_FORMAT=DYNAMIC;

DROP TABLE IF EXISTS `forum_threads`;

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
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `forum_threads_category_id_index` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci ROW_FORMAT=DYNAMIC;

DROP TABLE IF EXISTS `forum_threads_read`;

CREATE TABLE `forum_threads_read` (
  `thread_id` int(10) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci ROW_FORMAT=DYNAMIC;

DROP TABLE IF EXISTS `forumpost`;

CREATE TABLE `forumpost` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `forumid` int(11) NOT NULL DEFAULT 1,
  `parentid` int(11) NOT NULL DEFAULT 0,
  `users_id` int(10) unsigned NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `locked` tinyint(1) NOT NULL DEFAULT 0,
  `sticky` tinyint(1) NOT NULL DEFAULT 0,
  `replies` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parentid` (`parentid`),
  KEY `userid` (`users_id`),
  CONSTRAINT `FK_users_fp` FOREIGN KEY (`users_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

DROP TABLE IF EXISTS `gamesinfo`;

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
  FULLTEXT KEY `ix_title_ft` (`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

DROP TABLE IF EXISTS `genres`;

CREATE TABLE `genres` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `type` int(11) DEFAULT NULL,
  `disabled` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

DROP TABLE IF EXISTS `invitations`;

CREATE TABLE `invitations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `guid` varchar(50) NOT NULL,
  `users_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `FK_users_inv` (`users_id`),
  CONSTRAINT `FK_users_inv` FOREIGN KEY (`users_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

DROP TABLE IF EXISTS `jobs`;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci ROW_FORMAT=DYNAMIC;

DROP TABLE IF EXISTS `logging`;

CREATE TABLE `logging` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `time` datetime DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `host` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

DROP TABLE IF EXISTS `migrations`;

CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci ROW_FORMAT=DYNAMIC;

DROP TABLE IF EXISTS `missed_parts`;

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

DROP TABLE IF EXISTS `model_has_permissions`;

CREATE TABLE `model_has_permissions` (
  `permission_id` int(10) unsigned NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_type_model_id_index` (`model_type`,`model_id`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

DROP TABLE IF EXISTS `model_has_roles`;

CREATE TABLE `model_has_roles` (
  `role_id` int(10) unsigned NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_type_model_id_index` (`model_type`,`model_id`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

DROP TABLE IF EXISTS `movieinfo`;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

DROP TABLE IF EXISTS `musicinfo`;

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
  FULLTEXT KEY `ix_musicinfo_artist_title_ft` (`artist`,`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

DROP TABLE IF EXISTS `par_hashes`;

CREATE TABLE `par_hashes` (
  `releases_id` int(10) unsigned NOT NULL COMMENT 'FK to releases.id',
  `hash` varchar(32) NOT NULL COMMENT 'hash_16k block of par2',
  PRIMARY KEY (`releases_id`,`hash`),
  CONSTRAINT `FK_ph_releases` FOREIGN KEY (`releases_id`) REFERENCES `releases` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

DROP TABLE IF EXISTS `parts`;

CREATE TABLE `parts` (
  `binaries_id` bigint(20) unsigned NOT NULL DEFAULT 0,
  `messageid` varchar(255) NOT NULL DEFAULT '',
  `number` bigint(20) unsigned NOT NULL DEFAULT 0,
  `partnumber` int(10) unsigned NOT NULL DEFAULT 0,
  `size` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`binaries_id`,`number`),
  CONSTRAINT `FK_binaries` FOREIGN KEY (`binaries_id`) REFERENCES `binaries` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

DROP TABLE IF EXISTS `password_securities`;

CREATE TABLE `password_securities` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `google2fa_enable` tinyint(1) NOT NULL DEFAULT 0,
  `google2fa_secret` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci ROW_FORMAT=DYNAMIC;

DROP TABLE IF EXISTS `payments`;

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
  `invoice_amount` varchar(255) NOT NULL,
  `payment_method` varchar(255) NOT NULL,
  `payment_value` varchar(255) NOT NULL,
  `webhook_id` varchar(255) NOT NULL,
  `invoice_id` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci ROW_FORMAT=DYNAMIC;

DROP TABLE IF EXISTS `paypal_payments`;

CREATE TABLE `paypal_payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `users_id` int(11) NOT NULL,
  `transaction_id` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci ROW_FORMAT=DYNAMIC;

DROP TABLE IF EXISTS `permissions`;

CREATE TABLE `permissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

DROP TABLE IF EXISTS `personal_access_tokens`;

CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci ROW_FORMAT=DYNAMIC;

DROP TABLE IF EXISTS `poster_renames`;

CREATE TABLE `poster_renames` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `title` varchar(500) NOT NULL,
  `poster` varchar(255) NOT NULL,
  `source` varchar(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `poster_title` (`poster`,`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci ROW_FORMAT=DYNAMIC;

DROP TABLE IF EXISTS `predb`;

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
  FULLTEXT KEY `ft_predb_filename` (`filename`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
DROP TABLE IF EXISTS `predb_crcs`;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci ROW_FORMAT=DYNAMIC;

DROP TABLE IF EXISTS `predb_imports`;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

DROP TABLE IF EXISTS `pulse_aggregates`;

CREATE TABLE `pulse_aggregates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `bucket` int(10) unsigned NOT NULL,
  `period` mediumint(8) unsigned NOT NULL,
  `type` varchar(255) NOT NULL,
  `key` text NOT NULL,
  `key_hash` binary(16) GENERATED ALWAYS AS (unhex(md5(`key`))) VIRTUAL,
  `aggregate` varchar(255) NOT NULL,
  `value` decimal(20,2) NOT NULL,
  `count` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pulse_aggregates_bucket_period_type_aggregate_key_hash_unique` (`bucket`,`period`,`type`,`aggregate`,`key_hash`),
  KEY `pulse_aggregates_period_bucket_index` (`period`,`bucket`),
  KEY `pulse_aggregates_type_index` (`type`),
  KEY `pulse_aggregates_period_type_aggregate_bucket_index` (`period`,`type`,`aggregate`,`bucket`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci ROW_FORMAT=DYNAMIC;

DROP TABLE IF EXISTS `pulse_entries`;

CREATE TABLE `pulse_entries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `timestamp` int(10) unsigned NOT NULL,
  `type` varchar(255) NOT NULL,
  `key` text NOT NULL,
  `key_hash` binary(16) GENERATED ALWAYS AS (unhex(md5(`key`))) VIRTUAL,
  `value` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pulse_entries_timestamp_index` (`timestamp`),
  KEY `pulse_entries_type_index` (`type`),
  KEY `pulse_entries_key_hash_index` (`key_hash`),
  KEY `pulse_entries_timestamp_type_key_hash_value_index` (`timestamp`,`type`,`key_hash`,`value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci ROW_FORMAT=DYNAMIC;

DROP TABLE IF EXISTS `pulse_values`;

CREATE TABLE `pulse_values` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `timestamp` int(10) unsigned NOT NULL,
  `type` varchar(255) NOT NULL,
  `key` text NOT NULL,
  `key_hash` binary(16) GENERATED ALWAYS AS (unhex(md5(`key`))) VIRTUAL,
  `value` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pulse_values_type_key_hash_unique` (`type`,`key_hash`),
  KEY `pulse_values_timestamp_index` (`timestamp`),
  KEY `pulse_values_type_index` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci ROW_FORMAT=DYNAMIC;

DROP TABLE IF EXISTS `release_comments`;

CREATE TABLE `release_comments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `releases_id` int(10) unsigned NOT NULL COMMENT 'FK to releases.id',
  `text` varchar(2000) NOT NULL DEFAULT '',
  `isvisible` tinyint(1) NOT NULL DEFAULT 1,
  `issynced` tinyint(1) NOT NULL DEFAULT 0,
  `gid` varchar(32) DEFAULT NULL,
  `cid` varchar(32) DEFAULT NULL,
  `username` varchar(255) NOT NULL DEFAULT '',
  `users_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `host` varchar(15) DEFAULT NULL,
  `shared` tinyint(1) NOT NULL DEFAULT 0,
  `shareid` varchar(40) NOT NULL DEFAULT '',
  `siteid` varchar(40) NOT NULL DEFAULT '',
  `sourceid` bigint(20) unsigned DEFAULT NULL,
  `nzb_guid` binary(16) NOT NULL DEFAULT '0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0',
  PRIMARY KEY (`id`),
  KEY `ix_releasecomment_releases_id` (`releases_id`),
  KEY `ix_releasecomment_userid` (`users_id`),
  CONSTRAINT `FK_rc_releases` FOREIGN KEY (`releases_id`) REFERENCES `releases` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

DROP TABLE IF EXISTS `release_files`;

CREATE TABLE `release_files` (
  `releases_id` int(10) unsigned NOT NULL COMMENT 'FK to releases.id',
  `name` varchar(255) NOT NULL DEFAULT '',
  `size` bigint(20) unsigned NOT NULL DEFAULT 0,
  `ishashed` tinyint(1) NOT NULL DEFAULT 0,
  `crc32` varchar(255) NOT NULL DEFAULT '',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `passworded` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`releases_id`,`name`),
  KEY `ix_releasefiles_ishashed` (`ishashed`),
  CONSTRAINT `FK_rf_releases` FOREIGN KEY (`releases_id`) REFERENCES `releases` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
DROP TABLE IF EXISTS `release_informs`;
CREATE TABLE `release_informs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `relOName` varchar(255) NOT NULL,
  `relPName` varchar(255) NOT NULL,
  `api_token` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci ROW_FORMAT=DYNAMIC;
DROP TABLE IF EXISTS `release_naming_regexes`;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
DROP TABLE IF EXISTS `release_nfos`;
CREATE TABLE `release_nfos` (
  `releases_id` int(10) unsigned NOT NULL COMMENT 'FK to releases.id',
  `nfo` blob DEFAULT NULL,
  PRIMARY KEY (`releases_id`),
  CONSTRAINT `FK_rn_releases` FOREIGN KEY (`releases_id`) REFERENCES `releases` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
DROP TABLE IF EXISTS `release_regexes`;
CREATE TABLE `release_regexes` (
  `releases_id` int(10) unsigned NOT NULL DEFAULT 0,
  `collection_regex_id` int(11) NOT NULL DEFAULT 0,
  `naming_regex_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`releases_id`,`collection_regex_id`,`naming_regex_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
DROP TABLE IF EXISTS `release_subtitles`;
CREATE TABLE `release_subtitles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `releases_id` int(10) unsigned NOT NULL COMMENT 'FK to releases.id',
  `subsid` int(10) unsigned NOT NULL,
  `subslanguage` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ix_releasesubs_releases_id_subsid` (`releases_id`,`subsid`),
  CONSTRAINT `FK_rs_releases` FOREIGN KEY (`releases_id`) REFERENCES `releases` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
DROP TABLE IF EXISTS `release_unique`;
CREATE TABLE `release_unique` (
  `releases_id` int(10) unsigned NOT NULL COMMENT 'FK to releases.id.',
  `uniqueid` varchar(255) NOT NULL COMMENT 'Unique_ID from mediainfo.',
  PRIMARY KEY (`releases_id`,`uniqueid`),
  CONSTRAINT `FK_ru_releases` FOREIGN KEY (`releases_id`) REFERENCES `releases` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
DROP TABLE IF EXISTS `releases`;
CREATE TABLE `releases` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `searchname` varchar(255) NOT NULL DEFAULT '',
  `totalpart` int(11) DEFAULT 0,
  `groups_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'FK to groups.id',
  `size` bigint(20) unsigned NOT NULL DEFAULT 0,
  `postdate` datetime DEFAULT NULL,
  `adddate` datetime DEFAULT NULL,
  `updatetime` timestamp NOT NULL DEFAULT current_timestamp(),
  `gid` varchar(32) DEFAULT NULL,
  `guid` varchar(40) NOT NULL,
  `leftguid` char(1) NOT NULL COMMENT 'The first letter of the release guid',
  `fromname` varchar(255) DEFAULT NULL,
  `completion` double NOT NULL DEFAULT 0,
  `categories_id` int(11) NOT NULL DEFAULT 10,
  `videos_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'FK to videos.id of the parent series.',
  `tv_episodes_id` int(11) NOT NULL DEFAULT 0 COMMENT 'FK to tv_episodes.id for the episode.',
  `imdbid` varchar(100) DEFAULT NULL,
  `xxxinfo_id` int(11) NOT NULL DEFAULT 0,
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
  `audiostatus` tinyint(1) NOT NULL DEFAULT 0,
  `dehashstatus` tinyint(1) NOT NULL DEFAULT 0,
  `reqidstatus` tinyint(1) NOT NULL DEFAULT 0,
  `nzbstatus` tinyint(1) NOT NULL DEFAULT 0,
  `iscategorized` tinyint(1) NOT NULL DEFAULT 0,
  `isrenamed` tinyint(1) NOT NULL DEFAULT 0,
  `ishashed` tinyint(1) NOT NULL DEFAULT 0,
  `proc_pp` tinyint(1) NOT NULL DEFAULT 0,
  `proc_sorter` tinyint(1) NOT NULL DEFAULT 0,
  `proc_par2` tinyint(1) NOT NULL DEFAULT 0,
  `proc_nfo` tinyint(1) NOT NULL DEFAULT 0,
  `proc_files` tinyint(1) NOT NULL DEFAULT 0,
  `proc_uid` tinyint(1) NOT NULL DEFAULT 0,
  `proc_srr` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Has the release been srr\nprocessed',
  `proc_hash16k` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Has the release been hash16k\nprocessed',
  `proc_crc32` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Has the release been crc32 processed',
  `nzb_guid` blob NOT NULL,
  `source` smallint(5) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`,`categories_id`),
  KEY `ix_releases_groupsid` (`groups_id`,`passwordstatus`),
  KEY `ix_releases_postdate_searchname` (`postdate`,`searchname`),
  KEY `ix_releases_leftguid` (`leftguid`,`predb_id`),
  KEY `ix_releases_musicinfo_id` (`musicinfo_id`,`passwordstatus`),
  KEY `ix_releases_predb_id_searchname` (`predb_id`,`searchname`),
  KEY `ix_releases_haspreview_passwordstatus` (`haspreview`,`passwordstatus`),
  KEY `ix_releases_nfostatus` (`nfostatus`,`size`),
  KEY `ix_releases_dehashstatus` (`dehashstatus`,`ishashed`),
  KEY `ix_releases_name` (`name`),
  KEY `ix_releases_guid` (`guid`),
  KEY `ix_releases_videos_id` (`videos_id`),
  KEY `ix_releases_tv_episodes_id` (`tv_episodes_id`),
  KEY `ix_releases_imdbid` (`imdbid`),
  KEY `ix_releases_xxxinfo_id` (`xxxinfo_id`),
  KEY `ix_releases_consoleinfo_id` (`consoleinfo_id`),
  KEY `ix_releases_gamesinfo_id` (`gamesinfo_id`),
  KEY `ix_releases_bookinfo_id` (`bookinfo_id`),
  KEY `ix_releases_anidbid` (`anidbid`),
  KEY `ix_releases_movieinfo_id` (`movieinfo_id`),
  KEY `ix_releases_passwordstatus` (`passwordstatus`),
  KEY `ix_releases_nzb_guid` (`nzb_guid`(3072))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
DROP TABLE IF EXISTS `releases_groups`;
CREATE TABLE `releases_groups` (
  `releases_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'FK to releases.id',
  `groups_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'FK to groups.id',
  PRIMARY KEY (`releases_id`,`groups_id`),
  CONSTRAINT `FK_rg_releases` FOREIGN KEY (`releases_id`) REFERENCES `releases` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
DROP TABLE IF EXISTS `role_expiration_emails`;
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
DROP TABLE IF EXISTS `role_has_permissions`;
CREATE TABLE `role_has_permissions` (
  `permission_id` int(10) unsigned NOT NULL,
  `role_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
DROP TABLE IF EXISTS `roles`;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
DROP TABLE IF EXISTS `root_categories`;
CREATE TABLE `root_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `disablepreview` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_root_categories_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `name` varchar(25) NOT NULL DEFAULT '',
  `value` varchar(1000) NOT NULL DEFAULT '',
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
DROP TABLE IF EXISTS `short_groups`;
CREATE TABLE `short_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `first_record` bigint(20) unsigned NOT NULL DEFAULT 0,
  `last_record` bigint(20) unsigned NOT NULL DEFAULT 0,
  `updated` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_shortgroups_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
DROP TABLE IF EXISTS `steam_apps`;
CREATE TABLE `steam_apps` (
  `name` varchar(255) NOT NULL DEFAULT '' COMMENT 'Steam application name',
  `appid` int(10) unsigned NOT NULL COMMENT 'Steam application id',
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  KEY `ix_name_appid` (`name`,`appid`),
  FULLTEXT KEY `ix_name_ft` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
DROP TABLE IF EXISTS `telescope_entries`;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci ROW_FORMAT=DYNAMIC;
DROP TABLE IF EXISTS `telescope_entries_tags`;
CREATE TABLE `telescope_entries_tags` (
  `entry_uuid` char(36) NOT NULL,
  `tag` varchar(255) NOT NULL,
  KEY `telescope_entries_tags_entry_uuid_tag_index` (`entry_uuid`,`tag`),
  KEY `telescope_entries_tags_tag_index` (`tag`),
  CONSTRAINT `telescope_entries_tags_entry_uuid_foreign` FOREIGN KEY (`entry_uuid`) REFERENCES `telescope_entries` (`uuid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci ROW_FORMAT=DYNAMIC;

DROP TABLE IF EXISTS `telescope_monitoring`;

CREATE TABLE `telescope_monitoring` (
  `tag` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci ROW_FORMAT=DYNAMIC;

DROP TABLE IF EXISTS `tv_episodes`;

CREATE TABLE `tv_episodes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `videos_id` int(10) unsigned NOT NULL COMMENT 'FK to videos.id of the parent series.',
  `series` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT 'Number of series/season.',
  `episode` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT 'Number of episode within series',
  `se_complete` varchar(10) NOT NULL COMMENT 'String version of Series/Episode as taken from release subject (i.e. S02E21+22).',
  `title` varchar(180) NOT NULL COMMENT 'Title of the episode.',
  `firstaired` date DEFAULT NULL COMMENT 'Date of original airing/release.',
  `summary` text NOT NULL COMMENT 'Description/summary of the episode.',
  PRIMARY KEY (`id`),
  UNIQUE KEY `videos_id` (`videos_id`,`series`,`episode`,`firstaired`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

DROP TABLE IF EXISTS `tv_info`;

CREATE TABLE `tv_info` (
  `videos_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'FK to video.id',
  `summary` text NOT NULL COMMENT 'Description/summary of the show.',
  `publisher` varchar(50) NOT NULL COMMENT 'The channel/network of production/release (ABC, BBC, Showtime, etc.).',
  `localzone` varchar(50) NOT NULL DEFAULT '' COMMENT 'The linux tz style identifier',
  `image` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Does the video have a cover image?',
  PRIMARY KEY (`videos_id`),
  KEY `ix_tv_info_image` (`image`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

DROP TABLE IF EXISTS `usenet_groups`;

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
  KEY `active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

DROP TABLE IF EXISTS `user_downloads`;

CREATE TABLE `user_downloads` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `users_id` int(10) unsigned NOT NULL,
  `hosthash` varchar(50) NOT NULL DEFAULT '',
  `timestamp` datetime NOT NULL,
  `releases_id` int(10) unsigned NOT NULL COMMENT 'FK to releases.id',
  PRIMARY KEY (`id`),
  KEY `userid` (`users_id`),
  KEY `timestamp` (`timestamp`),
  CONSTRAINT `FK_users_ud` FOREIGN KEY (`users_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

DROP TABLE IF EXISTS `user_invitations`;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci ROW_FORMAT=DYNAMIC;

DROP TABLE IF EXISTS `user_movies`;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

DROP TABLE IF EXISTS `user_requests`;

CREATE TABLE `user_requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `users_id` int(10) unsigned NOT NULL,
  `hosthash` varchar(50) NOT NULL DEFAULT '',
  `request` varchar(255) NOT NULL,
  `timestamp` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `userid` (`users_id`),
  KEY `timestamp` (`timestamp`),
  CONSTRAINT `FK_users_urq` FOREIGN KEY (`users_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

DROP TABLE IF EXISTS `user_series`;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

DROP TABLE IF EXISTS `users`;

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
  `style` varchar(255) DEFAULT NULL,
  `rolechangedate` datetime DEFAULT NULL COMMENT 'When does the role expire',
  `next_rolechangedate` datetime DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `timezone` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `verified` tinyint(1) NOT NULL DEFAULT 0,
  `verification_token` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_users_api_token` (`api_token`),
  KEY `ix_user_roles` (`roles_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

DROP TABLE IF EXISTS `users_releases`;

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

DROP TABLE IF EXISTS `video_data`;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

DROP TABLE IF EXISTS `videos`;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

DROP TABLE IF EXISTS `videos_aliases`;

CREATE TABLE `videos_aliases` (
  `videos_id` int(10) unsigned NOT NULL COMMENT 'FK to videos.id of the parent title.',
  `title` varchar(180) NOT NULL COMMENT 'AKA of the video.',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`videos_id`,`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

DROP TABLE IF EXISTS `xxxinfo`;

CREATE TABLE `xxxinfo` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(1024) NOT NULL,
  `tagline` varchar(1024) NOT NULL,
  `plot` blob DEFAULT NULL,
  `genre` varchar(255) NOT NULL,
  `director` varchar(255) DEFAULT NULL,
  `actors` varchar(2500) NOT NULL,
  `extras` text DEFAULT NULL,
  `productinfo` text DEFAULT NULL,
  `trailers` text DEFAULT NULL,
  `directurl` varchar(2000) NOT NULL,
  `classused` varchar(20) NOT NULL DEFAULT '',
  `cover` tinyint(1) NOT NULL DEFAULT 0,
  `backdrop` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ix_xxxinfo_title` (`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

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

SET FOREIGN_KEY_CHECKS = 1;
