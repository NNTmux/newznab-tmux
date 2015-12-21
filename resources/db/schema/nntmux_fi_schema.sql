DROP TABLE IF EXISTS site;
DROP TABLE IF EXISTS anidb;
DROP TABLE IF EXISTS animetitles;

DROP TABLE IF EXISTS anidb_episodes;
CREATE TABLE IF NOT EXISTS anidb_episodes (
  anidbid INT(10) UNSIGNED NOT NULL COMMENT 'id of title from AniDB',
  episodeid INT(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'anidb id for this episode',
  episode_no SMALLINT(5) UNSIGNED NOT NULL COMMENT 'Numeric version of episode (leave 0 for combined episodes).',
  episode_title VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Title of the episode (en, x-jat)',
  airdate DATE NOT NULL,
  PRIMARY KEY (anidbid,episodeid)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci;

DROP TABLE IF EXISTS anidb_info;
CREATE TABLE IF NOT EXISTS anidb_info (
  anidbid INT(10) UNSIGNED NOT NULL COMMENT 'id of title from AniDB',
  type VARCHAR(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  startdate DATE DEFAULT NULL,
  enddate DATE DEFAULT NULL,
  updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  related VARCHAR(1024) COLLATE utf8_unicode_ci DEFAULT NULL,
  similar VARCHAR(1024) COLLATE utf8_unicode_ci DEFAULT NULL,
  creators VARCHAR(1024) COLLATE utf8_unicode_ci DEFAULT NULL,
  description TEXT COLLATE utf8_unicode_ci,
  rating VARCHAR(5) COLLATE utf8_unicode_ci DEFAULT NULL,
  picture VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  categories VARCHAR(1024) COLLATE utf8_unicode_ci DEFAULT NULL,
  characters VARCHAR(1024) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (anidbid),
  KEY ix_anidb_info_datetime (startdate,enddate,updated)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci;

DROP TABLE IF EXISTS anidb_titles;
CREATE TABLE IF NOT EXISTS anidb_titles (
  anidbid INT(10) UNSIGNED NOT NULL COMMENT 'id of title from AniDB',
  type VARCHAR(25) COLLATE utf8_unicode_ci NOT NULL COMMENT 'type of title.',
  lang VARCHAR(25) COLLATE utf8_unicode_ci NOT NULL,
  title VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (anidbid,type,lang,title)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci;

DROP TABLE IF EXISTS binaries;
CREATE TABLE IF NOT EXISTS binaries (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(512) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  fromname VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  date DATETIME DEFAULT NULL,
  xref VARCHAR(1024) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  totalParts INT(11) UNSIGNED NOT NULL DEFAULT '0',
  groupid INT(11) UNSIGNED NOT NULL DEFAULT '0',
  procstat INT(11) DEFAULT '0',
  categoryid INT(11) DEFAULT NULL,
  regexid INT(11) DEFAULT NULL,
  reqid VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  relpart INT(11) DEFAULT '0',
  reltotalpart INT(11) DEFAULT '0',
  binaryhash VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL,
  relname VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  releaseid INT(11) DEFAULT NULL,
  dateadded DATETIME DEFAULT NULL,
  PRIMARY KEY (ID),
  KEY fromname (fromname),
  KEY date (date),
  KEY groupid (groupid),
  KEY ix_binary_relname (relname),
  KEY ix_binary_releaseid (releaseid),
  KEY ix_binary_dateadded (dateadded),
  KEY ix_binary_binaryhash (binaryhash),
  KEY ix_binary_releaseid_relpart (releaseid,relpart),
  KEY ix_binary_procstat (procstat)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT = 1;

DROP TABLE IF EXISTS binaryblacklist;
CREATE TABLE IF NOT EXISTS binaryblacklist (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  groupname VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  regex VARCHAR(2000) COLLATE utf8_unicode_ci NOT NULL,
  msgcol INT(11) UNSIGNED NOT NULL DEFAULT '1',
  optype INT(11) UNSIGNED NOT NULL DEFAULT '1',
  status INT(11) UNSIGNED NOT NULL DEFAULT '1',
  description VARCHAR(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  last_activity DATE DEFAULT NULL,
  PRIMARY KEY (id)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT = 100001;

INSERT INTO binaryblacklist (id, groupname, regex, msgcol, optype, status, description, last_activity) VALUES
(1, 'alt.binaries.*', 'german|danish|flemish|dutch|french|swedish|swesub|deutsch|nl\\.?sub|norwegian|\\.ita\\.', 1, 1, 0, 'do not index non-english language binaries', NULL),
(12, '^alt\\.binaries\\.teevee$', '^\\[KoreanTV\\]', 1, 1, 0, 'Blocks posts by koreantv.', NULL),
(13, '^alt\\.binaries\\.(kenpsx|frogs)$', '^\\s*([a-f0-9]{16})\\s\\[\\d+\\/\\d+\\]\\s-\\s"\\1"\\s+yEnc$', 1, 1, 1, 'Block 16 character hash floods in kenpsx, frogs.', NULL),
(14, '^alt\\.binaries\\.multimedia\\.korean$', 'TESTMAN', 2, 1, 1, 'Posts by TESTMAN (jpegs)', NULL),
(15, '^alt\\.binaries\\.multimedia\\.korean$', '^yEnc ".+torrent"$', 1, 1, 1, 'torrent uploads ::: yEnc "SBS ÃÃÂ±Ã¢Â°Â¡Â¿Ã¤.E690.120916.HDTV.H264.720p-KOR.avi.torrent"', NULL),
(16, '^korea\\.binaries\\.movies$', '^.[?(Kornet|SK|xpeed|KT)]?', 1, 1, 1, 'Incomplete releases', NULL),
(17, '^korea\\.binaries\\.movies$', '^(top@top.t \\(top\\)|shit@xxxxxxxxaa.com \\(shit\\)|none@nonemail.com \\(none\\))$', 2, 1, 1, 'incomplete cryptic releases', NULL),
(18, '^korea\\.binaries\\.movies$', '^filzilla6@web\\.de \\(Baruth\\)$', 2, 1, 1, 'Virus Poster', NULL);

DROP TABLE IF EXISTS bookinfo;
CREATE TABLE IF NOT EXISTS bookinfo (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL,
  asin VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  url VARCHAR(1000) COLLATE utf8_unicode_ci DEFAULT NULL,
  author VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  publisher VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  publishdate DATETIME DEFAULT NULL,
  review VARCHAR(10000) COLLATE utf8_unicode_ci DEFAULT NULL,
  genreid INT(10) DEFAULT NULL,
  dewey VARCHAR(10) COLLATE utf8_unicode_ci DEFAULT NULL,
  ean VARCHAR(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  isbn VARCHAR(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  pages INT(10) DEFAULT NULL,
  cover TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
  createddate DATETIME NOT NULL,
  updateddate DATETIME NOT NULL,
  salesrank INT(10) UNSIGNED DEFAULT NULL,
  overview VARCHAR(3000) COLLATE utf8_unicode_ci DEFAULT NULL,
  genre VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (id),
  KEY ix_bookinfo_title (title),
  FULLTEXT KEY ix_bookinfo_author_title_ft (author,title)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT = 1;

DROP TABLE IF EXISTS category;
CREATE TABLE IF NOT EXISTS category (
  id INT(11) NOT NULL AUTO_INCREMENT,
  title VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL,
  parentid INT(11) DEFAULT NULL,
  status INT(11) NOT NULL DEFAULT '1',
  minsizetoformrelease BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
  maxsizetoformrelease BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
  description VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  disablepreview TINYINT(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (id)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT  =  1000001;

INSERT INTO category (id, title, parentid, status, minsizetoformrelease, maxsizetoformrelease, description, disablepreview) VALUES
(1000, 'Console', NULL, 1, 0, 0, NULL, 0),
(1010, 'NDS', 1000, 1, 0, 0, NULL, 0),
(1020, 'PSP', 1000, 1, 0, 0, NULL, 0),
(1030, 'Wii', 1000, 1, 0, 0, NULL, 0),
(1040, 'Xbox', 1000, 1, 0, 0, NULL, 0),
(1050, 'Xbox 360', 1000, 1, 0, 0, NULL, 0),
(1060, 'WiiWare/VC', 1000, 1, 0, 0, NULL, 0),
(1070, 'XBOX 360 DLC', 1000, 1, 0, 0, NULL, 0),
(1080, 'PS3', 1000, 1, 0, 0, NULL, 0),
(1090, 'Other', 1000, 1, 0, 0, NULL, 0),
(1110, '3DS', 1000, 1, 0, 0, NULL, 0),
(1120, 'PS Vita', 1000, 1, 0, 0, NULL, 0),
(1130, 'WiiU', 1000, 1, 0, 0, NULL, 0),
(1140, 'Xbox One', 1000, 1, 0, 0, NULL, 0),
(1180, 'PS4', 1000, 1, 0, 0, NULL, 0),
(2000, 'Movies', NULL, 1, 0, 0, NULL, 0),
(2010, 'Foreign', 2000, 1, 0, 0, NULL, 0),
(2020, 'Other', 2000, 1, 0, 0, NULL, 0),
(2030, 'SD', 2000, 1, 0, 0, NULL, 0),
(2040, 'HD', 2000, 1, 0, 0, NULL, 0),
(2050, '3D', 2000, 1, 0, 0, NULL, 0),
(2060, 'BluRay', 2000, 1, 0, 0, NULL, 0),
(2070, 'DVD', 2000, 1, 0, 0, NULL, 0),
(2080, 'WEB-DL', 2000, 1, 0, 0, NULL, 0),
(3000, 'Audio', NULL, 1, 0, 0, NULL, 0),
(3010, 'MP3', 3000, 1, 0, 0, NULL, 0),
(3020, 'Video', 3000, 1, 0, 0, NULL, 0),
(3030, 'Audiobook', 3000, 1, 0, 0, NULL, 0),
(3040, 'Lossless', 3000, 1, 0, 0, NULL, 0),
(3050, 'Other', 3000, 1, 0, 0, NULL, 0),
(3060, 'Foreign', 3000, 1, 0, 0, NULL, 0),
(4000, 'PC', NULL, 1, 0, 0, NULL, 0),
(4010, '0day', 4000, 1, 0, 0, NULL, 0),
(4020, 'ISO', 4000, 1, 0, 0, NULL, 0),
(4030, 'Mac', 4000, 1, 0, 0, NULL, 0),
(4040, 'Mobile-Other', 4000, 1, 0, 0, NULL, 0),
(4050, 'Games', 4000, 1, 0, 0, NULL, 0),
(4060, 'Mobile-iOS', 4000, 1, 0, 0, NULL, 0),
(4070, 'Mobile-Android', 4000, 1, 0, 0, NULL, 0),
(5000, 'TV', NULL, 1, 0, 0, NULL, 0),
(5010, 'WEB-DL', 5000, 1, 0, 0, NULL, 0),
(5020, 'Foreign', 5000, 1, 0, 0, NULL, 0),
(5030, 'SD', 5000, 1, 0, 0, NULL, 0),
(5040, 'HD', 5000, 1, 0, 0, NULL, 0),
(5050, 'Other', 5000, 1, 0, 0, NULL, 0),
(5060, 'Sport', 5000, 1, 0, 0, NULL, 0),
(5070, 'Anime', 5000, 1, 0, 0, NULL, 0),
(5080, 'Documentary', 5000, 1, 0, 0, NULL, 0),
(6000, 'XXX', NULL, 1, 0, 0, NULL, 0),
(6010, 'DVD', 6000, 1, 0, 0, NULL, 0),
(6020, 'WMV', 6000, 1, 0, 0, NULL, 0),
(6030, 'XviD', 6000, 1, 0, 0, NULL, 0),
(6040, 'x264', 6000, 1, 0, 0, NULL, 0),
(6041, 'HD Clips', 6000, 1, 0, 0, NULL, 0),
(6042, 'SD Clips', 6000, 1, 0, 0, NULL, 0),
(6050, 'Pack', 6000, 1, 0, 0, NULL, 0),
(6060, 'ImgSet', 6000, 1, 0, 0, NULL, 0),
(6070, 'Other', 6000, 1, 0, 0, NULL, 0),
(6080, 'SD', 6000, 1, 0, 0, NULL, 0),
(6090, 'WEB-DL', 6000, 1, 0, 0, NULL, 0),
(7000, 'Books', NULL, 1, 0, 0, NULL, 0),
(7010, 'Mags', 7000, 1, 0, 0, NULL, 0),
(7020, 'Ebook', 7000, 1, 0, 0, NULL, 0),
(7030, 'Comics', 7000, 1, 0, 0, NULL, 0),
(7040, 'Technical', 7000, 1, 0, 0, NULL, 0),
(7050, 'Other', 7000, 1, 0, 0, NULL, 0),
(7060, 'Foreign', 7000, 1, 0, 0, NULL, 0),
(8000, 'Other', NULL, 1, 0, 0, NULL, 0),
(8010, 'Misc', 8000, 1, 0, 0, NULL, 0),
(8020, 'Hashed', 8000, 1, 0, 0, NULL, 0);

DROP TABLE IF EXISTS consoleinfo;
CREATE TABLE IF NOT EXISTS consoleinfo (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL,
  asin VARCHAR(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  url VARCHAR(1000) COLLATE utf8_unicode_ci DEFAULT NULL,
  salesrank INT(10) UNSIGNED DEFAULT NULL,
  platform VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  publisher VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  genreid INT(10) DEFAULT NULL,
  esrb VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  releasedate DATETIME DEFAULT NULL,
  review VARCHAR(10000) COLLATE utf8_unicode_ci DEFAULT NULL,
  cover TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
  createddate DATETIME NOT NULL,
  updateddate DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_consoleinfo_title (title),
  FULLTEXT KEY ix_consoleinfo_title_platform_ft (title,platform)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT = 1;

DROP TABLE IF EXISTS content;
CREATE TABLE IF NOT EXISTS content (
  id INT(11) NOT NULL AUTO_INCREMENT,
  title VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL,
  url VARCHAR(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
  body TEXT COLLATE utf8_unicode_ci,
  metadescription VARCHAR(1000) COLLATE utf8_unicode_ci NOT NULL,
  metakeywords VARCHAR(1000) COLLATE utf8_unicode_ci NOT NULL,
  contenttype INT(11) NOT NULL,
  showinmenu INT(11) NOT NULL,
  status INT(11) NOT NULL,
  ordinal INT(11) DEFAULT NULL,
  role INT(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (id)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT  =  1000001;

   INSERT INTO `content` (`id`, `title`, `url`, `body`, `metadescription`, `metakeywords`, `contenttype`, `showinmenu`, `status`, `ordinal`, `role`) VALUES
(1, 'welcome to newznab', '/', '<div class="alert alert-info">\r\n<h4><i class="fa fa-info"></i>  This is Newznab-tmux testing site</h4>\r\n<p> It is reset from time to time , so don''t be mad :) </p>\r\n</div>', '', '', 3, 0, 1, 0, 0),
(2, 'Example Content', '/great/seo/content/page/', '<p>this is an example content page</p><p>Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p><p>Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>', '', '', 2, 1, 1, NULL, 0),
(3, 'Another Example', '/another/great/seo/content/page/', '<p>this is another example content page</p><p>Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p><p>Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>', '', '', 2, 1, 1, NULL, 0);

DROP TABLE IF EXISTS countries;
CREATE TABLE IF NOT EXISTS countries (
  id CHAR(2) COLLATE utf8_unicode_ci NOT NULL COMMENT '2 character code.',
  iso3 CHAR(3) COLLATE utf8_unicode_ci NOT NULL COMMENT '3 character code.',
  country VARCHAR(180) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Name of the country.',
  PRIMARY KEY (id),
  UNIQUE KEY code3 (iso3),
  UNIQUE KEY country (country)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci;

INSERT INTO countries (id, iso3, country) VALUES
('AF', 'AFG', 'Islamic Republic of Afghanistan'),
('AX', 'ALA', 'Åland Islands'),
('AL', 'ALB', 'Republic of Albania'),
('DZ', 'DZA', 'People''s Democratic Republic of Algeria'),
('AS', 'ASM', 'American Samoa'),
('AD', 'AND', 'Principality of Andorra'),
('AO', 'AGO', 'Republic of Angola'),
('AI', 'AIA', 'Anguilla'),
('AQ', 'ATA', 'Antarctica'),
('AG', 'ATG', 'Antigua and Barbuda'),
('AR', 'ARG', 'Argentine Republic'),
('AM', 'ARM', 'Republic of Armenia'),
('AW', 'ABW', 'Aruba'),
('AU', 'AUS', 'Commonwealth of Australia'),
('AT', 'AUT', 'Republic of Austria'),
('AZ', 'AZE', 'Republic of Azerbaijan'),
('BS', 'BHS', 'Commonwealth of The Bahamas'),
('BH', 'BHR', 'Kingdom of Bahrain'),
('BD', 'BGD', 'People''s Republic of Bangladesh'),
('BB', 'BRB', 'Barbados'),
('BY', 'BLR', 'Republic of Belarus'),
('BE', 'BEL', 'Kingdom of Belgium'),
('BZ', 'BLZ', 'Belize'),
('BJ', 'BEN', 'Republic of Benin'),
('BM', 'BMU', 'Bermuda Islands'),
('BT', 'BTN', 'Kingdom of Bhutan'),
('BO', 'BOL', 'Plurinational State of Bolivia'),
('BQ', 'BES', 'Bonaire, Sint Eustatius AND Saba'),
('BA', 'BIH', 'Bosnia AND Herzegovina'),
('BW', 'BWA', 'Republic of Botswana'),
('BV', 'BVT', 'Bouvet Island'),
('BR', 'BRA', 'Federative Republic of Brazil'),
('IO', 'IOT', 'British Indian Ocean Territory'),
('BN', 'BRN', 'Brunei Darussalam'),
('BG', 'BGR', 'Republic of Bulgaria'),
('BF', 'BFA', 'Burkina Faso'),
('BI', 'BDI', 'Republic of Burundi'),
('KH', 'KHM', 'Kingdom of Cambodia'),
('CM', 'CMR', 'Republic of Cameroon'),
('CA', 'CAN', 'Canada'),
('CV', 'CPV', 'Republic of Cape Verde'),
('KY', 'CYM', 'The Cayman Islands'),
('CF', 'CAF', 'Central African Republic'),
('TD', 'TCD', 'Republic of Chad'),
('CL', 'CHL', 'Republic of Chile'),
('CN', 'CHN', 'People''s Republic of China'),
('CX', 'CXR', 'Christmas Island'),
('CC', 'CCK', 'Cocos (Keeling) Islands'),
('CO', 'COL', 'Republic of Colombia'),
('KM', 'COM', 'Union of the Comoros'),
('CG', 'COG', 'Republic of the Congo'),
('CK', 'COK', 'Cook Islands'),
('CR', 'CRI', 'Republic of Costa Rica'),
('CI', 'CIV', 'Republic of Côte D''Ivoire (Ivory Coast)'),
('HR', 'HRV', 'Republic of Croatia'),
('CU', 'CUB', 'Republic of Cuba'),
('CW', 'CUW', 'Curaçao'),
('CY', 'CYP', 'Republic of Cyprus'),
('CZ', 'CZE', 'Czech Republic'),
('CD', 'COD', 'Democratic Republic of the Congo'),
('DK', 'DNK', 'Kingdom of Denmark'),
('DJ', 'DJI', 'Republic of Djibouti'),
('DM', 'DMA', 'Commonwealth of Dominica'),
('DO', 'DOM', 'Dominican Republic'),
('EC', 'ECU', 'Republic of Ecuador'),
('EG', 'EGY', 'Arab Republic of Egypt'),
('SV', 'SLV', 'Republic of El Salvador'),
('GQ', 'GNQ', 'Republic of Equatorial Guinea'),
('ER', 'ERI', 'State of Eritrea'),
('EE', 'EST', 'Republic of Estonia'),
('ET', 'ETH', 'Federal Democratic Republic of Ethiopia'),
('FK', 'FLK', 'The Falkland Islands (Malvinas)'),
('FO', 'FRO', 'The Faroe Islands'),
('FJ', 'FJI', 'Republic of Fiji'),
('FI', 'FIN', 'Republic of Finland'),
('FR', 'FRA', 'French Republic'),
('GF', 'GUF', 'French Guiana'),
('PF', 'PYF', 'French Polynesia'),
('TF', 'ATF', 'French Southern Territories'),
('GA', 'GAB', 'Gabonese Republic'),
('GM', 'GMB', 'Republic of The Gambia'),
('GE', 'GEO', 'Georgia'),
('DE', 'DEU', 'Federal Republic of Germany'),
('GH', 'GHA', 'Republic of Ghana'),
('GI', 'GIB', 'Gibraltar'),
('GR', 'GRC', 'Hellenic Republic'),
('GL', 'GRL', 'Greenland'),
('GD', 'GRD', 'Grenada'),
('GP', 'GLP', 'Guadeloupe'),
('GU', 'GUM', 'Guam'),
('GT', 'GTM', 'Republic of Guatemala'),
('GG', 'GGY', 'Guernsey'),
('GN', 'GIN', 'Republic of Guinea'),
('GW', 'GNB', 'Republic of Guinea-Bissau'),
('GY', 'GUY', 'Co-operative Republic of Guyana'),
('HT', 'HTI', 'Republic of Haiti'),
('HM', 'HMD', 'Heard Island AND McDonald Islands'),
('HN', 'HND', 'Republic of Honduras'),
('HK', 'HKG', 'Hong Kong'),
('HU', 'HUN', 'Hungary'),
('IS', 'ISL', 'Republic of Iceland'),
('IN', 'IND', 'Republic of India'),
('ID', 'IDN', 'Republic of Indonesia'),
('IR', 'IRN', 'Islamic Republic of Iran'),
('IQ', 'IRQ', 'Republic of Iraq'),
('IE', 'IRL', 'Ireland'),
('IM', 'IMN', 'Isle of Man'),
('IL', 'ISR', 'State of Israel'),
('IT', 'ITA', 'Italian Republic'),
('JM', 'JAM', 'Jamaica'),
('JP', 'JPN', 'Japan'),
('JE', 'JEY', 'The Bailiwick of Jersey'),
('JO', 'JOR', 'Hashemite Kingdom of Jordan'),
('KZ', 'KAZ', 'Republic of Kazakhstan'),
('KE', 'KEN', 'Republic of Kenya'),
('KI', 'KIR', 'Republic of Kiribati'),
('XK', 'XKX', 'Republic of Kosovo'),
('KW', 'KWT', 'State of Kuwait'),
('KG', 'KGZ', 'Kyrgyz Republic'),
('LA', 'LAO', 'Lao People''s Democratic Republic'),
('LV', 'LVA', 'Republic of Latvia'),
('LB', 'LBN', 'Republic of Lebanon'),
('LS', 'LSO', 'Kingdom of Lesotho'),
('LR', 'LBR', 'Republic of Liberia'),
('LY', 'LBY', 'Libya'),
('LI', 'LIE', 'Principality of Liechtenstein'),
('LT', 'LTU', 'Republic of Lithuania'),
('LU', 'LUX', 'Grand Duchy of Luxembourg'),
('MO', 'MAC', 'The Macao Special Administrative Region'),
('MK', 'MKD', 'The Former Yugoslav Republic of Macedonia'),
('MG', 'MDG', 'Republic of Madagascar'),
('MW', 'MWI', 'Republic of Malawi'),
('MY', 'MYS', 'Malaysia'),
('MV', 'MDV', 'Republic of Maldives'),
('ML', 'MLI', 'Republic of Mali'),
('MT', 'MLT', 'Republic of Malta'),
('MH', 'MHL', 'Republic of the Marshall Islands'),
('MQ', 'MTQ', 'Martinique'),
('MR', 'MRT', 'Islamic Republic of Mauritania'),
('MU', 'MUS', 'Republic of Mauritius'),
('YT', 'MYT', 'Mayotte'),
('MX', 'MEX', 'United Mexican States'),
('FM', 'FSM', 'Federated States of Micronesia'),
('MD', 'MDA', 'Republic of Moldova'),
('MC', 'MCO', 'Principality of Monaco'),
('MN', 'MNG', 'Mongolia'),
('ME', 'MNE', 'Montenegro'),
('MS', 'MSR', 'Montserrat'),
('MA', 'MAR', 'Kingdom of Morocco'),
('MZ', 'MOZ', 'Republic of Mozambique'),
('MM', 'MMR', 'Republic of the Union of Myanmar'),
('NA', 'NAM', 'Republic of Namibia'),
('NR', 'NRU', 'Republic of Nauru'),
('NP', 'NPL', 'Federal Democratic Republic of Nepal'),
('NL', 'NLD', 'Kingdom of the Netherlands'),
('NC', 'NCL', 'New Caledonia'),
('NZ', 'NZL', 'New Zealand'),
('NI', 'NIC', 'Republic of Nicaragua'),
('NE', 'NER', 'Republic of Niger'),
('NG', 'NGA', 'Federal Republic of Nigeria'),
('NU', 'NIU', 'Niue'),
('NF', 'NFK', 'Norfolk Island'),
('KP', 'PRK', 'Democratic People''s Republic of Korea'),
('MP', 'MNP', 'Northern Mariana Islands'),
('NO', 'NOR', 'Kingdom of Norway'),
('OM', 'OMN', 'Sultanate of Oman'),
('PK', 'PAK', 'Islamic Republic of Pakistan'),
('PW', 'PLW', 'Republic of Palau'),
('PS', 'PSE', 'State of Palestine (OR Occupied Palestinian Territory)'),
('PA', 'PAN', 'Republic of Panama'),
('PG', 'PNG', 'Independent State of Papua New Guinea'),
('PY', 'PRY', 'Republic of Paraguay'),
('PE', 'PER', 'Republic of Peru'),
('PH', 'PHL', 'Republic of the Philippines'),
('PN', 'PCN', 'Pitcairn'),
('PL', 'POL', 'Republic of Poland'),
('PT', 'PRT', 'Portuguese Republic'),
('PR', 'PRI', 'Commonwealth of Puerto Rico'),
('QA', 'QAT', 'State of Qatar'),
('RE', 'REU', 'Réunion'),
('RO', 'ROU', 'Romania'),
('RU', 'RUS', 'Russian Federation'),
('RW', 'RWA', 'Republic of Rwanda'),
('BL', 'BLM', 'Saint Barthélemy'),
('SH', 'SHN', 'Saint Helena, Ascension AND Tristan da Cunha'),
('KN', 'KNA', 'Federation of Saint Christopher AND Nevis'),
('LC', 'LCA', 'Saint Lucia'),
('MF', 'MAF', 'Saint Martin'),
('PM', 'SPM', 'Saint Pierre AND Miquelon'),
('VC', 'VCT', 'Saint Vincent AND the Grenadines'),
('WS', 'WSM', 'Independent State of Samoa'),
('SM', 'SMR', 'Republic of San Marino'),
('ST', 'STP', 'Democratic Republic of São Tomé AND Príncipe'),
('SA', 'SAU', 'Kingdom of Saudi Arabia'),
('SN', 'SEN', 'Republic of Senegal'),
('RS', 'SRB', 'Republic of Serbia'),
('SC', 'SYC', 'Republic of Seychelles'),
('SL', 'SLE', 'Republic of Sierra Leone'),
('SG', 'SGP', 'Republic of Singapore'),
('SX', 'SXM', 'Sint Maarten'),
('SK', 'SVK', 'Slovak Republic'),
('SI', 'SVN', 'Republic of Slovenia'),
('SB', 'SLB', 'Solomon Islands'),
('SO', 'SOM', 'Somali Republic'),
('ZA', 'ZAF', 'Republic of South Africa'),
('GS', 'SGS', 'South Georgia AND the South Sandwich Islands'),
('KR', 'KOR', 'Republic of Korea'),
('SS', 'SSD', 'Republic of South Sudan'),
('ES', 'ESP', 'Kingdom of Spain'),
('LK', 'LKA', 'Democratic Socialist Republic of Sri Lanka'),
('SD', 'SDN', 'Republic of the Sudan'),
('SR', 'SUR', 'Republic of Suriname'),
('SJ', 'SJM', 'Svalbard AND Jan Mayen'),
('SZ', 'SWZ', 'Kingdom of Swaziland'),
('SE', 'SWE', 'Kingdom of Sweden'),
('CH', 'CHE', 'Swiss Confederation'),
('SY', 'SYR', 'Syrian Arab Republic'),
('TW', 'TWN', 'Republic of China (Taiwan)'),
('TJ', 'TJK', 'Republic of Tajikistan'),
('TZ', 'TZA', 'United Republic of Tanzania'),
('TH', 'THA', 'Kingdom of Thailand'),
('TL', 'TLS', 'Democratic Republic of Timor-Leste'),
('TG', 'TGO', 'Togolese Republic'),
('TK', 'TKL', 'Tokelau'),
('TO', 'TON', 'Kingdom of Tonga'),
('TT', 'TTO', 'Republic of Trinidad AND Tobago'),
('TN', 'TUN', 'Republic of Tunisia'),
('TR', 'TUR', 'Republic of Turkey'),
('TM', 'TKM', 'Turkmenistan'),
('TC', 'TCA', 'Turks AND Caicos Islands'),
('TV', 'TUV', 'Tuvalu'),
('UG', 'UGA', 'Republic of Uganda'),
('UA', 'UKR', 'Ukraine'),
('AE', 'ARE', 'United Arab Emirates'),
('GB', 'GBR', 'United Kingdom of Great Britain AND Nothern Ireland'),
('US', 'USA', 'United States of America'),
('UM', 'UMI', 'United States Minor Outlying Islands'),
('UY', 'URY', 'Eastern Republic of Uruguay'),
('UZ', 'UZB', 'Republic of Uzbekistan'),
('VU', 'VUT', 'Republic of Vanuatu'),
('VA', 'VAT', 'State of the Vatican City'),
('VE', 'VEN', 'Bolivarian Republic of Venezuela'),
('VN', 'VNM', 'Socialist Republic of Vietnam'),
('VG', 'VGB', 'British Virgin Islands'),
('VI', 'VIR', 'Virgin Islands of the United States'),
('WF', 'WLF', 'Wallis AND Futuna'),
('EH', 'ESH', 'Western Sahara'),
('YE', 'YEM', 'Republic of Yemen'),
('ZM', 'ZMB', 'Republic of Zambia'),
('ZW', 'ZWE', 'Republic of Zimbabwe');

DROP TABLE IF EXISTS dnzb_failures;
CREATE TABLE IF NOT EXISTS dnzb_failures (
  release_id INT(11) UNSIGNED NOT NULL,
  userid INT(11) UNSIGNED NOT NULL,
  failed INT(10) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (release_id, userid)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci;

DROP TABLE IF EXISTS episodeinfo;
CREATE TABLE IF NOT EXISTS episodeinfo (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  rageid INT(11) UNSIGNED DEFAULT NULL,
  tvdbid INT(11) UNSIGNED DEFAULT NULL,
  imdbid MEDIUMINT(7) UNSIGNED zerofill DEFAULT NULL,
  showtitle VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  airdate DATETIME NOT NULL,
  link VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  fullep VARCHAR(20) COLLATE utf8_unicode_ci NOT NULL,
  eptitle VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  director VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  gueststars VARCHAR(1024) COLLATE utf8_unicode_ci DEFAULT NULL,
  overview VARCHAR(10000) COLLATE utf8_unicode_ci DEFAULT NULL,
  rating FLOAT DEFAULT NULL,
  writer VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  epabsolute INT(6) DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY fullep (showtitle,fullep),
  KEY ix_episodeinfo_rageid (rageid),
  KEY ix_episodeinfo_tvdbid (tvdbid),
  KEY ix_episodeinfo_imdbid (imdbid)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT = 1;

DROP TABLE IF EXISTS forumpost;
CREATE TABLE IF NOT EXISTS forumpost (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  forumid INT(11) DEFAULT NULL,
  parentid INT(11) DEFAULT NULL,
  userid INT(11) UNSIGNED DEFAULT NULL,
  subject VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL,
  message TEXT COLLATE utf8_unicode_ci NOT NULL,
  locked TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
  sticky TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
  replies INT(11) UNSIGNED NOT NULL DEFAULT '0',
  createddate DATETIME NOT NULL,
  updateddate DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY parentid (parentid),
  KEY userid (userid),
  KEY createddate (createddate),
  KEY updateddate (updateddate)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT = 1;

DROP TABLE IF EXISTS gamesinfo;
CREATE TABLE IF NOT EXISTS gamesinfo (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL,
  asin VARCHAR(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  url VARCHAR(1000) COLLATE utf8_unicode_ci DEFAULT NULL,
  platform VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  publisher VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  genre_id INT(10) DEFAULT NULL,
  esrb VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  releasedate DATETIME DEFAULT NULL,
  review VARCHAR(3000) COLLATE utf8_unicode_ci DEFAULT NULL,
  cover TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
  backdrop TINYINT(1) DEFAULT '0',
  trailer VARCHAR(1000) COLLATE utf8_unicode_ci DEFAULT '',
  classused VARCHAR(10) COLLATE utf8_unicode_ci DEFAULT 'steam',
  createddate DATETIME NOT NULL,
  updateddate DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY ix_gamesinfo_asin (asin)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT = 1;

DROP TABLE IF EXISTS genres;
CREATE TABLE IF NOT EXISTS genres (
  id INT(11) NOT NULL AUTO_INCREMENT,
  title VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL,
  type INT(4) DEFAULT NULL,
  disabled TINYINT(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (id)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT  =  1000001;

INSERT INTO genres (id, title, type, disabled) VALUES
(150, 'Blues', 3000, 0),
(151, 'Classic Rock', 3000, 0),
(152, 'Country', 3000, 0),
(153, 'Dance', 3000, 0),
(154, 'Disco', 3000, 0),
(155, 'Funk', 3000, 0),
(156, 'Grunge', 3000, 0),
(157, 'Hip-Hop', 3000, 0),
(158, 'Jazz', 3000, 0),
(159, 'Metal', 3000, 0),
(160, 'New Age', 3000, 0),
(161, 'Oldies', 3000, 0),
(162, 'Other', 3000, 0),
(163, 'Pop', 3000, 0),
(164, 'R&B', 3000, 0),
(165, 'Rap', 3000, 0),
(166, 'Reggae', 3000, 0),
(167, 'Rock', 3000, 0),
(168, 'Techno', 3000, 0),
(169, 'Industrial', 3000, 0),
(170, 'Alternative', 3000, 0),
(171, 'Ska', 3000, 0),
(172, 'Death Metal', 3000, 0),
(173, 'Pranks', 3000, 0),
(174, 'Soundtrack', 3000, 0),
(175, 'Euro-Techno', 3000, 0),
(176, 'Ambient', 3000, 0),
(177, 'Trip-Hop', 3000, 0),
(178, 'Vocal', 3000, 0),
(179, 'Jazz+Funk', 3000, 0),
(180, 'Fusion', 3000, 0),
(181, 'Trance', 3000, 0),
(182, 'Classical', 3000, 0),
(183, 'Instrumental', 3000, 0),
(184, 'Acid', 3000, 0),
(185, 'House', 3000, 0),
(186, 'Game', 3000, 0),
(187, 'Sound Clip', 3000, 0),
(188, 'Gospel', 3000, 0),
(189, 'Noise', 3000, 0),
(190, 'Alternative Rock', 3000, 0),
(191, 'Bass', 3000, 0),
(192, 'Soul', 3000, 0),
(193, 'Punk', 3000, 0),
(194, 'Space', 3000, 0),
(195, 'Meditative', 3000, 0),
(196, 'Instrumental Pop', 3000, 0),
(197, 'Instrumental Rock', 3000, 0),
(198, 'Ethnic', 3000, 0),
(199, 'Gothic', 3000, 0),
(200, 'Darkwave', 3000, 0),
(201, 'Techno-Industrial', 3000, 0),
(202, 'Electronic', 3000, 0),
(203, 'Pop-Folk', 3000, 0),
(204, 'Eurodance', 3000, 0),
(205, 'Dream', 3000, 0),
(206, 'Southern Rock', 3000, 0),
(207, 'Comedy', 3000, 0),
(208, 'Cult', 3000, 0),
(209, 'Gangsta', 3000, 0),
(210, 'Top 40', 3000, 0),
(211, 'Christian Rap', 3000, 0),
(212, 'Pop/Funk', 3000, 0),
(213, 'Jungle', 3000, 0),
(214, 'Native US', 3000, 0),
(215, 'Cabaret', 3000, 0),
(216, 'New Wave', 3000, 0),
(217, 'Psychadelic', 3000, 0),
(218, 'Rave', 3000, 0),
(219, 'Showtunes', 3000, 0),
(220, 'Trailer', 3000, 0),
(221, 'Lo-Fi', 3000, 0),
(222, 'Tribal', 3000, 0),
(223, 'Acid Punk', 3000, 0),
(224, 'Acid Jazz', 3000, 0),
(225, 'Polka', 3000, 0),
(226, 'Retro', 3000, 0),
(227, 'Musical', 3000, 0),
(228, 'Rock & Roll', 3000, 0),
(229, 'Hard Rock', 3000, 0),
(230, 'Folk', 3000, 0),
(231, 'Folk-Rock', 3000, 0),
(232, 'National Folk', 3000, 0),
(233, 'Swing', 3000, 0),
(234, 'Fast Fusion', 3000, 0),
(235, 'Bebob', 3000, 0),
(236, 'Latin', 3000, 0),
(237, 'Revival', 3000, 0),
(238, 'Celtic', 3000, 0),
(239, 'Bluegrass', 3000, 0),
(240, 'Avantgarde', 3000, 0),
(241, 'Gothic Rock', 3000, 0),
(242, 'Progressive Rock', 3000, 0),
(243, 'Psychedelic Rock', 3000, 0),
(244, 'Symphonic Rock', 3000, 0),
(245, 'Slow Rock', 3000, 0),
(246, 'Big Band', 3000, 0),
(247, 'Chorus', 3000, 0),
(248, 'Easy Listening', 3000, 0),
(249, 'Acoustic', 3000, 0),
(250, 'Humour', 3000, 0),
(251, 'Speech', 3000, 0),
(252, 'Chanson', 3000, 0),
(253, 'Opera', 3000, 0),
(254, 'Chamber Music', 3000, 0),
(255, 'Sonata', 3000, 0),
(256, 'Symphony', 3000, 0),
(257, 'Booty Bass', 3000, 0),
(258, 'Primus', 3000, 0),
(259, 'Porn Groove', 3000, 0),
(260, 'Satire', 3000, 0),
(261, 'Slow Jam', 3000, 0),
(262, 'Club', 3000, 0),
(263, 'Tango', 3000, 0),
(264, 'Samba', 3000, 0),
(265, 'Folklore', 3000, 0),
(266, 'Ballad', 3000, 0),
(267, 'Power Ballad', 3000, 0),
(268, 'Rhytmic Soul', 3000, 0),
(269, 'Freestyle', 3000, 0),
(270, 'Duet', 3000, 0),
(271, 'Punk Rock', 3000, 0),
(272, 'Drum Solo', 3000, 0),
(273, 'Acapella', 3000, 0),
(274, 'Euro-House', 3000, 0),
(275, 'Dance Hall', 3000, 0),
(276, 'Goa', 3000, 0),
(277, 'Drum & Bass', 3000, 0),
(278, 'Club-House', 3000, 0),
(279, 'Hardcore', 3000, 0),
(280, 'Terror', 3000, 0),
(281, 'Indie', 3000, 0),
(282, 'BritPop', 3000, 0),
(283, 'Negerpunk', 3000, 0),
(284, 'Polsk Punk', 3000, 0),
(285, 'Beat', 3000, 0),
(286, 'Christian Gangsta', 3000, 0),
(287, 'Heavy Metal', 3000, 0),
(288, 'Black Metal', 3000, 0),
(289, 'Crossover', 3000, 0),
(290, 'Contemporary C', 3000, 0),
(291, 'Christian Rock', 3000, 0),
(292, 'Merengue', 3000, 0),
(293, 'Salsa', 3000, 0),
(294, 'Thrash Metal', 3000, 0),
(295, 'Anime', 3000, 0),
(296, 'JPop', 3000, 0),
(297, 'SynthPop', 3000, 0),
(298, 'Electronica', 3000, 0),
(299, 'World Music', 3000, 0),
(300, 'Miscellaneous', 3000, 0),
(301, 'Rap & Hip-Hop', 3000, 0),
(302, 'Dance & Electronic', 3000, 0),
(303, 'Adventure', 1000, 0),
(304, 'Hard Rock & Metal', 3000, 0),
(305, 'Broadway & Vocalists', 3000, 0),
(306, 'Unknown', 1000, 0),
(307, 'Christian & Gospel', 3000, 0),
(308, '', 3000, 0),
(309, 'Action', 1000, 0),
(310, 'Unknown', 4000, 0),
(311, 'Action', 4000, 0),
(312, 'Strategy', 4000, 0),
(313, 'Puzzle', 4000, 0),
(314, 'Adventure', 4000, 0),
(315, 'Sports', 4000, 0),
(316, 'Simulation', 4000, 0),
(317, 'Anal', 6000, 0),
(318, 'Big Butt', 6000, 0),
(319, 'Gonzo', 6000, 0),
(320, 'Oiled', 6000, 0),
(321, 'Widescreen', 6000, 0),
(322, '18+ Teens', 6000, 0),
(323, 'Big Cocks', 6000, 0),
(324, 'Blu-Ray', 6000, 0),
(325, 'Feature', 6000, 0),
(326, 'Prison', 6000, 0),
(327, 'All Sex', 6000, 0),
(328, 'Couples', 6000, 0),
(329, 'European', 6000, 0),
(330, 'Foreign', 6000, 0),
(331, 'Point Of View', 6000, 0),
(332, 'Threesomes', 6000, 0),
(333, 'College', 6000, 0),
(334, 'Teachers', 6000, 0),
(335, 'Older Men', 6000, 0),
(336, 'Rimming', 6000, 0),
(337, 'Glory Hole', 6000, 0),
(338, 'Blowjobs', 6000, 0),
(339, 'Big Budget', 6000, 0),
(340, 'Gaping', 6000, 0),
(341, 'Sex Toy Play', 6000, 0),
(342, 'Cumshots', 6000, 0),
(343, 'Gangbang', 6000, 0),
(344, 'Fetish', 6000, 0),
(345, 'Transsexual', 6000, 0),
(346, 'Big Boobs', 6000, 0),
(347, 'Mature', 6000, 0),
(348, 'MILF', 6000, 0),
(349, 'All Girl / Lesbian', 6000, 0),
(350, 'Interracial', 6000, 0),
(351, 'Orgy', 6000, 0),
(352, '18+ Teen Transsexuals', 6000, 0),
(353, 'Babysitter', 6000, 0),
(354, 'Amateur', 6000, 0),
(355, 'Public Sex', 6000, 0),
(356, 'Web-To-DVD', 6000, 0),
(357, 'Compilation', 6000, 0),
(358, 'Porn For Couples', 6000, 0),
(359, 'Romance', 6000, 0),
(360, 'Young (18+) Ladies', 6000, 0),
(361, 'Redheads', 6000, 0),
(362, 'Affairs/Love Triangles', 6000, 0),
(363, 'Women Directors', 6000, 0),
(364, 'Made For Women', 6000, 0),
(365, 'Pantyhose/Stocking', 6000, 0),
(366, 'Black', 6000, 0),
(367, 'Domination', 6000, 0),
(368, 'Face Sitting', 6000, 0),
(369, 'Female Domination', 6000, 0),
(370, 'Interactive', 6000, 0),
(371, 'Bi-Sexual', 6000, 0),
(372, 'Tit Fucking', 6000, 0),
(373, 'Anal Sex', 6000, 0),
(374, 'Big Pussies', 6000, 0),
(375, 'Cock & Ball Play', 6000, 0),
(376, 'Facials', 6000, 0),
(377, 'Finger Fucking', 6000, 0),
(378, 'Girl-Girl/Lesbian', 6000, 0),
(379, 'Nipple Play', 6000, 0),
(380, 'xtreme', 6000, 0),
(381, 'Canadian', 6000, 0),
(382, 'Black Hair', 6000, 0),
(383, 'Blondes', 6000, 0),
(384, 'Brunettes', 6000, 0),
(385, 'Cream Pie', 6000, 0),
(386, 'Latex/Rubber', 6000, 0),
(387, 'S&M', 6000, 0),
(388, 'Watersports', 6000, 0),
(389, 'BBW', 6000, 0),
(390, 'Prebooks', 6000, 0),
(391, 'Asian', 6000, 0),
(392, 'Massage', 6000, 0),
(393, 'Swallowing', 6000, 0),
(394, 'Small Tits', 6000, 0),
(395, 'Naturally Busty', 6000, 0),
(396, 'Affairs &amp; Love Triangles', 6000, 0),
(397, 'Wives', 6000, 0),
(398, 'Swingers', 6000, 0),
(399, 'Old &amp; Young Females (18+)', 6000, 0),
(400, 'Maid', 6000, 0),
(401, 'Pantyhose &amp; Stocking', 6000, 0),
(402, 'Masturbation', 6000, 0),
(403, 'Alt Girls', 6000, 0),
(404, 'Tattoo', 6000, 0),
(405, 'Exotic Workouts', 6000, 0),
(406, 'Athletes', 6000, 0),
(407, 'Deep Throat', 6000, 0),
(408, 'Wrestling &amp; Fighting', 6000, 0),
(409, 'Cheerleaders', 6000, 0),
(410, 'Home Made Movies', 6000, 0),
(411, 'Squirting', 6000, 0),
(412, 'Cuckolds', 6000, 0),
(413, 'Cougars', 6000, 0),
(414, 'Cosplay', 6000, 0),
(415, 'Bikini Babes', 6000, 0),
(416, 'Brazilian', 6000, 0),
(417, 'Grannies', 6000, 0),
(418, 'Erotic Vignette', 6000, 0),
(419, 'Water Play', 6000, 0),
(420, 'Hairy', 6000, 0),
(421, 'CFNM', 6000, 0),
(422, 'Stripping', 6000, 0),
(423, 'Double Penetration', 6000, 0),
(424, 'Lingerie', 6000, 0),
(425, 'Nurses &amp; Doctors', 6000, 0),
(426, 'British', 6000, 0),
(427, 'Fetish Wear', 6000, 0),
(428, 'Strap-Ons', 6000, 0),
(429, 'Indian', 6000, 0),
(430, 'Spoofs &amp; Parodies', 6000, 0),
(431, 'Pregnant', 6000, 0),
(432, 'Classic', 6000, 0),
(433, 'Jeans &amp; Denim', 6000, 0),
(434, 'Boxed Sets', 6000, 0),
(435, 'Mystery', 6000, 0),
(436, 'Girl on Guy Strap-Ons', 6000, 0),
(437, 'Celebrity', 6000, 0),
(438, 'Handjobs', 6000, 0),
(439, 'Shaved', 6000, 0),
(440, 'Vampires', 6000, 0),
(441, 'Panties &amp; Thongs', 6000, 0),
(442, 'Instructional (X-Rated)', 6000, 0),
(443, 'Fantasy', 6000, 0),
(444, 'Historical / Period Piece', 6000, 0),
(445, 'Western', 6000, 0),
(446, 'Midgets', 6000, 0),
(447, 'Oddities', 6000, 0),
(448, 'Foot Fetish', 6000, 0),
(449, 'Virgin', 6000, 0),
(450, 'Horror', 6000, 0),
(451, 'Streaming Video', 6000, 0),
(452, 'Solo Girls', 6000, 0),
(453, 'Solo Female', 6000, 0),
(454, 'Kinky', 6000, 0),
(455, 'By Women', 6000, 0),
(456, 'All Girl', 6000, 0),
(457, 'Exhibitionism', 6000, 0),
(458, 'College Co-Eds', 6000, 0),
(459, 'Pro-Am', 6000, 0),
(460, 'Parties / Clubs', 6000, 0),
(461, 'Sex', 6000, 0),
(462, 'Europe', 6000, 0),
(463, 'Germany', 6000, 0),
(464, 'Tantra / Spirituality', 6000, 0),
(465, 'Big Tits', 6000, 0),
(466, 'United Kingdom', 6000, 0),
(467, 'Female on Female', 6000, 0),
(468, 'Strapons', 6000, 0),
(469, 'Lesbian', 6000, 0),
(470, 'Potpourri', 6000, 0),
(471, 'Humor', 6000, 0),
(472, 'Spoofs / Parodies', 6000, 0),
(473, 'Gagging', 6000, 0),
(474, 'Family', 6000, 0),
(475, 'Vignettes', 6000, 0),
(476, 'Body', 6000, 0),
(477, 'Home Made', 6000, 0),
(478, 'POV', 6000, 0),
(479, 'M on F', 6000, 0),
(480, 'Gaper', 6000, 0),
(481, 'Latex & Rubber', 6000, 0),
(482, 'Foreign Kink', 6000, 0),
(483, 'Masks', 6000, 0),
(484, 'Group Sex', 6000, 0),
(485, 'Older Women', 6000, 0),
(486, 'Young Women / Teens', 6000, 0),
(487, 'Contemporary', 6000, 0),
(488, 'Features', 6000, 0),
(489, 'Biker Chicks', 6000, 0),
(490, 'Hetero Couples', 6000, 0),
(491, 'Caucasian Girls', 6000, 0),
(492, 'Modern', 6000, 0),
(493, '2011 AVN Award Nominees', 6000, 0),
(494, 'All Black', 6000, 0),
(495, 'Cumshots & Facials', 6000, 0),
(496, 'Outdoors', 6000, 0),
(497, 'Classics', 6000, 0),
(498, 'BDSM', 6000, 0),
(499, '90''s', 6000, 0),
(500, 'Oral', 6000, 0),
(501, 'Mixed Races', 6000, 0),
(502, 'Ebony Girls', 6000, 0),
(503, 'Black Women', 6000, 0),
(504, 'Striptease', 6000, 0),
(505, 'Dancers / Strippers', 6000, 0),
(506, '2013 AVN Award Nominees', 6000, 0),
(507, '2013 Sex Awards Nominees', 6000, 0),
(508, 'Cunnilingus', 6000, 0),
(509, 'Asian Girls', 6000, 0),
(510, 'Erotica', 6000, 0),
(511, 'Fantasies', 6000, 0),
(512, 'Black Men', 6000, 0),
(513, 'Latina', 6000, 0),
(514, 'Latina Girls', 6000, 0),
(515, 'Cuckold', 6000, 0),
(516, 'Natural Look', 6000, 0),
(517, 'Footjobs', 6000, 0),
(518, 'Feet', 6000, 0),
(519, 'Feet & Shoes', 6000, 0),
(520, 'Legs & Nylons', 6000, 0),
(521, 'Teen', 6000, 0),
(522, 'Russia', 6000, 0),
(523, 'Russian', 6000, 0),
(524, 'Schoolgirls', 6000, 0),
(525, 'Massage Parlor', 6000, 0),
(526, 'Virgins', 6000, 0),
(527, 'Beach', 6000, 0),
(528, '2008 AVN Award Nominees', 6000, 0),
(529, 'Czech', 6000, 0),
(530, 'Orgies', 6000, 0),
(531, 'Babysitters', 6000, 0),
(532, '2009 AVN Award Nominees', 6000, 0),
(533, 'Big Butts', 6000, 0),
(534, 'Handjob Female on Male', 6000, 0),
(535, 'Hidden Cam', 6000, 0),
(536, '2014 AVN Award Nominees', 6000, 0),
(537, 'XBIZ Awards', 6000, 0),
(538, 'Spain', 6000, 0),
(539, 'Spanish', 6000, 0),
(540, 'Feminist Porn Awards', 6000, 0),
(541, 'Sybian', 6000, 0),
(542, 'Instructional', 6000, 0),
(543, 'Female Ejaculation', 6000, 0),
(544, 'Dutch', 6000, 0),
(545, 'Maids', 6000, 0),
(546, 'Tattooed / Pierced', 6000, 0),
(547, 'Shower / Bathroom', 6000, 0),
(548, 'Cum-Swapping', 6000, 0),
(549, 'Girls Next Door', 6000, 0),
(550, 'Petite', 6000, 0),
(551, 'Gangbang - M on F', 6000, 0),
(552, 'Office', 6000, 0),
(553, 'Toys', 6000, 0),
(554, '2010 AVN Award Nominees', 6000, 0),
(555, 'International', 6000, 0),
(556, 'Student', 6000, 0),
(557, 'Deep Throating', 6000, 0),
(558, 'Boat', 6000, 0),
(559, 'Secretaries', 6000, 0),
(560, 'Reality', 6000, 0),
(561, 'Brazil', 6000, 0),
(562, 'Male on Female', 6000, 0),
(563, 'Nurses', 6000, 0),
(564, 'Interactive Sex', 6000, 0),
(565, 'Up & Coming', 6000, 0),
(566, 'Cum Swapping', 6000, 0),
(567, 'Multi-Angles', 6000, 0),
(568, 'Leather', 6000, 0),
(569, 'Pot Luck', 6000, 0),
(570, 'Cream Pies', 6000, 0),
(571, 'Behind The Scenes', 6000, 0),
(572, 'Auditions', 6000, 0),
(573, 'Pickup', 6000, 0),
(574, 'Sex Machines', 6000, 0),
(575, '2012 AVN Award Nominees', 6000, 0),
(576, 'Voyeurism', 6000, 0),
(577, 'Voyeur', 6000, 0),
(578, 'Bukkake', 6000, 0),
(579, 'Smoking', 6000, 0),
(580, 'Gangbang - F on F', 6000, 0),
(581, 'Italy', 6000, 0),
(582, 'Italian', 6000, 0),
(583, 'Portuguese', 6000, 0),
(584, 'Locale', 6000, 0),
(585, 'Secretaries/Office', 6000, 0),
(586, 'Sex at Work', 6000, 0),
(587, 'School Girls', 6000, 0),
(588, 'Felching', 6000, 0),
(589, 'Glasses', 6000, 0),
(590, 'Euro', 6000, 0),
(591, 'Pissing', 6000, 0),
(592, 'Shocking Penetration', 6000, 0),
(593, 'German Speaking', 6000, 0),
(594, 'Anilingus', 6000, 0),
(595, 'High Definition', 6000, 0),
(596, 'M.I.L.F.', 6000, 0),
(597, 'Cougar', 6000, 0),
(598, 'Big Dick', 6000, 0),
(599, 'Cumshot', 6000, 0),
(600, 'Pantyhose/Stockings', 6000, 0),
(601, 'Dancers/Models', 6000, 0),
(602, 'Submales', 6000, 0),
(603, 'Threeway', 6000, 0),
(604, 'Fresh Faces', 6000, 0),
(605, 'Natural Breasts', 6000, 0),
(606, 'German', 6000, 0),
(607, 'Blowjob', 6000, 0),
(608, 'Gloryhole', 6000, 0),
(609, 'Black Dicks/White Chicks', 6000, 0),
(610, 'P.O.V.', 6000, 0),
(611, 'Glamour', 6000, 0),
(612, 'Brides &amp; Weddings', 6000, 0),
(613, 'Exhibitionist', 6000, 0),
(614, 'Party Girls', 6000, 0),
(615, 'Reality Based', 6000, 0),
(616, 'Shemale', 6000, 0),
(617, 'Cheerleader', 6000, 0),
(618, 'Award Winning Movies', 6000, 0),
(619, 'New Release', 6000, 0),
(620, 'Foot', 6000, 0),
(621, 'Older / Younger', 6000, 0),
(622, 'Vintage', 6000, 0),
(623, 'Incest', 6000, 0),
(624, 'Femdom', 6000, 0),
(625, 'Strap-On', 6000, 0),
(626, 'Double Anal', 6000, 0),
(627, 'Costumes', 6000, 0),
(628, 'For Ladies', 6000, 0),
(629, 'Family Roleplay', 6000, 0),
(630, 'Girls With Toys', 6000, 0),
(631, 'Ass-to-mouth', 6000, 0),
(632, 'Parody', 6000, 0),
(633, 'Racing', 4000, 0),
(634, 'Double Penetration (Dp)', 6000, 0),
(635, 'Family', 1000, 0),
(636, 'Uniform', 6000, 0),
(637, 'Wet / Messy', 6000, 0),
(638, 'RPG', 4000, 0),
(639, 'Just Legal', 6000, 0),
(640, 'Cum Swap', 6000, 0),
(641, 'D.P.', 6000, 0),
(642, 'Racing', 1000, 0),
(643, 'Sports', 1000, 0),
(644, 'Fighting', 1000, 0),
(645, 'Indie', 4000, 0),
(646, 'Shooter', 1000, 0),
(647, 'Shemale Bareback', 6000, 0),
(648, 'Casual', 4000, 0),
(649, 'Big Asses', 6000, 0),
(650, 'Wrestling', 6000, 0),
(651, 'Natural Tits', 6000, 0),
(652, 'Hidden Camera', 6000, 0),
(653, 'Busty', 6000, 0),
(654, 'Blow Jobs', 6000, 0),
(655, 'Rough Sex', 6000, 0),
(656, 'Alt', 6000, 0),
(657, 'Three-Way', 6000, 0),
(658, 'Torture', 6000, 0),
(659, 'Fat', 6000, 0),
(660, 'Strategy', 1000, 0),
(661, 'Latin Music', 3000, 0),
(662, 'Big Natural Breasts', 6000, 0),
(663, 'Holidays', 6000, 0),
(664, 'Cum Guzzling', 6000, 0),
(665, 'Big Dicks', 6000, 0),
(666, 'M.I.L.F.s', 6000, 0),
(667, 'Soundtracks', 3000, 0),
(668, 'Swinger', 6000, 0),
(669, 'Parodies', 6000, 0),
(670, 'Role Playing', 6000, 0),
(671, 'Blow-Bang', 6000, 0),
(672, '*SALE-Downloads*', 6000, 0),
(673, '*SALE-Rentals*', 6000, 0),
(674, '*SALE Streaming*', 6000, 0),
(675, 'Children''s Music', 3000, 0),
(676, '80''s', 6000, 0),
(677, 'Role Playing', 4000, 0),
(678, 'Taboo', 6000, 0),
(679, 'Stealth', 4000, 0),
(680, 'Male Domination', 6000, 0),
(681, 'First Timers', 6000, 0),
(682, 'Smothering', 6000, 0),
(683, 'Role-Playing', 1000, 0),
(684, 'Music', 1000, 0),
(685, 'Simulation', 1000, 0),
(686, 'Arcade', 1000, 0),
(687, 'Horror', 1000, 0),
(688, 'Casino', 1000, 0),
(689, 'Fantasy', 1000, 0),
(690, 'Brides/Weddings', 6000, 0),
(691, 'Midget', 6000, 0),
(692, 'Military', 6000, 0),
(693, 'Uniforms', 6000, 0),
(694, 'Married', 6000, 0),
(695, 'Platformer', 4000, 0),
(696, 'Hand Jobs', 6000, 0),
(697, 'Turn Based Strategy', 4000, 0),
(698, 'Flying', 1000, 0),
(699, 'Point and Click', 4000, 0),
(700, 'Housewives', 6000, 0),
(701, 'Homemade', 6000, 0),
(702, 'Celebrities', 6000, 0),
(703, 'Tattoos', 6000, 0),
(704, 'Tit Punishment', 6000, 0),
(705, 'Strap Ons', 6000, 0),
(706, 'Muscles', 6000, 0),
(707, 'Arcade', 4000, 0),
(708, 'Adult Humor', 6000, 0),
(709, 'Thick Chicks', 6000, 0),
(710, 'Reality Porn', 6000, 0),
(711, 'Sex in Cars', 6000, 0),
(712, 'Street Pick Up', 6000, 0),
(713, 'Orgy (Women only)', 6000, 0),
(714, 'Vampire', 6000, 0),
(715, 'Combat Sim', 4000, 0),
(716, 'Collections', 1000, 0),
(717, 'Older', 6000, 0),
(718, 'French Speaking', 6000, 0),
(719, 'Spanking', 6000, 0),
(720, 'Bisexual', 6000, 0),
(721, 'Horror Porn', 6000, 0),
(722, 'Hairy Pussy', 6000, 0),
(723, 'Mother/Daughter', 6000, 0),
(724, 'Drama', 6000, 0),
(725, 'Rubber/Latex', 6000, 0),
(726, 'Mystery/Suspense', 6000, 0),
(727, 'First Person Shooter', 4000, 0),
(728, 'Enema', 6000, 0),
(729, 'She-Male', 6000, 0),
(730, 'Panties', 6000, 0),
(731, 'Bondage', 6000, 0),
(732, '70''s', 6000, 0),
(733, 'Catfights', 6000, 0),
(734, 'Trivia', 1000, 0),
(735, 'Triple Penetration', 6000, 0),
(736, 'Celebrity Skin', 6000, 0),
(737, 'Cult Favorites', 6000, 0),
(738, 'Erotic Fiction', 6000, 0),
(739, 'Female Nudity', 6000, 0),
(740, 'Full-Frontal Nudity', 6000, 0),
(741, 'Hollywood Hits', 6000, 0),
(742, 'Sexual Awakening/Coming-of-Age', 6000, 0),
(743, 'Sexual Fantasies', 6000, 0),
(744, 'Sexual Obsession', 6000, 0),
(745, 'Sexuality', 6000, 0),
(746, 'International Skin', 6000, 0),
(747, 'Lesbian/Girl-Girl', 6000, 0),
(748, 'Sexploitation', 6000, 0),
(749, 'Shower/Bath Scene(s)', 6000, 0),
(750, 'Genital Punishment', 6000, 0),
(751, 'Holiday', 6000, 0);

DROP TABLE IF EXISTS groups;
CREATE TABLE IF NOT EXISTS groups (
  id INT(11) NOT NULL AUTO_INCREMENT,
  name VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  backfill_target INT(4) NOT NULL DEFAULT '1',
  first_record BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
  first_record_postdate DATETIME DEFAULT NULL,
  last_record BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
  last_record_postdate DATETIME DEFAULT NULL,
  last_updated DATETIME DEFAULT NULL,
  minfilestoformrelease INT(4) DEFAULT NULL,
  minsizetoformrelease BIGINT(20) DEFAULT NULL,
  regexmatchonly TINYINT(1) NOT NULL DEFAULT '1',
  active TINYINT(1) NOT NULL DEFAULT '0',
  backfill TINYINT(1) NOT NULL DEFAULT '0',
  description VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT '',
  PRIMARY KEY (id),
  UNIQUE KEY name (name),
  KEY active (active)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT = 100001;

INSERT INTO groups (id, name, backfill_target, first_record, first_record_postdate, last_record, last_record_postdate, last_updated, minfilestoformrelease, minsizetoformrelease, regexmatchonly, active, backfill, description) VALUES
(1, 'alt.binaries.cd.image.linux', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(2, 'alt.binaries.linux.iso', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(3, 'comp.os.linux.development.apps', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(4, 'comp.os.linux.networking', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(5, 'comp.os.linux.misc', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(6, 'de.alt.sources.linux.patches', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(7, 'alt.binaries.sounds.ogg', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(8, 'alt.binaries.sounds.utilites', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(9, 'alt.binaries.sounds.midi', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(10, 'alt.binaries.linux', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(11, 'alt.binaries.dvdr', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(12, 'alt.binaries.multimedia', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(13, 'alt.binaries.movies.divx', 0, 0, NULL, 0, NULL, NULL, 5, NULL, 1, 0, 0, ''),
(14, 'alt.binaries.games.xbox', 0, 0, NULL, 0, NULL, NULL, 5, NULL, 1, 0, 0, ''),
(15, 'alt.binaries.movies.xvid', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(16, 'alt.binaries.sony.psp', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(17, 'alt.binaries.nintendo.ds', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(18, 'alt.binaries.games.nintendods', 0, 0, NULL, 0, NULL, NULL, 5, NULL, 1, 0, 0, ''),
(19, 'alt.binaries.hdtv.x264', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(20, 'alt.binaries.games.xbox360', 0, 0, NULL, 0, NULL, NULL, 4, NULL, 1, 0, 0, ''),
(21, 'alt.binaries.games.wii', 0, 0, NULL, 0, NULL, NULL, 5, NULL, 1, 0, 0, ''),
(22, 'alt.binaries.wmvhd', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(23, 'alt.binaries.x264', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(24, 'alt.binaries.wii', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(25, 'alt.binaries.moovee', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(26, 'alt.binaries.inner-sanctum', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(27, 'alt.binaries.warez.smartphone', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(28, 'alt.binaries.teevee', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(29, 'alt.binaries.warez', 0, 0, NULL, 0, NULL, NULL, 5, NULL, 1, 0, 1, ''),
(30, 'alt.binaries.sounds.mp3.complete_cd', 0, 0, NULL, 0, NULL, NULL, 5, NULL, 1, 0, 0, ''),
(31, 'alt.binaries.mpeg.video.music', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(32, 'alt.binaries.mp3', 0, 0, NULL, 0, NULL, NULL, 11, NULL, 1, 0, 0, ''),
(33, 'alt.binaries.mma', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(34, 'alt.binaries.sounds.mp3.classical', 0, 0, NULL, 0, NULL, NULL, 5, NULL, 1, 0, 0, ''),
(35, 'alt.binaries.mac', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(36, 'alt.binaries.e-book', 0, 0, NULL, 0, NULL, NULL, NULL, 1000000, 1, 0, 0, ''),
(37, 'alt.binaries.warez.ibm-pc.0-day', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(38, 'alt.binaries.tvseries', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(39, 'alt.binaries.ath', 0, 0, NULL, 0, NULL, NULL, 8, NULL, 1, 0, 0, ''),
(40, 'alt.binaries.ftn', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(41, 'alt.binaries.erotica', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(42, 'alt.binaries.games', 0, 0, NULL, 0, NULL, NULL, 5, NULL, 1, 0, 0, ''),
(43, 'alt.binaries.cores', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(44, 'alt.binaries.country.mp3', 0, 0, NULL, 0, NULL, NULL, 5, NULL, 1, 0, 0, ''),
(45, 'alt.binaries.sounds.mp3.1990s', 0, 0, NULL, 0, NULL, NULL, 5, NULL, 1, 0, 0, ''),
(46, 'alt.binaries.console.ps3', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(47, 'alt.binaries.scary.exe.files', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(48, 'alt.binaries.cd.image', 0, 0, NULL, 0, NULL, NULL, 4, NULL, 1, 0, 0, ''),
(49, 'alt.binaries.e-book.technical', 0, 0, NULL, 0, NULL, NULL, NULL, 1000000, 1, 0, 0, ''),
(50, 'alt.binaries.erotica.divx', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(51, 'alt.binaries.test', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(52, 'alt.binaries.x', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(53, 'alt.binaries.hou', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(54, 'alt.binaries.pro-wrestling', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(55, 'alt.binaries.sounds.lossless', 0, 0, NULL, 0, NULL, NULL, 5, NULL, 1, 0, 0, ''),
(56, 'alt.binaries.comp', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(57, 'alt.binaries.warez.quebec-hackers', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(58, 'alt.binaries.sounds.mp3.bluegrass', 0, 0, NULL, 0, NULL, NULL, 5, NULL, 1, 0, 0, ''),
(59, 'alt.binaries.sounds.radio.bbc', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(60, 'alt.binaries.e-book.flood', 0, 0, NULL, 0, NULL, NULL, NULL, 1000000, 1, 0, 0, ''),
(61, 'alt.binaries.movies.erotica', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(62, 'alt.binaries.multimedia.documentaries', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(63, 'alt.binaries.sounds.mp3.jazz', 0, 0, NULL, 0, NULL, NULL, 5, NULL, 1, 0, 0, ''),
(64, 'alt.binaries.multimedia.erotica.amateur', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(65, 'alt.binaries.sounds.1960s.mp3', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(66, 'alt.binaries.sounds.1970s.mp3', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(67, 'alt.binaries.sounds.mp3.comedy', 0, 0, NULL, 0, NULL, NULL, 5, NULL, 1, 0, 0, ''),
(68, 'alt.binaries.sounds.mp3.2000s', 0, 0, NULL, 0, NULL, NULL, 5, NULL, 1, 0, 0, ''),
(69, 'alt.binaries.sounds.mp3.christian', 0, 0, NULL, 0, NULL, NULL, 5, NULL, 1, 0, 0, ''),
(70, 'alt.binaries.sounds.mp3.1950s', 0, 0, NULL, 0, NULL, NULL, 5, NULL, 1, 0, 0, ''),
(71, 'alt.binaries.sounds.mp3.1970s', 0, 0, NULL, 0, NULL, NULL, 5, NULL, 1, 0, 0, ''),
(72, 'alt.binaries.sounds.mp3.1980s', 0, 0, NULL, 0, NULL, NULL, 5, NULL, 1, 0, 0, ''),
(73, 'alt.binaries.mp3.bootlegs', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(74, 'alt.binaries.sounds.mp3', 0, 0, NULL, 0, NULL, NULL, 5, NULL, 1, 0, 0, ''),
(75, 'alt.binaries.mp3.audiobooks', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(76, 'alt.binaries.sounds.mp3.rap-hiphop.full-albums', 0, 0, NULL, 0, NULL, NULL, 5, NULL, 1, 0, 0, ''),
(77, 'alt.binaries.sounds.mp3.full_albums', 0, 0, NULL, 0, NULL, NULL, 5, NULL, 1, 0, 0, ''),
(78, 'alt.binaries.sounds.mp3.dance', 0, 0, NULL, 0, NULL, NULL, 5, NULL, 1, 0, 0, ''),
(79, 'alt.binaries.warez.uk.mp3', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(80, 'alt.binaries.sounds.mp3.heavy-metal', 0, 0, NULL, 0, NULL, NULL, 5, NULL, 1, 0, 0, ''),
(81, 'alt.binaries.multimedia.cartoons', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(82, 'alt.binaries.multimedia.sports', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(83, 'alt.binaries.multimedia.anime', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(84, 'alt.binaries.sounds.lossless.classical', 0, 0, NULL, 0, NULL, NULL, 5, NULL, 1, 0, 0, ''),
(85, 'alt.binaries.multimedia.disney', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(86, 'alt.binaries.sounds.mp3.nospam', 0, 0, NULL, 0, NULL, NULL, 5, NULL, 1, 0, 0, ''),
(87, 'alt.binaries.multimedia.sitcoms', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(88, 'alt.binaries.sounds.radio.british', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(89, 'alt.binaries.multimedia.comedy.british', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(90, 'alt.binaries.etc', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(91, 'alt.binaries.misc', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(92, 'alt.binaries.sounds.mp3.rock', 0, 0, NULL, 0, NULL, NULL, 5, NULL, 1, 0, 0, ''),
(93, 'alt.binaries.dc', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(94, 'alt.binaries.documentaries', 0, 0, NULL, 0, NULL, NULL, 5, NULL, 1, 0, 0, ''),
(95, 'alt.binaries.cd.lossless', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(96, 'alt.binaries.sounds.audiobooks.repost', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(97, 'alt.binaries.highspeed', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(98, 'alt.binaries.bloaf', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(99, 'alt.binaries.big', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(100, 'alt.binaries.sounds.mp3.musicals', 0, 0, NULL, 0, NULL, NULL, 5, NULL, 1, 0, 0, ''),
(101, 'alt.binaries.sound.mp3', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(102, 'alt.binaries.sounds.mp3.jazz.vocals', 0, 0, NULL, 0, NULL, NULL, 5, NULL, 1, 0, 0, ''),
(103, 'alt.binaries.dvd.movies', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(104, 'alt.binaries.ebook', 0, 0, NULL, 0, NULL, NULL, NULL, 1000000, 1, 0, 0, ''),
(105, 'alt.binaries.sounds.mp3.disco', 0, 0, NULL, 0, NULL, NULL, 5, NULL, 1, 0, 0, ''),
(106, 'alt.binaries.mp3.full_albums', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(107, 'alt.binaries.tv', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(108, 'alt.binaries.sounds.lossless.country', 0, 0, NULL, 0, NULL, NULL, 5, NULL, 1, 0, 0, ''),
(109, 'alt.binaries.uzenet', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(110, 'alt.binaries.mom', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(111, 'alt.binaries.ijsklontje', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(112, 'alt.binaries.sounds.lossless.1960s', 0, 0, NULL, 0, NULL, NULL, 5, NULL, 1, 0, 0, ''),
(113, 'alt.binaries.sounds.mp3.emo', 0, 0, NULL, 0, NULL, NULL, 5, NULL, 1, 0, 0, ''),
(114, 'alt.binaries.classic.tv.shows', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(115, 'alt.binaries.dgma', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(116, 'alt.binaries.sounds.mp3.opera', 0, 0, NULL, 0, NULL, NULL, 5, NULL, 1, 0, 0, ''),
(117, 'alt.binaries.ipod.videos', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(118, 'alt.binaries.music.opera', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(119, 'alt.binaries.sounds.flac.jazz', 0, 0, NULL, 0, NULL, NULL, 5, NULL, 1, 0, 0, ''),
(120, 'alt.binaries.multimedia.tv', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(121, 'alt.binaries.sounds.whitburn.pop', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(122, 'alt.binaries.sound.audiobooks', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(123, 'alt.binaries.sounds.mp3.acoustic', 0, 0, NULL, 0, NULL, NULL, 5, NULL, 1, 0, 0, ''),
(124, 'alt.binaries.u-4all', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(125, 'alt.binaries.sounds.mp3.progressive-country', 0, 0, NULL, 0, NULL, NULL, 5, NULL, 1, 0, 0, ''),
(126, 'alt.binaries.multimedia.classic-films', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(127, 'alt.binaries.music.flac', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(128, 'alt.binaries.ghosts', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(129, 'alt.binaries.hdtv', 0, 0, NULL, 0, NULL, NULL, 2, NULL, 1, 0, 0, ''),
(130, 'alt.binaries.town', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(131, 'alt.binaries.comics.dcp', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(132, 'alt.binaries.audio.warez', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(133, 'alt.binaries.b4e', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(134, 'alt.binaries.pictures.comics.dcp', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(135, 'alt.binaries.pictures.comics.repost', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(136, 'alt.binaries.pictures.comics.reposts', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(137, 'alt.binaries.pictures.comics.complete', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(138, 'alt.binaries.sounds.mp3.rap-hiphop', 0, 0, NULL, 0, NULL, NULL, 5, NULL, 1, 0, 0, ''),
(139, 'alt.binaries.movies', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(140, 'alt.binaries.sounds.jpop', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(141, 'alt.binaries.sounds.mp3.country', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(142, 'alt.binaries.sounds.country.mp3', 0, 0, NULL, 0, NULL, NULL, 5, NULL, 1, 0, 0, ''),
(143, 'alt.binaries.worms', 0, 0, NULL, 0, NULL, NULL, 2, NULL, 1, 0, 0, ''),
(144, 'alt.binaries.multimedia.anime.highspeed', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(145, 'alt.binaries.anime', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(146, 'alt.binaries.multimedia.anime.repost', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(147, 'alt.binaries.0day.stuffz', 0, 0, NULL, 0, NULL, NULL, 2, NULL, 1, 0, 0, ''),
(148, 'dk.binaer.film', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(149, 'dk.binaer.tv', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(150, 'alt.binaries.xbox360.gamez', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(151, 'alt.binaries.wii.gamez', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(152, 'alt.binaries.wb', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(153, 'alt.binaries.tv.deutsch', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(154, 'alt.binaries.sounds.flac', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(155, 'alt.binaries.movies.french', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(156, 'alt.binaries.movies.divx.french', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(157, 'alt.binaries.karagarga', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(158, 'alt.binaries.drummers', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(159, 'alt.binaries.blu-ray', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(160, 'alt.binaries.apps.stuffz', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(161, 'alt.binaries.triballs', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(162, 'es.binarios.hd', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(163, 'alt.binaries.fz', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(164, 'alt.binaries.boneless', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, 'Added by predb import script'),
(165, 'alt.binaries.x.upper-case', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(166, 'alt.binaries.town.cine', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(167, 'alt.binaries.town.long.ding.dong', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(168, 'alt.binaries.town.monster.long.dong', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(169, 'alt.binaries.town.movie', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(170, 'alt.binaries.town.serien', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(171, 'alt.binaries.town.v2.foreign', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(172, 'alt.binaries.town.v2.tv', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(173, 'alt.binaries.town.v3', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(174, 'alt.binaries.town.xpron', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(175, 'alt.binaries.town.xxx', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(176, 'alt.binaries.town.z.alfa', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(177, 'alt.binaries.town.z.alpha', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(178, 'alt.binaries.town.z.beta', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(179, 'alt.binaries.town.z.bravo', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(180, 'alt.binaries.town.z.charlie', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(181, 'alt.binaries.town.z.delta', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(182, 'alt.binaries.town.z.echo', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(183, 'alt.binaries.town.z.foxtrot', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(184, 'alt.binaries.town.z.golf', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(185, 'alt.binaries.town.z.hotel', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(186, 'alt.binaries.town.z.india', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(187, 'alt.binaries.town.z.juliett', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(188, 'alt.binaries.town.z.kilo', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(189, 'alt.binaries.town.z.lima', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(190, 'alt.binaries.town.z.mike', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(191, 'alt.binaries.town.z.november', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(192, 'alt.binaries.town.z.oscar', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(193, 'alt.binaries.town.z.papa', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(194, 'alt.binaries.town.z.quebec', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(195, 'alt.binaries.town.z.romea', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(196, 'alt.binaries.town.z.romeo', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(197, 'alt.binaries.town.z.sierra', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(198, 'alt.binaries.town.z.tango', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(199, 'alt.binaries.town.z.uniform', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(200, 'alt.binaries.town.z.victor', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(201, 'alt.binaries.town.z.whiskey', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(202, 'alt.binaries.town.z.xray', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(203, 'alt.binaries.town.z.yankee', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(204, 'alt.binaries.town.z.zulu', 0, 0, NULL, 0, NULL, NULL, NULL, NULL, 1, 0, 0, ''),
(205, 'alt.binaries.dvd', 0, 0, NULL, 0, NULL, NULL, 0, 0, 1, 0, 0, ''),
(206, 'alt.binaries.dvd9', 0, 0, NULL, 0, NULL, NULL, 0, 0, 1, 0, 0, ''),
(207, 'alt.binaries.insiderz', 0, 0, NULL, 0, NULL, NULL, 0, 0, 1, 0, 0, ''),
(208, 'alt.binaries.nl', 0, 0, NULL, 0, NULL, NULL, 0, 0, 1, 0, 0, ''),
(209, 'dk.binaries.film', 0, 0, NULL, 0, NULL, NULL, 0, 0, 1, 0, 0, ''),
(210, 'alt.binaries.multimedia.vintage-film', 0, 0, NULL, 0, NULL, NULL, 0, 0, 1, 0, 0, ''),
(211, 'alt.binaries.amazing', 0, 0, NULL, 0, NULL, NULL, 0, 0, 1, 0, 0, ''),
(212, 'alt.binaries.prof', 0, 0, NULL, 0, NULL, NULL, 0, 0, 1, 0, 0, ''),
(213, 'alt.binaries.squaresoft', 0, 0, NULL, 0, NULL, NULL, 0, 0, 1, 0, 0, ''),
(214, 'alt.binaries.illuminaten', 0, 0, NULL, 0, NULL, NULL, 0, 0, 1, 0, 0, '');

DROP TABLE IF EXISTS logging;
CREATE TABLE IF NOT EXISTS logging (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  time DATETIME DEFAULT NULL,
  username VARCHAR(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  host VARCHAR(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (id)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT = 1;

DROP TABLE IF EXISTS menu;
CREATE TABLE IF NOT EXISTS menu (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  href VARCHAR(2000) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  title VARCHAR(2000) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  newwindow INT(1) UNSIGNED NOT NULL DEFAULT '0',
  tooltip VARCHAR(2000) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  role INT(11) UNSIGNED NOT NULL,
  ordinal INT(11) UNSIGNED NOT NULL,
  menueval VARCHAR(2000) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (id)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT  =  1000001;

INSERT INTO menu (id, href, title, newwindow, tooltip, role, ordinal, menueval) VALUES
(1, 'search', 'Search', 0, 'Search for Nzbs', 1, 10, ''),
(2, 'browse', 'Browse', 0, 'Browse for Nzbs', 1, 20, ''),
(3, 'browsegroup', 'Browse Groups', 0, 'Browse by Group', 1, 25, ''),
(4, 'movies', 'Movies', 0, 'Browse for Movies', 1, 40, ''),
(5, 'upcoming', 'In Theatres', 0, 'Whats on in theatres', 1, 45, ''),
(6, 'series', 'TV Series', 0, 'Browse for TV Series', 1, 50, ''),
(8, 'anime', 'Anime', 0, 'Browse Anime', 1, 55, ''),
(9, 'music', 'Music', 0, 'Browse for Music', 1, 60, ''),
(10, 'console', 'Console', 0, 'Browse for Games', 1, 65, ''),
(11, 'books', 'Books', 0, 'Browse for Books', 1, 66, ''),
(12, 'predb', 'PreDB', 0, 'View PreDB', 1, 67, '{if $site->nzprekey==''''}-1{/if}'),
(13, 'admin', 'Admin', 0, 'Admin', 2, 70, ''),
(14, 'cart', 'My Cart', 0, 'Your Nzb cart', 1, 75, ''),
(15, 'mymovies', 'My Movies', 0, 'Your Movie Wishlist', 1, 78, ''),
(16, 'queue', 'My Queue', 0, 'View Your Download Queue', 1, 80, '{if (($userdata.saburl|count_characters+$userdata.nzbgeturl|count_characters == 0) && $site->sabintegrationtype==2) || $site->sabintegrationtype==0}-1{/if}'),
(17, 'nzbvortex', 'My Queue', 0, 'View Your NZBVortex Queue', 1, 81, '{if $userdata.nzbvortex_server_url == ''''}-1{/if}'),
(19, 'forum', 'Forum', 0, 'Browse Forum', 1, 85, ''),
(20, 'profile', 'Profile', 0, 'View your profile', 1, 90, ''),
(21, 'logout', 'Logout', 0, 'Logout', 1, 95, ''),
(22, 'login', 'Login', 0, 'Login', 0, 100, ''),
(23, 'register', 'Register', 0, 'Register', 0, 110, ''),
(24, 'prehash', 'Prehash', 0, 'Prehash', 1, 68, ''),
(25, 'newposterwall', 'New Releases', 0, 'Newest Releases Poster Wall', 1, 11, '');

DROP TABLE IF EXISTS movieinfo;
CREATE TABLE IF NOT EXISTS movieinfo (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  imdbid MEDIUMINT(7) UNSIGNED zerofill DEFAULT NULL,
  tmdbid INT(10) UNSIGNED NOT NULL DEFAULT '0',
  title VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  tagline VARCHAR(1024) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  rating VARCHAR(4) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  plot VARCHAR(1024) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  year VARCHAR(4) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  genre VARCHAR(64) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  type VARCHAR(32) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  director VARCHAR(64) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  actors VARCHAR(2000) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  language VARCHAR(64) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  cover TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
  backdrop TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
  trailer VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  createddate DATETIME NOT NULL,
  updateddate DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY imdbid (imdbid),
  KEY ix_movieinfo_title (title)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT = 1;

DROP TABLE IF EXISTS musicinfo;
CREATE TABLE IF NOT EXISTS musicinfo (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL,
  asin VARCHAR(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  url VARCHAR(1000) COLLATE utf8_unicode_ci DEFAULT NULL,
  salesrank INT(10) UNSIGNED DEFAULT NULL,
  artist VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  publisher VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  releasedate DATETIME DEFAULT NULL,
  review VARCHAR(10000) COLLATE utf8_unicode_ci DEFAULT NULL,
  year VARCHAR(4) COLLATE utf8_unicode_ci DEFAULT NULL,
  genreid INT(10) DEFAULT NULL,
  tracks VARCHAR(3000) COLLATE utf8_unicode_ci DEFAULT NULL,
  cover TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
  createddate DATETIME NOT NULL,
  updateddate DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_musicinfo_title (title),
  FULLTEXT KEY ix_musicinfo_artist_title_ft (artist,title)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT = 1;

DROP TABLE IF EXISTS partrepair;
CREATE TABLE IF NOT EXISTS partrepair (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  numberid BIGINT(20) UNSIGNED NOT NULL,
  groupid INT(11) UNSIGNED NOT NULL,
  attempts TINYINT(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (ID),
  UNIQUE KEY ix_partrepair_numberID_groupID (numberID,groupID)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT = 1;

DROP TABLE IF EXISTS parts;
CREATE TABLE IF NOT EXISTS parts (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  binaryid BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
  messageid VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  number BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
  partnumber INT(10) UNSIGNED NOT NULL DEFAULT '0',
  size BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (id),
  KEY binaryid (binaryID),
  KEY ix_parts_number (number)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT = 1;

DROP TABLE IF EXISTS predb;
CREATE TABLE IF NOT EXISTS predb (
  id INT(12) NOT NULL AUTO_INCREMENT,
  ctime INT(12) NOT NULL,
  dirname VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL,
  category VARCHAR(20) COLLATE utf8_unicode_ci NOT NULL,
  nuketype VARCHAR(20) COLLATE utf8_unicode_ci DEFAULT '',
  nukereason VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT '',
  nuketime INT(12) DEFAULT '0',
  filesize FLOAT DEFAULT '0',
  filecount INT(6) DEFAULT '0',
  filename VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT '',
  updatedate TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY dirname (dirname),
  KEY ix_predb_ctime (ctime),
  KEY ix_predb_updatedate (updatedate),
  KEY ix_predb_filename (filename)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT = 1;

DROP TABLE IF EXISTS predbhash;
CREATE TABLE IF NOT EXISTS predbhash (
  hash varbinary(20) NOT NULL DEFAULT '',
  pre_id INT(11) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (hash)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

DROP TABLE IF EXISTS prehash;
CREATE TABLE IF NOT EXISTS prehash (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  filename VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  title VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  nfo VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  size VARCHAR(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  category VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  predate DATETIME DEFAULT NULL,
  source VARCHAR(50) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  requestid INT(10) UNSIGNED NOT NULL DEFAULT '0',
  groupid INT(10) UNSIGNED NOT NULL DEFAULT '0',
  nuked TINYINT(1) NOT NULL DEFAULT '0',
  nukereason VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  files VARCHAR(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  searched TINYINT(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (id),
  UNIQUE KEY ix_prehash_title (title),
  KEY ix_prehash_filename (filename),
  KEY ix_prehash_nfo (nfo),
  KEY ix_prehash_predate (predate),
  KEY ix_prehash_source (source),
  KEY ix_prehash_requestid (requestid,groupid),
  KEY ix_prehash_size (size),
  KEY ix_prehash_category (category),
  KEY ix_prehash_searched (searched)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT = 1;

DROP TRIGGER IF EXISTS delete_hashes;
DROP TRIGGER IF EXISTS insert_hashes;
DROP TRIGGER IF EXISTS update_hashes;

DELIMITER $$

CREATE TRIGGER delete_hashes AFTER DELETE ON prehash FOR EACH ROW BEGIN DELETE FROM predbhash WHERE hash IN ( UNHEX(md5(OLD.title)), UNHEX(md5(md5(OLD.title))), UNHEX(sha1(OLD.title)) ) AND pre_id  =  OLD.id; END $$
CREATE TRIGGER insert_hashes AFTER INSERT ON prehash FOR EACH ROW BEGIN INSERT INTO predbhash (hash, pre_id) VALUES (UNHEX(MD5(NEW.title)), NEW.id), (UNHEX(MD5(MD5(NEW.title))), NEW.id), ( UNHEX(SHA1(NEW.title)), NEW.id); END $$
CREATE TRIGGER update_hashes AFTER UPDATE ON prehash FOR EACH ROW BEGIN IF NEW.title !=  OLD.title THEN DELETE FROM predbhash WHERE hash IN ( UNHEX(md5(OLD.title)), UNHEX(md5(md5(OLD.title))), UNHEX(sha1(OLD.title)) ) AND pre_id  =  OLD.id; INSERT INTO predbhash (hash, pre_id) VALUES ( UNHEX(MD5(NEW.title)), NEW.id ), ( UNHEX(MD5(MD5(NEW.title))), NEW.id ), ( UNHEX(SHA1(NEW.title)), NEW.id );END IF;END $$

DELIMITER ;

DROP TABLE IF EXISTS prehash_imports;
CREATE TABLE IF NOT EXISTS prehash_imports (
  title VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  nfo VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  size VARCHAR(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  category VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  predate DATETIME DEFAULT NULL,
  source VARCHAR(50) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  requestid INT(10) UNSIGNED NOT NULL DEFAULT '0',
  groupid INT(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'FK to groups',
  nuked TINYINT(1) NOT NULL DEFAULT '0' COMMENT 'Is this pre nuked? 0 no 2 yes 1 un nuked 3 mod nuked',
  nukereason VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'If this pre is nuked, what is the reason?',
  files VARCHAR(50) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'How many files does this pre have ?',
  filename VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  searched TINYINT(1) NOT NULL DEFAULT '0',
  groupname VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci;

DROP TABLE IF EXISTS releaseaudio;
CREATE TABLE IF NOT EXISTS releaseaudio (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  releaseid INT(11) UNSIGNED DEFAULT NULL,
  audioid INT(2) UNSIGNED DEFAULT NULL,
  audioformat VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  audiomode VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  audiobitratemode VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  audiobitrate VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  audiochannels VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  audiosamplerate VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  audiolibrary VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  audiolanguage VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  audiotitle VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY releaseid (releaseid,audioid)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT = 1;

DROP TABLE IF EXISTS releaseextrafull;
CREATE TABLE IF NOT EXISTS releaseextrafull (
  releaseid INT(11) UNSIGNED NOT NULL DEFAULT '0',
  mediainfo TEXT COLLATE utf8_unicode_ci,
  PRIMARY KEY (releaseid)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci;

DROP TABLE IF EXISTS releasenfo;
CREATE TABLE IF NOT EXISTS releasenfo (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  releaseid INT(11) UNSIGNED DEFAULT NULL,
  binaryid INT(11) UNSIGNED DEFAULT NULL,
  nfo blob,
  PRIMARY KEY (id),
  UNIQUE KEY ix_releasenfo_releaseid (releaseid),
  KEY ix_releasenfo_binaryid (binaryid)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT = 1;

DROP TABLE IF EXISTS releaseregex;
CREATE TABLE IF NOT EXISTS releaseregex (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  groupname VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  regex VARCHAR(2000) COLLATE utf8_unicode_ci NOT NULL,
  ordinal INT(11) UNSIGNED NOT NULL,
  status INT(11) UNSIGNED NOT NULL DEFAULT '1',
  description VARCHAR(1000) COLLATE utf8_unicode_ci DEFAULT NULL,
  categoryid INT(11) DEFAULT NULL,
  PRIMARY KEY (id)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT = 1;

DROP TABLE IF EXISTS releaseregextesting;
CREATE TABLE IF NOT EXISTS releaseregextesting (
  id INT(11) NOT NULL AUTO_INCREMENT,
  name VARCHAR(512) COLLATE utf8_unicode_ci NOT NULL,
  fromname VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL,
  date DATETIME NOT NULL,
  binaryhash VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL,
  groupname VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL,
  regexid INT(11) UNSIGNED DEFAULT NULL,
  categoryid INT(11) UNSIGNED DEFAULT NULL,
  reqid VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  blacklistid INT(11) DEFAULT NULL,
  size BIGINT(20) NOT NULL,
  dateadded TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY ix_releaseregextesting_binaryhash (binaryhash)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT = 1;

DROP TABLE IF EXISTS releases;
CREATE TABLE IF NOT EXISTS releases (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  gid VARCHAR(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  name VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  searchname VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  totalpart INT(11) DEFAULT '0',
  groupid INT(10) UNSIGNED DEFAULT NULL,
  size BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
  postdate DATETIME DEFAULT NULL,
  adddate DATETIME DEFAULT NULL,
  updatedate TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  guid VARCHAR(50) COLLATE utf8_unicode_ci NOT NULL,
  fromname VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  completion FLOAT NOT NULL DEFAULT '0',
  categoryid INT(11) NOT NULL DEFAULT '0',
  videos_id MEDIUMINT(11) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'FK to videos.id of the parent series.',
  tv_episodes_id MEDIUMINT(11) NOT NULL DEFAULT '0' COMMENT 'FK to tv_episodes.id of the episode',
  regexid INT(11) DEFAULT NULL,
  tvdbid INT(11) UNSIGNED DEFAULT NULL,
  imdbid MEDIUMINT(7) UNSIGNED zerofill DEFAULT NULL,
  episodeinfoid INT(11) DEFAULT NULL,
  musicinfoid INT(11) DEFAULT NULL,
  consoleinfoid INT(11) DEFAULT NULL,
  bookinfoid INT(11) DEFAULT NULL,
  preid INT(12) DEFAULT NULL,
  anidbid INT(11) DEFAULT NULL,
  reqid VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  releasenfoid INT(11) DEFAULT NULL,
  grabs INT(10) UNSIGNED NOT NULL DEFAULT '0',
  comments INT(11) NOT NULL DEFAULT '0',
  passwordstatus INT(11) NOT NULL DEFAULT '0',
  rarinnerfilecount INT(11) NOT NULL DEFAULT '0',
  haspreview INT(11) NOT NULL DEFAULT '0',
  dehashstatus TINYINT(1) NOT NULL DEFAULT '0',
  nfostatus TINYINT(4) NOT NULL DEFAULT '0',
  jpgstatus TINYINT(1) NOT NULL DEFAULT '0',
  audiostatus TINYINT(1) NOT NULL DEFAULT '0',
  videostatus TINYINT(1) NOT NULL DEFAULT '0',
  reqidstatus TINYINT(1) NOT NULL DEFAULT '0',
  prehashid INT(10) UNSIGNED NOT NULL DEFAULT '0',
  iscategorized TINYINT(1) NOT NULL DEFAULT '0',
  isrenamed TINYINT(1) NOT NULL DEFAULT '0',
  ishashed TINYINT(1) NOT NULL DEFAULT '0',
  isrequestid TINYINT(1) NOT NULL DEFAULT '0',
  proc_pp TINYINT(1) NOT NULL DEFAULT '0',
  proc_par2 TINYINT(1) NOT NULL DEFAULT '0',
  proc_nfo TINYINT(1) NOT NULL DEFAULT '0',
  proc_files TINYINT(1) NOT NULL DEFAULT '0',
  gamesinfo_id INT(10) NOT NULL DEFAULT '0',
  xxxinfo_id INT(10) NOT NULL DEFAULT '0',
  proc_sorter TINYINT(1) NOT NULL DEFAULT '0',
  nzbstatus TINYINT(1) NOT NULL DEFAULT '0',
  nzb_guid BINARY(16) DEFAULT NULL,
  PRIMARY KEY (id,categoryid),
  KEY ix_releases_name (name),
  KEY ix_releases_group_id (groupid,passwordstatus),
  KEY ix_releases_postdate_searchname (postdate,searchname),
  KEY ix_releases_guid (guid),
  KEY ix_releases_nzb_guid (nzb_guid),
  KEY ix_releases_imdbid (imdbid),
  KEY ix_releases_xxxinfo_id (xxxinfo_id),
  KEY ix_releases_musicinfoid (musicinfoid,passwordstatus),
  KEY ix_releases_consoleinfoid (consoleinfoid),
  KEY ix_releases_gamesinfo_id (gamesinfo_id),
  KEY ix_releases_bookinfoid (bookinfoid),
  KEY ix_releases_anidbid (anidbid),
  KEY ix_releases_preid_searchname (prehashid,searchname),
  KEY ix_releases_haspreview_passwordstatus (haspreview,passwordstatus),
  KEY ix_releases_passwordstatus (passwordstatus),
  KEY ix_releases_nfostatus (nfostatus,size),
  KEY ix_releases_dehashstatus (dehashstatus,ishashed),
  KEY ix_releases_reqidstatus (adddate,reqidstatus,isrequestid),
  KEY ix_releases_gid (gid) COMMENT 'Index releases.gid column',
  KEY ix_releases_videos_id (videos_id),
  KEY ix_releases_tv_episodes_id (tv_episodes_id)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT = 1
  PARTITION BY RANGE (categoryid)(
  PARTITION unused VALUES LESS THAN (1000),
  PARTITION console VALUES LESS THAN (2000),
  PARTITION movies VALUES LESS THAN (3000),
  PARTITION audio VALUES LESS THAN (4000),
  PARTITION pc VALUES LESS THAN (5000),
  PARTITION tv VALUES LESS THAN (6000),
  PARTITION xxx VALUES LESS THAN (7000),
  PARTITION books VALUES LESS THAN (8000),
  PARTITION misc VALUES LESS THAN (9000)
  );

DROP TRIGGER IF EXISTS check_insert;
DROP TRIGGER IF EXISTS check_update;
DROP TRIGGER IF EXISTS delete_search;
DROP TRIGGER IF EXISTS insert_search;
DROP TRIGGER IF EXISTS update_search;

DELIMITER $$

CREATE TRIGGER check_insert BEFORE INSERT ON releases FOR EACH ROW BEGIN IF NEW.searchname REGEXP '[a-fA-F0-9]{32}' OR NEW.name REGEXP '[a-fA-F0-9]{32}' THEN SET NEW.ishashed  =  1;ELSEIF NEW.name REGEXP '^\[ ?([[:digit:]]{4,6}) ?\]|^REQs*([[:digit:]]{4,6})|^([[:digit:]]{4,6})-[[:digit:]]{1}\[' THEN SET NEW.isrequestid  =  1; END IF; END $$
CREATE TRIGGER check_update BEFORE UPDATE ON releases FOR EACH ROW BEGIN IF NEW.searchname REGEXP '[a-fA-F0-9]{32}' OR NEW.name REGEXP '[a-fA-F0-9]{32}' THEN SET NEW.ishashed  =  1;ELSEIF NEW.name REGEXP '^\[ ?([[:digit:]]{4,6}) ?\]|^REQs*([[:digit:]]{4,6})|^([[:digit:]]{4,6})-[[:digit:]]{1}\[' THEN SET NEW.isrequestid  =  1;END IF;END $$
CREATE TRIGGER delete_search AFTER DELETE ON releases FOR EACH ROW BEGIN DELETE FROM releasesearch WHERE releaseid  =  OLD.id; END $$
CREATE TRIGGER insert_search AFTER INSERT ON releases FOR EACH ROW BEGIN INSERT INTO releasesearch (releaseid, guid, name, searchname) VALUES (NEW.id, NEW.guid, NEW.name, NEW.searchname);END $$
CREATE TRIGGER update_search AFTER UPDATE ON releases FOR EACH ROW BEGIN IF NEW.guid !=  OLD.guid THEN UPDATE releasesearch SET guid  =  NEW.guid WHERE releaseid  =  OLD.id; END IF;IF NEW.name !=  OLD.name THEN UPDATE releasesearch SET name  =  NEW.name WHERE releaseid  =  OLD.id; END IF; IF NEW.searchname !=  OLD.searchname THEN UPDATE releasesearch SET searchname  =  NEW.searchname WHERE releaseid  =  OLD.id; END IF;END $$

DELIMITER ;

DROP TABLE IF EXISTS releasesearch;
CREATE TABLE IF NOT EXISTS releasesearch (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  releaseid INT(11) UNSIGNED NOT NULL,
  guid VARCHAR(50) COLLATE utf8_unicode_ci NOT NULL,
  name VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  searchname VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  fromname VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (id),
  KEY ix_releasesearch_releaseid (releaseid),
  KEY ix_releasesearch_guid (guid),
  FULLTEXT KEY ix_releasesearch_name_ft (name),
  FULLTEXT KEY ix_releasesearch_searchname_ft (searchname),
  FULLTEXT KEY ix_releasesearch_fromname_ft (fromname)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT = 1;

DROP TABLE IF EXISTS releasesubs;
CREATE TABLE IF NOT EXISTS releasesubs (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  releaseid INT(11) UNSIGNED DEFAULT NULL,
  subsid INT(2) UNSIGNED DEFAULT NULL,
  subslanguage VARCHAR(50) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY releaseid (releaseid,subsid)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT = 1;

DROP TABLE IF EXISTS releasevideo;
CREATE TABLE IF NOT EXISTS releasevideo (
  releaseid INT(11) UNSIGNED NOT NULL DEFAULT '0',
  containerformat VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  overallbitrate VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  videoduration VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  videoformat VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  videocodec VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  videowidth INT(10) DEFAULT NULL,
  videoheight INT(10) DEFAULT NULL,
  videoaspect VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  videoframerate FLOAT(7,4) DEFAULT NULL,
  videolibrary VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  definition INT(10) DEFAULT NULL,
  PRIMARY KEY (releaseid)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci;

DROP TABLE IF EXISTS release_comments;
CREATE TABLE IF NOT EXISTS release_comments (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  sourceid BIGINT(20) UNSIGNED DEFAULT NULL,
  gid VARCHAR(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  cid VARCHAR(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  releaseid INT(11) UNSIGNED DEFAULT NULL,
  text VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  isvisible TINYINT(1) DEFAULT '1',
  issynced TINYINT(1) DEFAULT '0',
  userid INT(11) UNSIGNED DEFAULT NULL,
  username VARCHAR(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  createddate DATETIME DEFAULT NULL,
  host VARCHAR(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  shared TINYINT(1) NOT NULL DEFAULT '1',
  shareid VARCHAR(40) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  siteid VARCHAR(40) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  nzb_guid BINARY(16) NOT NULL DEFAULT '0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0',
  text_hash VARCHAR(32) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  UNIQUE KEY ux_text_hash_siteid_nzb_guid (text_hash,siteid,nzb_guid),
  UNIQUE KEY ux_text_siteid_nzb_guid (text,siteid,nzb_guid),
  KEY ix_releasecomment_releaseid (releaseid),
  KEY ix_releasecomment_userid (userid),
  KEY ix_releasecomment_cid (cid),
  KEY ix_releasecomment_gid (gid)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT = 1;

DROP TRIGGER IF EXISTS insert_MD5;
DELIMITER $$

CREATE TRIGGER insert_MD5 BEFORE INSERT ON release_comments FOR EACH ROW SET NEW.text_hash  =  MD5(NEW.text);$$

DELIMITER ;

DROP TABLE IF EXISTS release_files;
CREATE TABLE IF NOT EXISTS release_files (
  releaseid INT(11) UNSIGNED NOT NULL,
  name VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  size BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
  ishashed TINYINT(1) NOT NULL DEFAULT '0',
  createddate DATETIME DEFAULT NULL,
  passworded TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (releaseid,name),
  KEY ix_releasefiles_ishashed (ishashed)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci;

DROP TABLE IF EXISTS roleexcat;
CREATE TABLE IF NOT EXISTS roleexcat (
  id INT(16) UNSIGNED NOT NULL AUTO_INCREMENT,
  role INT(11) NOT NULL,
  categoryid INT(11) DEFAULT NULL,
  createddate DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY ix_roleexcat_rolecat (role,categoryid)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT = 1;

DROP TABLE IF EXISTS settings;
CREATE TABLE IF NOT EXISTS settings (
  setting VARCHAR(64) COLLATE utf8_unicode_ci NOT NULL,
  value VARCHAR(19000) COLLATE utf8_unicode_ci DEFAULT NULL,
  updateddate TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  section VARCHAR(25) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  subsection VARCHAR(25) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  name VARCHAR(25) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  hint TEXT COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (section,subsection,name),
  UNIQUE KEY ui_settings_setting (setting)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci;

INSERT INTO settings (setting, value, section, subsection, name, hint) VALUES
('adbrowse', '', '', '', 'adbrowse', ''),
('addetail', '', '', '', 'addetail', ''),
('addpar2', '1', '', '', 'addpar2', ''),
('adheader', '', '', '', 'adheader', ''),
('alternate_nntp', '1', '', '', 'alternate_nntp', ''),
('amazonassociatetag', '', '', '', 'amazonassociatetag', ''),
('amazonprivkey', '', '', '', 'amazonprivkey', ''),
('amazonpubkey', '', '', '', 'amazonpubkey', ''),
('amazonsleep', '1000', '', '', 'amazonsleep', ''),
('anidb_banned', '0', '', '', 'anidb_banned', ''),
('anidbkey', '', '', '', 'anidbkey', ''),
('apienabled', '1', '', '', 'apienabled', ''),
('audiopreviewprune', '60', '', '', 'audiopreviewprune', ''),
('backfillthreads', '5', '', '', 'backfillthreads', ''),
('banned', '0', '', '', 'banned', ''),
('binarythreads', '10', '', '', 'binarythreads', ''),
('book_reqids', '3030, 7010, 7020, 7040, 7060', '', '', 'book_reqids', ''),
('categorizeforeign', '0', '', '', 'categorizeforeign', ''),
('catwebdl', '0', '', '', 'catwebdl', ''),
('checkpasswordedrar', '1', '', '', 'checkpasswordedrar', ''),
('code', 'Newznab Tmux', '', '', 'code', ''),
('completionpercent', '0', '', '', 'completionpercent', ''),
('compressedheaders', '0', '', '', 'compressedheaders', ''),
('coverspath', './resources/covers', '', '', 'coverspath', ''),
('crossposttime', '2','', '', 'crossposttime', ''),
('curlproxyaddress', '','', '', 'curlproxyaddress', ''),
('curlproxytype', '','', '', 'curlproxytype', ''),
('curlproxyuserpwd', '','', '', 'curlproxyuserpwd', ''),
('dbversion', '$Rev: 3253 $','', '', 'dbversion', ''),
('delaytime', '2','', '', 'delaytime', ''),
('deletepasswordedrelease', '0', '', '', 'deletepasswordedrelease', ''),
('deletepossiblerelease', '0', '', '', 'deletepossiblerelease', ''),
('dereferrer_link', 'http://derefer.me/?','', '', 'dereferrer_link', ''),
('disablebackfillgroup', '1', '', '', 'disablebackfillgroup', 'Whether to disable backfill on a group if the target date has been reached.'),
('email', '', '', '', 'email', ''),
('exepermittedcategories', '4000,4010,4020,4030,4040,4050,4060,4070,8010','', '', 'exepermittedcategories', ''),
('extractusingrarinfo', '0','', '', 'extractusingrarinfo', ''),
('fanarttvkey', '', '', '', 'fanarttvkey', ''),
('ffmpeg_duration', '5','', '', 'ffmpeg_duration', ''),
('ffmpeg_image_time', '5','', '', 'ffmpeg_image_time', ''),
('ffmpegpath', '', '', '', 'ffmpegpath', ''),
('fixnamesperrun', '10', '', '', 'fixnamesperrun', ''),
('fixnamethreads', '10', '', '', 'fixnamethreads', ''),
('footer', 'newznab is a simple usenet indexing site that is easy to configure as a community website.','', '', 'footer', ''),
('giantbombkey', '', '', '', 'giantbombkey', ''),
('google_adsense_acc', '','', '', 'google_adsense_acc', ''),
('google_adsense_search', '','', '', 'google_adsense_search', ''),
('google_analytics_acc', '','', '', 'google_analytics_acc', ''),
('grabstatus', '1','', '', 'grabstatus', ''),
('home_link', '/', '', '', 'home_link', ''),
('imdburl', '1', '', '', 'imdburl', ''),
('innerfileblacklist', '','', '', 'innerfileblacklist', ''),
('intanidbupdate', '7','', '', 'intanidbupdate', ''),
('lamepath', '', '', '', 'lamepath', ''),
('lastanidbupdate', '0','', '', 'lastanidbupdate', ''),
('latestregexrevision', '0','', '', 'latestregexrevision', ''),
('latestregexurl', 'http://www.newznab.com/getregex.php','', '', 'latestregexurl', ''),
('lookup_reqids', '1','', '', 'lookup_reqids', ''),
('lookupanidb', '1','', '', 'lookupanidb', ''),
('lookupbooks', '1','', '', 'lookupbooks', ''),
('lookupgames', '1','', '', 'lookupgames', ''),
('lookupimdb', '1','', '', 'lookupimdb', ''),
('lookuplanguage', 'en','', '', 'lookuplanguage', ''),
('lookupmusic', '1','', '', 'lookupmusic', ''),
('lookupnfo', '1','', '', 'lookupnfo', ''),
('lookuppar2', '1','', '', 'lookuppar2', ''),
('lookupthetvdb', '1','', '', 'lookupthetvdb', ''),
('lookuptvrage', '1', '', '', 'lookuptvrage', ''),
('lookupxxx', '1','', '', 'lookupxxx', ''),
('magic_file_path', '', '', '', 'magic_file_path', ''),
('maxaddprocessed', '25','', '', 'maxaddprocessed', ''),
('maxanidbprocessed', '100','', '', 'maxanidbprocessed', ''),
('maxbooksprocessed', '300','', '', 'maxbooksprocessed', ''),
('maxgamesprocessed', '150','', '', 'maxgamesprocessed', ''),
('maximdbprocessed', '100','', '', 'maximdbprocessed', ''),
('maxmsgsperrun', '200000','', '', 'maxmsgsperrun', ''),
('maxmssgs', '20000','', '', 'maxmssgs', ''),
('maxmusicprocessed', '150','', '', 'maxmusicprocessed', ''),
('maxnestedlevels', '3','', '', 'maxnestedlevels', ''),
('maxnfoprocessed', '100','', '', 'maxnfoprocessed', ''),
('maxnforetries', '5','', '', 'maxnforetries', ''),
('maxnzbsprocessed', '1000','', '', 'maxnzbsprocessed', ''),
('maxpartrepair', '15000','', '', 'maxpartrepair', ''),
('maxpartsprocessed', '3','', '', 'maxpartsprocessed', ''),
('maxrageprocessed', '75','', '', 'maxrageprocessed', ''),
('maxsizetoformrelease', '0','', '', 'maxsizetoformrelease', ''),
('maxsizetopostprocess', '100','', '', 'maxsizetopostprocess', ''),
('maxsizetoprocessnfo', '100','', '', 'maxsizetoprocessnfo', ''),
('maxxxxprocessed', '100','', '', 'maxxxxprocessed', ''),
('mediainfopath', '', '', '', 'mediainfopath', ''),
('metadescription', 'Newznab a usenet indexing website with community features','', '', 'metadescription', ''),
('metakeywords', 'usenet,nzbs,newznab,cms,community','', '', 'metakeywords', ''),
('metatitle', 'newznab - A great usenet indexer', '', '', 'metatitle', ''),
('minfilestoformrelease', '1','', '', 'minfilestoformrelease', ''),
('minsizetoformrelease', '0', '', '', 'minsizetoformrelease', ''),
('minsizetopostprocess', '0', '', '', 'minsizetopostprocess', ''),
('minsizetoprocessnfo', '1','', '', 'minsizetoprocessnfo', ''),
('mischashedretentionhours', '72', '', '', 'mischashedretentionhours', ''),
('miscotherretentionhours', '72', '', '', 'miscotherretentionhours', ''),
('newgroupdaystoscan', '3','', '', 'newgroupdaystoscan', ''),
('newgroupmsgstoscan', '50000','', '', 'newgroupmsgstoscan', ''),
('newgroupscanmethod', '0','', '', 'newgroupscanmethod', ''),
('newznabID', '','', '', 'newznabID', ''),
('nfothreads', '10', '', '', 'nfothreads', ''),
('nntpproxy', '0','', '', 'nntpproxy', ''),
('nntpretries', '10','', '', 'nntpretries', ''),
('nzbgetpassword', '','', '', 'nzbgetpassword', ''),
('nzbgeturl', '','', '', 'nzbgeturl', ''),
('nzbgetusername', '','', '', 'nzbgetusername', ''),
('nzbpath', '', '', '', 'nzbpath', ''),
('nzbsplitlevel', '1','', '', 'nzbsplitlevel', ''),
('nzbthreads', '5', '', '', 'nzbthreads', ''),
('nzprearticles', '500','', '', 'nzprearticles', ''),
('nzprefield', '', '', '', 'nzprefield', ''),
('nzpregroup', '', '', '', 'nzpregroup', ''),
('nzprekey', '', '', '', 'nzprekey', ''),
('nzpreposter', '', '', '', 'nzpreposter', ''),
('nzpresubject', '', '', '', 'nzpresubject', ''),
('partrepair', '1','', '', 'partrepair', ''),
('partrepairmaxtries', '3','', '', 'partrepairmaxtries', ''),
('partretentionhours', '72', '', '', 'partretentionhours', 'The number of hours incomplete parts and binaries will be retained'),
('partsdeletechunks', '0','', '', 'partsdeletechunks', ''),
('passchkattempts', '1','', '', 'passchkattempts', ''),
('postthreads', '10', '', '', 'postthreads', ''),
('postthreadsamazon', '1','', '', 'postthreadsamazon', ''),
('postthreadsnon', '10', '', '', 'postthreadsnon', ''),
('privateprofiles', '1', '', '', 'privateprofiles', 'Hide profiles from other users (admin/mod can still access).'),
('processjpg', '1','', '', 'processjpg', ''),
('processthumbnails', '1', '', '', 'processthumbnails', ''),
('processvideos', '0','', '', 'processvideos', ''),
('rawretentiondays', '1.5','', '', 'rawretentiondays', ''),
('recaptchaprivatekey', '', '', '', 'recaptchaprivatekey', ''),
('recaptchapublickey', '', '', '', 'recaptchapublickey', ''),
('registerrecaptcha', '1', '', '', 'registerrecaptcha', ''),
('registerstatus', '0', '', '', 'registerstatus', ''),
('releaseretentiondays', '0','', '', 'releaseretentiondays', ''),
('releasethreads', '10', '', '', 'releasethreads', ''),
('removespam', '1','', '', 'removespam', ''),
('removespecial', '1','', '', 'removespecial', ''),
('reqidthreads', '10', '', '', 'reqidthreads', ''),
('reqidurl', 'http://allfilled.newznab.com/query.php?t = [GROUP]&reqid = [REQID]','', '', 'reqidurl', ''),
('request_hours', '1','', '', 'request_hours', ''),
('request_url', 'http://reqid.newznab-tmux.pw/', '', '', 'request_url', ''),
('rottentomatokey', '', '', '', 'rottentomatokey', ''),
('rottentomatoquality', 'profile','', '', 'rottentomatoquality', ''),
('sabapikey', '','', '', 'sabapikey', ''),
('sabapikeytype', '1','', '', 'sabapikeytype', ''),
('sabcompletedir', '','', '', 'sabcompletedir', ''),
('sabintegrationtype', '2','', '', 'sabintegrationtype', ''),
('sabpriority', '0','', '', 'sabpriority', ''),
('saburl', '','', '', 'saburl', ''),
('sabvdir', '','', '', 'sabvdir', ''),
('safebackfilldate', '2012-06-24','', '', 'safebackfilldate', ''),
('safepartrepair', '0','', '', 'safepartrepair', ''),
('saveaudiopreview', '0', '', '', 'saveaudiopreview', ''),
('segmentstodownload', '4', '', '', 'segmentstodownload', ''),
('showadminwelcome', '0','', '', 'showadminwelcome', ''),
('showdroppedyencparts', '0','', '', 'showdroppedyencparts', ''),
('showpasswordedrelease', '1', '', '', 'showpasswordedrelease', ''),
('showrecentforumposts', '0','', '', 'showrecentforumposts', ''),
('siteseed', '','', '', 'siteseed', ''),
('sphinxbinpath', '','', '', 'sphinxbinpath', ''),
('sphinxconfpath', '','', '', 'sphinxconfpath', ''),
('sphinxenabled', '0','', '', 'sphinxenabled', ''),
('sphinxindexnfos', '0','', '', 'sphinxindexnfos', ''),
('sphinxindexnzbs', '0','', '', 'sphinxindexnzbs', ''),
('sphinxindexpredb', '0','', '', 'sphinxindexpredb', ''),
('sphinxindexreleasefiles', '0','', '', 'sphinxindexreleasefiles', ''),
('sphinxmergefreq', '0200','', '', 'sphinxmergefreq', ''),
('sphinxmergefreq_count', '10000','', '', 'sphinxmergefreq_count', ''),
('sphinxrebuildfreq', '0300','', '', 'sphinxrebuildfreq', ''),
('sphinxrebuildfreq_day', 'Monday','', '', 'sphinxrebuildfreq_day', ''),
('sphinxsearchfields', 'name,searchname','', '', 'sphinxsearchfields', ''),
('sphinxserverhost', '127.0.0.1:9306','', '', 'sphinxserverhost', ''),
('spotnabautoenable', '0', '', '', 'spotnabautoenable', ''),
('spotnabbroadcast', '0', '', '', 'spotnabbroadcast', ''),
('spotnabdiscover', '0', '', '', 'spotnabdiscover', ''),
('spotnabemail', '', '', '', 'spotnabemail', ''),
('spotnabgroup', 'alt.binaries.backup','', '', 'spotnabgroup', ''),
('spotnablastarticle', '', '', '', 'spotnablastarticle', ''),
('spotnabpost', '0', '', '', 'spotnabpost', ''),
('spotnabprivacy', '0', '', '', 'spotnabprivacy', ''),
('spotnabsiteprvkey', '', '', '', 'spotnabsiteprvkey', ''),
('spotnabsitepubkey', '', '', '', 'spotnabsitepubkey', ''),
('spotnabuser', '', '', '', 'spotnabuser', ''),
('sqlpatch', '217', '', '', 'sqlpatch', ''),
('storeuserips', '0', '', '', 'storeuserips', ''),
('strapline', 'A great usenet indexer','', '', 'strapline', ''),
('style', 'omicron', '', '', 'style', ''),
('tablepergroup', '1', '', '', 'tablepergroup', ''),
('tandc', '<p>All information within this database is indexed by an automated process, without any human intervention. It is obtained from global Usenet newsgroups over which this site has no control. We cannot prevent that you might find obscene or objectionable material by using this service. If you do come across obscene, incorrect or objectionable results, let us know by using the contact form.</p>','', '', 'tandc', ''),
('timeoutpath', '', '', '', 'timeoutpath', ''),
('timeoutseconds', '60', '', '', 'timeoutseconds', ''),
('title', 'NNTmux', '', '', 'title', ''),
('tmdbkey', '','', '', 'tmdbkey', ''),
('tmpunrarpath', '/var/www/nntmux/resources/tmp/unrar/', '', '', 'tmpunrarpath', ''),
('unrarpath', '', '', '', 'unrarpath', ''),
('updatecleanup', '0', '', '', 'updatecleanup', ''),
('updateparsing', '0','', '', 'updateparsing', ''),
('userdownloadpurgedays', '0','', '', 'userdownloadpurgedays', ''),
('userhostexclusion', '','', '', 'userhostexclusion', ''),
('userselstyle', '1', '', '', 'userselstyle', ''),
('yydecoderpath', '', '', '', 'yydecoderpath', ''),
('zippath', '', '', '', 'zippath', ''),
('trakttvclientkey', '', 'APIs', '', 'trakttvclientkey', 'The Trakt.tv API v2 Client ID (SHA256 hash - 64 characters long string). Used for movie and tv lookups.'),
('fetchlastcompressedfiles', '1', 'archive', 'fetch', 'end', 'Try to download the last rar or zip file? (This is good if most of the files are at the end.) Note: The first rar/zip is still downloaded.'),
('collection_timeout', '48', 'indexer', 'processing', 'collection_timeout', 'How many hours to wait before converting a collection into a release that is considered "stuck".'),
('last_run_time', '', 'indexer', 'processing', 'last_run_time', 'Last date the indexer (update_binaries or backfill) was run.'),
('maxheadersiteration', '1000000', 'max', 'headers', 'iteration', 'The maximum number of headers that update binaries sees as the total range. This ensure that a total of no more than this is attempted to be downloaded at one time per group.'),
('trailers_display', '1', 'site', 'trailers', 'trailers_display', 'Display trailers on the details page?'),
('trailers_size_x', '480', 'site', 'trailers', 'trailers_size_x', 'Width of the displayed trailer. 480 by default.'),
('trailers_size_y', '345', 'site', 'trailers', 'trailers_size_y', 'Height of the displayed trailer. 345 by default.'),
('tmux.running.exit', '0', 'tmux', 'running', 'exit', 'Determines if the running tmux monitor script should exit. If 0 nothing changes; if positive the script should exit gracefully (allowing all panes to finish); if negative the script should die as soon as possible.');

DROP TABLE IF EXISTS sharing;
CREATE TABLE IF NOT EXISTS sharing (
  site_guid VARCHAR(40) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  site_name VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  enabled TINYINT(1) NOT NULL DEFAULT '0',
  posting TINYINT(1) NOT NULL DEFAULT '0',
  start_position TINYINT(1) NOT NULL DEFAULT '0',
  fetching TINYINT(1) NOT NULL DEFAULT '1',
  auto_enable TINYINT(1) NOT NULL DEFAULT '1',
  hide_users TINYINT(1) NOT NULL DEFAULT '1',
  last_article BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
  max_push MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '40',
  max_pull INT(10) UNSIGNED NOT NULL DEFAULT '200',
  max_download MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '150',
  PRIMARY KEY (site_guid)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci;

DROP TABLE IF EXISTS sharing_sites;
CREATE TABLE IF NOT EXISTS sharing_sites (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  site_name VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  site_guid VARCHAR(40) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  last_time DATETIME DEFAULT NULL,
  first_time DATETIME DEFAULT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT '0',
  comments MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (id)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT = 1;

DROP TABLE IF EXISTS shortgroups;
CREATE TABLE IF NOT EXISTS shortgroups (
  id INT(11) NOT NULL AUTO_INCREMENT,
  name VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  first_record BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
  last_record BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
  updated DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  KEY ix_shortgroups_id (id),
  KEY ix_shortgroups_name (name)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT = 1;

DROP TABLE IF EXISTS sphinx;
CREATE TABLE IF NOT EXISTS sphinx (
  id INT(11) NOT NULL AUTO_INCREMENT,
  name VARCHAR(20) COLLATE utf8_unicode_ci NOT NULL,
  maxid INT(11) DEFAULT NULL,
  updatedate TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  lastmergedate TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00',
  nextmergedate TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00',
  lastrebuilddate TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00',
  nextrebuilddate TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (id)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT = 1;

DROP TABLE IF EXISTS spotnabsources;
CREATE TABLE IF NOT EXISTS spotnabsources (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(64) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'nntp',
  useremail VARCHAR(128) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'spot@nntp.com',
  usenetgroup VARCHAR(64) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'alt.binaries.backup',
  publickey VARCHAR(512) COLLATE utf8_unicode_ci NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT '0',
  description VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT '',
  lastupdate DATETIME DEFAULT NULL,
  lastbroadcast DATETIME DEFAULT NULL,
  lastarticle BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
  dateadded DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY spotnabsources_ix1 (username,useremail,usenetgroup)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT = 1;

DROP TABLE IF EXISTS tmux;
CREATE TABLE IF NOT EXISTS tmux (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  setting VARCHAR(64) COLLATE utf8_unicode_ci NOT NULL,
  value VARCHAR(19000) COLLATE utf8_unicode_ci DEFAULT NULL,
  updateddate TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY setting (setting)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT = 1;

INSERT INTO tmux (setting, value) VALUES
('defrag_cache', '900'),
('monitor_delay', '30'),
('tmux_session', 'newznab'),
('niceness', '19'),
('binaries', '2'),
('maxbinaries', '10000'),
('backfill', '0'),
('import', '0'),
('nzbs', '/path/to/nzbs'),
('running', '1'),
('sequential', '0'),
('nfos', '0'),
('post', '3'),
('releases', '2'),
('fix_names', '1'),
('seq_timer', '30'),
('bins_timer', '30'),
('bins_kill_timer', '0'),
('back_timer', '30'),
('import_timer', '30'),
('rel_timer', '30'),
('fix_timer', '30'),
('post_timer', '30'),
('import_bulk', '0'),
('backfill_qty', '100000'),
('postprocess_kill', '0'),
('crap_timer', '30'),
('fix_crap', 'blacklist, blfiles, executable, gibberish, installbin, passworded, passwordurl, sample, scr, short, size, huge, codec'),
('tv_timer', '43200'),
('update_tv', '1'),
('htop', '0'),
('nmon', '0'),
('bwmng', '0'),
('mytop', '0'),
('console', '1'),
('vnstat', '0'),
('vnstat_args', ''),
('tcptrack', '0'),
('tcptrack_args', '-i eth0 port 443'),
('backfill_groups', '4'),
('post_kill_timer', '300'),
('monitor_path', ''),
('write_logs', '0'),
('powerline', '0'),
('patchdb', '0'),
('patchdb_timer', '21600'),
('progressive', '0'),
('backfill_order', '1'),
('backfill_days', '1'),
('post_amazon', '1'),
('amazonsleep', '1000'),
('post_non', '1'),
('post_timer_amazon', '30'),
('post_timer_non', '30'),
('colors_start', '1'),
('colors_end', '250'),
('colors_exc', '4, 8, 9, 11, 15, 16, 17, 18, 19, 46, 47, 48, 49, 50, 51, 52, 53, 59, 60'),
('monitor_path_a', ''),
('monitor_path_b', ''),
('showquery', '1'),
('fix_crap_opt', 'Custom'),
('showprocesslist', '0'),
('processupdate', '2'),
('maxaddprocessed', '25'),
('maxnfoprocessed', '100'),
('maxrageprocessed', '75'),
('maximdbprocessed', '100'),
('maxanidbprocessed', '100'),
('maxmusicprocessed', '150'),
('maxgamesprocessed', '150'),
('maxbooksprocessed', '300'),
('maxnzbsprocessed', '1000'),
('maxpartrepair', '15000'),
('partrepair', '1'),
('binarythreads', '1'),
('backfillthreads', '1'),
('postthreads', '1'),
('releasethreads', '1'),
('nzbthreads', '1'),
('maxmssgs', '20000'),
('maxsizetopostprocess', '100'),
('postthreadsamazon', '1'),
('postthreadsnon', '1'),
('currentppticket', '0'),
('nextppticket', '0'),
('segmentstodownload', '2'),
('passchkattempts', '1'),
('maxpartsprocessed', '3'),
('debuginfo', '1'),
('movie_timer', '43200'),
('fetch_movie', '0'),
('unwanted', '0'),
('others', '0'),
('spotnab', '0'),
('spotnab_timer', '600'),
('predb', '0'),
('predb_timer', '600'),
('safebackfilldate', '2012-06-24'),
('request_hours', '1'),
('lookuppar2', '0'),
('addpar2', '0'),
('fixnamethreads', '1'),
('fixnamesperrun', '10'),
('zippath', ''),
('processjpg', '0'),
('lastpretime', '0'),
('nntpretries', '10'),
('run_sharing', '1'),
('sharing_timer', '60'),
('imdburl', '0'),
('yydecoderpath', ''),
('ffmpeg_duration', '5'),
('ffmpeg_image_time', '5'),
('processvideos', '0'),
('dehash', '3'),
('dehash_timer', '30'),
('optimize', '0'),
('optimize_timer', '86400'),
('import_count', '50000'),
('collections_kill', '0'),
('run_ircscraper', '1');

DROP TABLE IF EXISTS tv_episodes;
CREATE TABLE IF NOT EXISTS tv_episodes (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  videos_id MEDIUMINT(11) UNSIGNED NOT NULL COMMENT 'FK to videos.id of the parent series.',
  series SMALLINT(5) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Number of series/season.',
  episode SMALLINT(5) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Number of episode within series',
  se_complete VARCHAR(10) COLLATE utf8_unicode_ci NOT NULL COMMENT 'String version of Series/Episode as taken from release subject (i.e. S02E21+22).',
  title VARCHAR(180) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Title of the episode.',
  firstaired DATE DEFAULT NULL,
  summary TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Description/summary of the episode.',
  PRIMARY KEY (id),
  KEY ux_videoid_series_episode_aired (videos_id,series,episode,firstaired)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT = 1;

DROP TABLE IF EXISTS tv_info;
CREATE TABLE IF NOT EXISTS tv_info (
  videos_id MEDIUMINT(11) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'FK to video.id',
  summary TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Description/summary of the show.',
  publisher VARCHAR(50)   CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'The channel/network of production/release (ABC, BBC, Showtime, etc.).',
  localzone VARCHAR(50)   CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'The linux tz style identifier',
  image TINYINT(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Does the video have a cover image?',
  PRIMARY KEY (videos_id),
  KEY ix_tv_info_image (image)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci;

DROP TABLE IF EXISTS upcoming;
CREATE TABLE IF NOT EXISTS upcoming (
  id INT(10) NOT NULL AUTO_INCREMENT,
  source VARCHAR(20) COLLATE utf8_unicode_ci NOT NULL,
  typeid INT(10) DEFAULT NULL,
  info TEXT COLLATE utf8_unicode_ci,
  updateddate TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY source (source,typeid)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT = 1;

DROP TABLE IF EXISTS usercart;
CREATE TABLE IF NOT EXISTS usercart (
  id INT(16) UNSIGNED NOT NULL AUTO_INCREMENT,
  userid INT(11) DEFAULT NULL,
  releaseid INT(11) DEFAULT NULL,
  createddate DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY ix_usercart_userrelease (userid,releaseid)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT = 1;

DROP TABLE IF EXISTS userdownloads;
CREATE TABLE IF NOT EXISTS userdownloads (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  userid INT(16) DEFAULT NULL,
  hosthash VARCHAR(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  TIMESTAMP DATETIME NOT NULL,
  releaseid INT(11) DEFAULT NULL,
  PRIMARY KEY (id),
  KEY userid (userid),
  KEY hosthash (hosthash),
  KEY TIMESTAMP (TIMESTAMP),
  KEY releaseid (releaseid)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT = 1;

DROP TABLE IF EXISTS userexcat;
CREATE TABLE IF NOT EXISTS userexcat (
  id INT(16) UNSIGNED NOT NULL AUTO_INCREMENT,
  userid INT(11) DEFAULT NULL,
  categoryid INT(11) DEFAULT NULL,
  createddate DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY ix_userexcat_usercat (userid,categoryid)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT = 1;

DROP TABLE IF EXISTS userinvite;
CREATE TABLE IF NOT EXISTS userinvite (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  guid VARCHAR(50) COLLATE utf8_unicode_ci NOT NULL,
  userid INT(11) UNSIGNED DEFAULT NULL,
  createddate DATETIME NOT NULL,
  PRIMARY KEY (id)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT = 1;

DROP TABLE IF EXISTS usermovies;
CREATE TABLE IF NOT EXISTS usermovies (
  id INT(16) UNSIGNED NOT NULL AUTO_INCREMENT,
  userid INT(16) DEFAULT NULL,
  imdbid MEDIUMINT(7) UNSIGNED zerofill DEFAULT NULL,
  categoryid VARCHAR(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  createddate DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_usermovies_userid (userid,imdbid)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT = 1;

DROP TABLE IF EXISTS userrequests;
CREATE TABLE IF NOT EXISTS userrequests (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  userid INT(16) DEFAULT NULL,
  request VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL,
  hosthash VARCHAR(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  TIMESTAMP DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY userid (userid),
  KEY hosthash (hosthash),
  KEY TIMESTAMP (TIMESTAMP)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT = 1;

DROP TABLE IF EXISTS userroles;
CREATE TABLE IF NOT EXISTS userroles (
  id INT(10) NOT NULL AUTO_INCREMENT,
  name VARCHAR(32) COLLATE utf8_unicode_ci NOT NULL,
  apirequests INT(10) UNSIGNED NOT NULL,
  downloadrequests INT(10) UNSIGNED NOT NULL,
  defaultinvites INT(10) UNSIGNED NOT NULL,
  isdefault TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
  canpreview TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
  canpre TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
  hideads TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (id)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT = 4;

INSERT INTO userroles (id, name, apirequests, downloadrequests, defaultinvites, isdefault, canpreview)
  VALUES
  (1, 'Guest', 0, 0, 0, 0, 0),
  (2, 'User', 10, 10, 1, 1, 0),
  (3, 'Admin', 1000, 1000, 1000, 0, 1),
  (4, 'Disabled', 0, 0, 0, 0, 0),
  (5, 'Moderator', 1000, 1000, 1000, 0, 1),
  (6, 'Friend', 100, 100, 5, 0, 1);

UPDATE userroles SET id =  id - 1;

DROP TABLE IF EXISTS users;
CREATE TABLE IF NOT EXISTS users (
  id INT(16) UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(50) COLLATE utf8_unicode_ci NOT NULL,
  email VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL,
  password VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL,
  role INT(11) NOT NULL DEFAULT '1',
  host VARCHAR(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  grabs INT(11) NOT NULL DEFAULT '0',
  rsstoken VARCHAR(32) COLLATE utf8_unicode_ci NOT NULL,
  createddate DATETIME NOT NULL,
  resetguid VARCHAR(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  lastlogin DATETIME DEFAULT NULL,
  apiaccess DATETIME DEFAULT NULL,
  invites INT(11) NOT NULL DEFAULT '0',
  invitedby INT(11) DEFAULT NULL,
  movieview INT(11) NOT NULL DEFAULT '1',
  musicview INT(11) NOT NULL DEFAULT '0',
  consoleview INT(11) NOT NULL DEFAULT '0',
  xxxview INT(11) DEFAULT NULL,
  gameview INT(11) DEFAULT NULL,
  bookview INT(11) NOT NULL DEFAULT '0',
  saburl VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  sabapikey VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  sabapikeytype TINYINT(1) DEFAULT NULL,
  sabpriority TINYINT(1) DEFAULT NULL,
  nzbgeturl VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  nzbgetusername VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  nzbgetpassword VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  kindleid VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  userseed VARCHAR(50) COLLATE utf8_unicode_ci NOT NULL,
  notes VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  rolechangedate DATETIME DEFAULT NULL,
  nzbvortex_api_key VARCHAR(10) COLLATE utf8_unicode_ci DEFAULT NULL,
  nzbvortex_server_url VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  cp_api VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  cp_url VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  queuetype TINYINT(1) NOT NULL DEFAULT '1',
  style VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (id)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT = 1;

DROP TABLE IF EXISTS userseries;
CREATE TABLE IF NOT EXISTS userseries (
  id INT(16) UNSIGNED NOT NULL AUTO_INCREMENT,
  userid INT(16) DEFAULT NULL,
  videos_id INT(16) NOT NULL COMMENT 'FK to videos.id',
  categoryid VARCHAR(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  createddate DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_userseries_videos_id (userid,videos_id)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT = 1;

DROP TABLE IF EXISTS videos;
CREATE TABLE IF NOT EXISTS videos (
  id MEDIUMINT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Show''s ID to be used in other tables as reference.',
  type TINYINT(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '0  =  TV, 1  =  Film, 2  =  Anime',
  title VARCHAR(180) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Name of the video.',
  countries_id CHAR(2) COLLATE utf8_unicode_ci NOT NULL DEFAULT '' COMMENT 'Two character country code (FK to countries table).',
  started DATETIME NOT NULL COMMENT 'Date (UTC) of production''s first airing.',
  anidb MEDIUMINT(11) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'ID number for anidb site',
  imdb MEDIUMINT(11) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'ID number for IMDB site (without the ''tt'' prefix).',
  tmdb MEDIUMINT(11) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'ID number for TMDB site.',
  trakt MEDIUMINT(11) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'ID number for TraktTV site.',
  tvdb MEDIUMINT(11) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'ID number for TVDB site',
  tvmaze MEDIUMINT(11) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'ID number for TVMaze site.',
  tvrage MEDIUMINT(11) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'ID number for TVRage site.',
  source TINYINT(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Which site did we use for info?',
  PRIMARY KEY (id),
  UNIQUE KEY ix_videos_title (title,type,started,countries_id),
  KEY ix_videos_imdb (imdb),
  KEY ix_videos_tmdb (tmdb),
  KEY ix_videos_trakt (trakt),
  KEY ix_videos_tvdb (tvdb),
  KEY ix_videos_tvmaze (tvmaze),
  KEY ix_videos_tvrage (tvrage),
  KEY ix_videos_type_source (type,source)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT = 1;

DROP TABLE IF EXISTS videos_aliases;
CREATE TABLE IF NOT EXISTS videos_aliases (
  videos_id MEDIUMINT(11) UNSIGNED NOT NULL COMMENT 'FK to videos.id of the parent title.',
  title VARCHAR(180) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'AKA of the video.',
  PRIMARY KEY (videos_id,title)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci;

DROP TABLE IF EXISTS xxxinfo;
CREATE TABLE IF NOT EXISTS xxxinfo (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL,
  tagline VARCHAR(1024) COLLATE utf8_unicode_ci NOT NULL,
  plot blob,
  genre VARCHAR(64) COLLATE utf8_unicode_ci NOT NULL,
  director VARCHAR(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  actors VARCHAR(2000) COLLATE utf8_unicode_ci NOT NULL,
  extras TEXT COLLATE utf8_unicode_ci,
  productinfo TEXT COLLATE utf8_unicode_ci,
  trailers TEXT COLLATE utf8_unicode_ci,
  directurl VARCHAR(2000) COLLATE utf8_unicode_ci NOT NULL,
  classused VARCHAR(4) COLLATE utf8_unicode_ci DEFAULT 'ade',
  cover TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
  backdrop TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
  createddate DATETIME NOT NULL,
  updateddate DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_xxxinfo_title (title)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT = 1;
