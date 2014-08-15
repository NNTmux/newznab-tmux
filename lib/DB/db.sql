ALTER TABLE `predb`
ADD `md5` VARCHAR(32) NULL,
ADD INDEX (`md5`);
ALTER TABLE `releases`
ADD `dehashstatus` TINYINT(1) NOT NULL DEFAULT 0,
ADD `nfostatus` TINYINT NOT NULL DEFAULT 0,
ADD `jpgstatus` TINYINT(1) NOT NULL DEFAULT 0,
ADD `audiostatus` TINYINT(1) NOT NULL DEFAULT 0,
ADD `videostatus` TINYINT(1) NOT NULL DEFAULT 0,
ADD `reqidstatus` TINYINT(1) NOT NULL DEFAULT 0,
ADD `prehashID` INT UNSIGNED NOT NULL DEFAULT 0,
ADD `iscategorized` BIT NOT NULL DEFAULT 0,
ADD `isrenamed` BIT NOT NULL DEFAULT 0,
ADD `ishashed` BIT NOT NULL DEFAULT 0,
ADD `isrequestid` BIT NOT NULL DEFAULT 0,
ADD `proc_pp` TINYINT(1) NOT NULL DEFAULT 0,
ADD `proc_par2` BIT NOT NULL DEFAULT 0,
ADD `proc_nfo` BIT NOT NULL DEFAULT 0,
ADD `proc_files` BIT NOT NULL DEFAULT 0,
ADD `gamesinfo_id` INT AFTER consoleinfoID,
ADD `nzbstatus` TINYINT(1) NOT NULL DEFAULT 1;
CREATE INDEX `ix_releases_nfostatus` ON `releases` (`nfostatus` ASC) USING HASH;
CREATE INDEX `ix_releases_reqidstatus` ON `releases` (`reqidstatus` ASC) USING HASH;
CREATE INDEX `ix_releases_passwordstatus` ON `releases` (`passwordstatus`);
CREATE INDEX `ix_releases_releasenfoID` ON `releases` (`releasenfoID`);
CREATE INDEX `ix_releases_dehashstatus` ON `releases` (`dehashstatus`);
CREATE INDEX `ix_releases_haspreview` ON `releases` (`haspreview` ASC) USING HASH;
CREATE INDEX `ix_releases_postdate_name` ON `releases` (`postdate`, `name`);
CREATE INDEX `ix_releases_prehashid_searchname` ON `releases` (`prehashID`, `searchname`);
CREATE INDEX `ix_releases_gamesinfo_id` ON `releases` (`gamesinfo_id`);
CREATE INDEX `ix_releases_status` ON `releases` (`nzbstatus`, `iscategorized`, `isrenamed`, `nfostatus`, `ishashed`, `passwordstatus`, `dehashstatus`, `releasenfoID`, `musicinfoID`, `consoleinfoID`, `bookinfoID`, `haspreview`, `categoryID`, `imdbID`, `rageID`);

ALTER TABLE users ADD COLUMN gameview INT AFTER consoleview;

DROP TABLE IF EXISTS prehash;
CREATE TABLE prehash (
  ID         INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  filename   VARCHAR(255)     NOT NULL DEFAULT '',
  title      VARCHAR(255)     NOT NULL DEFAULT '',
  nfo        VARCHAR(255)     NULL,
  size       VARCHAR(50)      NULL,
  category   VARCHAR(255)     NULL,
  predate    DATETIME DEFAULT NULL,
  source     VARCHAR(50)      NOT NULL DEFAULT '',
  requestID  INT(10) UNSIGNED NOT NULL DEFAULT '0',
  groupID    INT(10) UNSIGNED NOT NULL DEFAULT '0',
  nuked      TINYINT(1)       NOT NULL DEFAULT '0',
  nukereason VARCHAR(255)     NULL,
  files      VARCHAR(50)      NULL,
  searched   TINYINT(1)       NOT NULL DEFAULT '0',
  PRIMARY KEY (ID)
)
  ENGINE =INNODB
  DEFAULT CHARACTER SET utf8
  COLLATE utf8_unicode_ci
  AUTO_INCREMENT =1;

CREATE INDEX `ix_prehash_filename` ON `prehash` (`filename`);
CREATE UNIQUE INDEX `ix_prehash_title` ON `prehash` (`title`);
CREATE INDEX `ix_prehash_nfo` ON `prehash` (`nfo`);
CREATE INDEX `ix_prehash_predate` ON `prehash` (`predate`);
CREATE INDEX `ix_prehash_source` ON `prehash` (`source`);
CREATE INDEX `ix_prehash_requestid` ON `prehash` (`requestID`, `groupID`);
CREATE INDEX `ix_prehash_size` ON `prehash` (`size`);
CREATE INDEX `ix_prehash_category` ON `prehash` (`category`);
CREATE INDEX `ix_prehash_searched` ON `prehash` (`searched`);

DROP TABLE IF EXISTS tmux;
CREATE TABLE tmux (
  ID          INT(10) UNSIGNED        NOT NULL AUTO_INCREMENT,
  setting     VARCHAR(64)
              COLLATE utf8_unicode_ci NOT NULL,
  value       VARCHAR(19000)
              COLLATE utf8_unicode_ci DEFAULT NULL,
  updateddate TIMESTAMP               NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (ID),
  UNIQUE KEY setting (setting)
)
  ENGINE =INNODB
  DEFAULT CHARSET =utf8
  COLLATE =utf8_unicode_ci;

INSERT INTO tmux (setting, value) VALUES ('defrag_cache', '900'),
('monitor_delay', '30'),
('tmux_session', 'newznab'),
('niceness', '19'),
('binaries', '0'),
('maxbinaries', '10000'),
('backfill', '0'),
('import', '0'),
('nzbs', '/path/to/nzbs'),
('running', '0'),
('sequential', '0'),
('nfos', '0'),
('post', '0'),
('releases', '0'),
('fix_names', '0'),
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
('fix_crap', '0'),
('tv_timer', '43200'),
('update_tv', '0'),
('htop', '0'),
('nmon', '0'),
('bwmng', '0'),
('mytop', '0'),
('console', '0'),
('vnstat', '0'),
('vnstat_args', NULL),
('tcptrack', '0'),
('tcptrack_args', '-i eth0 port 443'),
('backfill_groups', '4'),
('post_kill_timer', '300'),
('monitor_path', NULL),
('write_logs', '0'),
('powerline', '0'),
('patchdb', '0'),
('patchdb_timer', '21600'),
('progressive', '0'),
('dehash', '0'),
('dehash_timer', '30'),
('backfill_order', '2'),
('backfill_days', '1'),
('post_amazon', '0'),
('amazonsleep', 1000),
('post_non', '0'),
('post_timer_amazon', '30'),
('post_timer_non', '30'),
('colors_start', '1'),
('colors_end', '250'),
('colors_exc', '4, 8, 9, 11, 15, 16, 17, 18, 19, 46, 47, 48, 49, 50, 51, 52, 53, 59, 60'),
('monitor_path_a', NULL),
('monitor_path_b', NULL),
('showquery', '0'),
('fix_crap_opt', 'Disabled'),
('showprocesslist', '0'),
('processupdate', '2'),
('maxaddprocessed', 25),
('maxnfoprocessed', 100),
('maxrageprocessed', 75),
('maximdbprocessed', 100),
('maxanidbprocessed', 100),
('maxmusicprocessed', 150),
('maxgamesprocessed', 150),
('maxbooksprocessed', 300),
('maxnzbsprocessed', 1000),
('maxpartrepair', 15000),
('partrepair', 1),
('binarythreads', 1),
('backfillthreads', 1),
('postthreads', 1),
('releasethreads', 1),
('nzbthreads', 1),
('maxmssgs', 20000),
('maxsizetopostprocess', 100),
('postthreadsamazon', '1'),
('postthreadsnon', '1'),
('currentppticket', '0'),
('nextppticket', '0'),
('segmentstodownload', '2'),
('passchkattempts', 1),
('maxpartsprocessed', 3),
('debuginfo', 0),
('request_url', 'http://reqid.nzedb.com/index.php?reqid=[REQUEST_ID]&group=[GROUP_NM]'),
('lookup_reqids', '1'),
('lookup_reqids_timer', '40'),
('sphinx', '0'),
('sphinx_timer', '600'),
('movie_timer', '43200'),
('fetch_movie', '0'),
('unwanted', '0'),
('others', '0'),
('spotnab', '0'),
('spotnab_timer', '600'),
('predb', '0'),
('predb_timer', '600'),
('safebackfilldate', '2012-06-24'),
('safepartrepair', '0'),
('request_hours', '1'),
('trakttvkey', ''),
('fanarttvkey', ''),
('lookuppar2', '0'),
('addpar2', '0'),
('fixnamethreads', '1'),
('fixnamesperrun', '10'),
('max_load', 2.0),
('max_load_releases', 2.0),
('zippath', ''),
('processjpg', 0),
('scrape', 0),
('lastpretime', '0'),
('nntpretries', '10'),
('run_sharing', '0'),
  ('sharing_timer', '60'),
  ('imdburl', '0'),
  ('yydecoderpath', ''),
  ('ffmpeg_duration', '5'),
  ('ffmpeg_image_time', '5'),
  ('processvideos', '0'),
  ('sqlpatch', '56');

DROP TABLE IF EXISTS releasesearch;
CREATE TABLE releasesearch (
  ID         INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  releaseID  INT(11) UNSIGNED NOT NULL,
  guid       VARCHAR(50)      NOT NULL,
  name       VARCHAR(255)     NOT NULL DEFAULT '',
  searchname VARCHAR(255)     NOT NULL DEFAULT '',
  PRIMARY KEY (ID)
)
  ENGINE =MYISAM
  DEFAULT CHARSET =utf8
  COLLATE =utf8_unicode_ci
  AUTO_INCREMENT =1;

CREATE FULLTEXT INDEX ix_releasesearch_name_searchname_ft ON releasesearch (name, searchname);
CREATE INDEX ix_releasesearch_releaseid ON releasesearch (releaseID);
CREATE INDEX ix_releasesearch_guid ON releasesearch (guid);

DROP TABLE IF EXISTS country;
CREATE TABLE country (
  ID   INT(11)      NOT NULL AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL DEFAULT "",
  code CHAR(2)      NOT NULL DEFAULT "",
  PRIMARY KEY (ID)
)
  ENGINE =INNODB
  DEFAULT CHARACTER SET utf8
  COLLATE utf8_unicode_ci
  AUTO_INCREMENT =1;

CREATE INDEX ix_country_name ON country (name);

INSERT INTO country (code, name) VALUES ('AF', 'Afghanistan'),
('AX', 'Aland Islands'),
('AL', 'Albania'),
('DZ', 'Algeria'),
('AS', 'American Samoa'),
('AD', 'Andorra'),
('AO', 'Angola'),
('AI', 'Anguilla'),
('AQ', 'Antarctica'),
('AG', 'Antigua and Barbuda'),
('AR', 'Argentina'),
('AM', 'Armenia'),
('AW', 'Aruba'),
('AU', 'Australia'),
('AT', 'Austria'),
('AZ', 'Azerbaijan'),
('BS', 'Bahamas'),
('BH', 'Bahrain'),
('BD', 'Bangladesh'),
('BB', 'Barbados'),
('BY', 'Belarus'),
('BE', 'Belgium'),
('BZ', 'Belize'),
('BJ', 'Benin'),
('BM', 'Bermuda'),
('BT', 'Bhutan'),
('BO', 'Bolivia'),
('BA', 'Bosnia and Herzegovina'),
('BW', 'Botswana'),
('BV', 'Bouvetoya'),
('BR', 'Brazil'),
('VG', 'British Virgin Islands'),
('BN', 'Brunei Darussalam'),
('BG', 'Bulgaria'),
('BF', 'Burkina Faso'),
('BI', 'Burundi'),
('KH', 'Cambodia'),
('CM', 'Cameroon'),
('CA', 'Canada'),
('CV', 'Cape Verde'),
('KY', 'Cayman Islands'),
('CF', 'Central African Republic'),
('TD', 'Chad'),
('CL', 'Chile'),
('CN', 'China'),
('CX', 'Christmas Island'),
('CC', 'Cocos (Keeling) Islands'),
('CO', 'Colombia'),
('KM', 'Comoros the'),
('CD', 'Congo'),
('CG', 'Congo the'),
('CK', 'Cook Islands'),
('CR', 'Costa Rica'),
('CI', 'Cote d\'Ivoire'),
('HR', 'Croatia'),
('CU', 'Cuba'),
('CY', 'Cyprus'),
('CZ', 'Czech Republic'),
('DK', 'Denmark'),
('DJ', 'Djibouti'),
('DM', 'Dominica'),
('DO', 'Dominican Republic'),
('EC', 'Ecuador'),
('EG', 'Egypt'),
('SV', 'El Salvador'),
('GQ', 'Equatorial Guinea'),
('ER', 'Eritrea'),
('EE', 'Estonia'),
('ET', 'Ethiopia'),
('FO', 'Faroe Islands'),
('FK', 'Falkland Islands'),
('FJ', 'Fiji'),
('FI', 'Finland'),
('FR', 'France'),
('GF', 'French Guiana'),
('PF', 'French Polynesia'),
('GA', 'Gabon'),
('GM', 'Gambia'),
('GE', 'Georgia'),
('DE', 'Germany'),
('GH', 'Ghana'),
('GI', 'Gibraltar'),
('GR', 'Greece'),
('GL', 'Greenland'),
('GD', 'Grenada'),
('GP', 'Guadeloupe'),
('GU', 'Guam'),
('GT', 'Guatemala'),
('GG', 'Guernsey'),
('GN', 'Guinea'),
('GW', 'Guinea-Bissau'),
('GY', 'Guyana'),
('HT', 'Haiti'),
('HN', 'Honduras'),
('HK', 'Hong Kong'),
('HU', 'Hungary'),
('IS', 'Iceland'),
('IN', 'India'),
('ID', 'Indonesia'),
('IR', 'Iran'),
('IQ', 'Iraq'),
('IE', 'Ireland'),
('IM', 'Isle of Man'),
('IL', 'Israel'),
('IT', 'Italy'),
('JM', 'Jamaica'),
('JP', 'Japan'),
('JE', 'Jersey'),
('JO', 'Jordan'),
('KZ', 'Kazakhstan'),
('KE', 'Kenya'),
('KI', 'Kiribati'),
('KP', 'Korea'),
('KR', 'Korea'),
('KW', 'Kuwait'),
('KG', 'Kyrgyz Republic'),
('LA', 'Lao'),
('LV', 'Latvia'),
('LB', 'Lebanon'),
('LS', 'Lesotho'),
('LR', 'Liberia'),
('LY', 'Libyan Arab Jamahiriya'),
('LI', 'Liechtenstein'),
('LT', 'Lithuania'),
('LU', 'Luxembourg'),
('MO', 'Macao'),
('MK', 'Macedonia'),
('MG', 'Madagascar'),
('MW', 'Malawi'),
('MY', 'Malaysia'),
('MV', 'Maldives'),
('ML', 'Mali'),
('MT', 'Malta'),
('MH', 'Marshall Islands'),
('MQ', 'Martinique'),
('MR', 'Mauritania'),
('MU', 'Mauritius'),
('YT', 'Mayotte'),
('MX', 'Mexico'),
('FM', 'Micronesia'),
('MD', 'Moldova'),
('MC', 'Monaco'),
('MN', 'Mongolia'),
('ME', 'Montenegro'),
('MS', 'Montserrat'),
('MA', 'Morocco'),
('MZ', 'Mozambique'),
('MM', 'Myanmar'),
('NA', 'Namibia'),
('NR', 'Nauru'),
('NP', 'Nepal'),
('AN', 'Netherlands Antilles'),
('NL', 'Netherlands'),
('NC', 'New Caledonia'),
('NZ', 'New Zealand'),
('NI', 'Nicaragua'),
('NE', 'Niger'),
('NG', 'Nigeria'),
('NU', 'Niue'),
('NF', 'Norfolk Island'),
('MP', 'Northern Mariana Islands'),
('NO', 'Norway'),
('OM', 'Oman'),
('PK', 'Pakistan'),
('PW', 'Palau'),
('PS', 'Palestinian Territory'),
('PA', 'Panama'),
('PG', 'Papua New Guinea'),
('PY', 'Paraguay'),
('PE', 'Peru'),
('PH', 'Philippines'),
('PN', 'Pitcairn Islands'),
('PL', 'Poland'),
('PT', 'Portugal'),
('PR', 'Puerto Rico'),
('QA', 'Qatar'),
('RE', 'Reunion'),
('RO', 'Romania'),
('RU', 'Russian Federation'),
('RW', 'Rwanda'),
('BL', 'Saint Barthelemy'),
('SH', 'Saint Helena'),
('KN', 'Saint Kitts'),
('LC', 'Saint Lucia'),
('MF', 'Saint Martin'),
('PM', 'Saint Pierre'),
('VC', 'Saint Vincent'),
('WS', 'Samoa'),
('SM', 'San Marino'),
('ST', 'Sao Tome'),
('SA', 'Saudi Arabia'),
('SN', 'Senegal'),
('RS', 'Serbia'),
('SC', 'Seychelles'),
('SL', 'Sierra Leone'),
('SG', 'Singapore'),
('SK', 'Slovakia'),
('SI', 'Slovenia'),
('SB', 'Solomon Islands'),
('SO', 'Somalia'),
('ZA', 'South Africa'),
('ES', 'Spain'),
('LK', 'Sri Lanka'),
('SD', 'Sudan'),
('SR', 'Suriname'),
('SZ', 'Swaziland'),
('SE', 'Sweden'),
('CH', 'Switzerland'),
('SY', 'Syrian Arab Republic'),
('TW', 'Taiwan'),
('TJ', 'Tajikistan'),
('TZ', 'Tanzania'),
('TH', 'Thailand'),
('TL', 'Timor-Leste'),
('TG', 'Togo'),
('TK', 'Tokelau'),
('TO', 'Tonga'),
('TT', 'Trinidad and Tobago'),
('TN', 'Tunisia'),
('TR', 'Turkey'),
('TM', 'Turkmenistan'),
('TV', 'Tuvalu'),
('UG', 'Uganda'),
('UA', 'Ukraine'),
('AE', 'United Arab Emirates'),
('GB', 'United Kingdom'),
('US', 'United States'),
('VI', 'United States Virgin Islands'),
('UY', 'Uruguay'),
('UZ', 'Uzbekistan'),
('VU', 'Vanuatu'),
  ('VE', 'Venezuela'),
  ('VN', 'Vietnam'),
  ('WF', 'Wallis and Futuna'),
  ('EH', 'Western Sahara'),
  ('YE', 'Yemen'),
  ('ZM', 'Zambia'),
  ('ZW', 'Zimbabwe');

DROP TABLE IF EXISTS gamesinfo;
CREATE TABLE         gamesinfo (
  id          INT(10) UNSIGNED    NOT NULL AUTO_INCREMENT,
  title       VARCHAR(255)        NOT NULL,
  asin        VARCHAR(128)        DEFAULT NULL,
  url         VARCHAR(1000)       DEFAULT NULL,
  platform    VARCHAR(255)        DEFAULT NULL,
  publisher   VARCHAR(255)        DEFAULT NULL,
  genreid     INT(10)             NULL DEFAULT NULL,
  esrb        VARCHAR(255)        NULL DEFAULT NULL,
  releasedate DATETIME            DEFAULT NULL,
  review      VARCHAR(3000)       DEFAULT NULL,
  cover       TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
  createddate DATETIME            NOT NULL,
  updateddate DATETIME            NOT NULL,
  PRIMARY KEY                    (id),
  UNIQUE INDEX ix_gamesinfo_asin (asin)
)
  ENGINE          = InnoDB
  DEFAULT CHARSET = utf8
  COLLATE         = utf8_unicode_ci
  AUTO_INCREMENT  = 1;

DROP TABLE IF EXISTS shortgroups;
CREATE TABLE shortgroups (
  ID           INT(11)         NOT NULL AUTO_INCREMENT,
  name         VARCHAR(255)    NOT NULL DEFAULT "",
  first_record BIGINT UNSIGNED NOT NULL DEFAULT "0",
  last_record  BIGINT UNSIGNED NOT NULL DEFAULT "0",
  updated      DATETIME DEFAULT NULL,
  PRIMARY KEY (ID)
)
  ENGINE =InnoDB
  DEFAULT CHARACTER SET utf8
  COLLATE utf8_unicode_ci
  AUTO_INCREMENT =1;

CREATE INDEX ix_shortgroups_id ON shortgroups (ID);
CREATE INDEX ix_shortgroups_name ON shortgroups (name);

DROP TABLE IF EXISTS `category`;
CREATE TABLE category
(
  `ID`                   INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `title`                VARCHAR(255)    NOT NULL,
  `parentID`             INT             NULL,
  `status`               INT             NOT NULL DEFAULT '1',
  `minsizetoformrelease` BIGINT UNSIGNED NOT NULL DEFAULT '0',
  `maxsizetoformrelease` BIGINT UNSIGNED NOT NULL DEFAULT '0',
  `description`          VARCHAR(255)    NULL,
  `disablepreview`       TINYINT(1)      NOT NULL DEFAULT '0'
)
  ENGINE =INNODB
  DEFAULT CHARACTER SET utf8
  COLLATE utf8_unicode_ci
  AUTO_INCREMENT =100000;

INSERT INTO category (ID, title) VALUES (1000, 'Console');
INSERT INTO category (ID, title) VALUES (2000, 'Movies');
INSERT INTO category (ID, title) VALUES (3000, 'Audio');
INSERT INTO category (ID, title) VALUES (4000, 'PC');
INSERT INTO category (ID, title) VALUES (5000, 'TV');
INSERT INTO category (ID, title) VALUES (6000, 'XXX');
INSERT INTO category (ID, title) VALUES (7000, 'Books');
INSERT INTO category (ID, title) VALUES (8000, 'Other');

INSERT INTO category (ID, title, parentID) VALUES (1010, 'NDS', 1000);
INSERT INTO category (ID, title, parentID) VALUES (1020, 'PSP', 1000);
INSERT INTO category (ID, title, parentID) VALUES (1030, 'Wii', 1000);
INSERT INTO category (ID, title, parentID) VALUES (1040, 'Xbox', 1000);
INSERT INTO category (ID, title, parentID) VALUES (1050, 'Xbox 360', 1000);
INSERT INTO category (ID, title, parentID) VALUES (1060, 'WiiWare/VC', 1000);
INSERT INTO category (ID, title, parentID) VALUES (1070, 'XBOX 360 DLC', 1000);
INSERT INTO category (ID, title, parentID) VALUES (1080, 'PS3', 1000);
INSERT INTO category (ID, title, parentID) VALUES (1090, 'Other', 1000);
INSERT INTO category (ID, title, parentID) VALUES (1110, '3DS', 1000);
INSERT INTO category (ID, title, parentID) VALUES (1120, 'PS Vita', 1000);
INSERT INTO category (ID, title, parentID) VALUES (1130, 'WiiU', 1000);
INSERT INTO category (ID, title, parentID) VALUES (1140, 'Xbox One', 1000);
INSERT INTO category (ID, title, parentID) VALUES (1180, 'PS4', 1000);

INSERT INTO category (ID, title, parentID) VALUES (2010, 'Foreign', 2000);
INSERT INTO category (ID, title, parentID) VALUES (2020, 'Other', 2000);
INSERT INTO category (ID, title, parentID) VALUES (2030, 'SD', 2000);
INSERT INTO category (ID, title, parentID) VALUES (2040, 'HD', 2000);
INSERT INTO category (ID, title, parentID) VALUES (2050, '3D', 2000);
INSERT INTO category (ID, title, parentID) VALUES (2060, 'BluRay', 2000);
INSERT INTO category (ID, title, parentID) VALUES (2070, 'DVD', 2000);

INSERT INTO category (ID, title, parentID) VALUES (3010, 'MP3', 3000);
INSERT INTO category (ID, title, parentID) VALUES (3020, 'Video', 3000);
INSERT INTO category (ID, title, parentID) VALUES (3030, 'Audiobook', 3000);
INSERT INTO category (ID, title, parentID) VALUES (3040, 'Lossless', 3000);
INSERT INTO category (ID, title, parentID) VALUES (3050, 'Other', 3000);
INSERT INTO category (ID, title, parentID) VALUES (3060, 'Foreign', 3000);

INSERT INTO category (ID, title, parentID) VALUES (4010, '0day', 4000);
INSERT INTO category (ID, title, parentID) VALUES (4020, 'ISO', 4000);
INSERT INTO category (ID, title, parentID) VALUES (4030, 'Mac', 4000);
INSERT INTO category (ID, title, parentID) VALUES (4040, 'Mobile-Other', 4000);
INSERT INTO category (ID, title, parentID) VALUES (4050, 'Games', 4000);
INSERT INTO category (ID, title, parentID) VALUES (4060, 'Mobile-iOS', 4000);
INSERT INTO category (ID, title, parentID) VALUES (4070, 'Mobile-Android', 4000);

INSERT INTO category (ID, title, parentID) VALUES (5010, 'WEB-DL', 5000);
INSERT INTO category (ID, title, parentID) VALUES (5020, 'Foreign', 5000);
INSERT INTO category (ID, title, parentID) VALUES (5030, 'SD', 5000);
INSERT INTO category (ID, title, parentID) VALUES (5040, 'HD', 5000);
INSERT INTO category (ID, title, parentID) VALUES (5050, 'Other', 5000);
INSERT INTO category (ID, title, parentID) VALUES (5060, 'Sport', 5000);
INSERT INTO category (ID, title, parentID) VALUES (5070, 'Anime', 5000);
INSERT INTO category (ID, title, parentID) VALUES (5080, 'Documentary', 5000);

INSERT INTO category (ID, title, parentID) VALUES (6010, 'DVD', 6000);
INSERT INTO category (ID, title, parentID) VALUES (6020, 'WMV', 6000);
INSERT INTO category (ID, title, parentID) VALUES (6030, 'XviD', 6000);
INSERT INTO category (ID, title, parentID) VALUES (6040, 'x264', 6000);
INSERT INTO category (ID, title, parentID) VALUES (6050, 'Pack', 6000);
INSERT INTO category (ID, title, parentID) VALUES (6060, 'ImgSet', 6000);
INSERT INTO category (ID, title, parentID) VALUES (6070, 'Other', 6000);

INSERT INTO category (ID, title, parentID) VALUES (7010, 'Mags', 7000);
INSERT INTO category (ID, title, parentID) VALUES (7020, 'Ebook', 7000);
INSERT INTO category (ID, title, parentID) VALUES (7030, 'Comics', 7000);
INSERT INTO category (ID, title, parentID) VALUES (7040, 'Technical', 7000);
INSERT INTO category (ID, title, parentID) VALUES (7050, 'Other', 7000);
INSERT INTO category (ID, title, parentID) VALUES (7060, 'Foreign', 7000);

INSERT INTO category (ID, title, parentID) VALUES (8010, 'Misc', 8000);
INSERT INTO category (ID, title, parentID) VALUES (8020, 'Hashed', 8000);

DROP TABLE IF EXISTS sharing_sites;
CREATE TABLE sharing_sites (
  ID         INT(11) UNSIGNED   NOT NULL AUTO_INCREMENT,
  site_name  VARCHAR(255)       NOT NULL DEFAULT '',
  site_guid  VARCHAR(40)        NOT NULL DEFAULT '',
  last_time  DATETIME DEFAULT NULL,
  first_time DATETIME DEFAULT NULL,
  enabled    TINYINT(1)         NOT NULL DEFAULT '0',
  comments   MEDIUMINT UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (ID)
)
  ENGINE =InnoDB
  DEFAULT CHARACTER SET utf8
  COLLATE utf8_unicode_ci
  AUTO_INCREMENT =1;

DROP TABLE IF EXISTS sharing;
CREATE TABLE sharing (
  site_guid      VARCHAR(40)        NOT NULL DEFAULT '',
  site_name      VARCHAR(255)       NOT NULL DEFAULT '',
  enabled        TINYINT(1)         NOT NULL DEFAULT '0',
  posting        TINYINT(1)         NOT NULL DEFAULT '0',
  start_position TINYINT(1)         NOT NULL DEFAULT '0',
  fetching       TINYINT(1)         NOT NULL DEFAULT '1',
  auto_enable    TINYINT(1)         NOT NULL DEFAULT '1',
  hide_users     TINYINT(1)         NOT NULL DEFAULT '1',
  last_article   BIGINT UNSIGNED    NOT NULL DEFAULT '0',
  max_push       MEDIUMINT UNSIGNED NOT NULL DEFAULT '40',
  max_pull       MEDIUMINT UNSIGNED NOT NULL DEFAULT '20000',
  max_download   MEDIUMINT UNSIGNED NOT NULL DEFAULT '150',
  PRIMARY KEY (site_guid)
)
  ENGINE =InnoDB
  DEFAULT CHARACTER SET utf8
  COLLATE utf8_unicode_ci;

ALTER TABLE releasecomment ADD COLUMN shared TINYINT(1) NOT NULL DEFAULT '1';
ALTER TABLE releasecomment ADD COLUMN shareID VARCHAR(40) NOT NULL DEFAULT '';
ALTER TABLE releasecomment ADD COLUMN siteID VARCHAR(40) NOT NULL DEFAULT '';
ALTER TABLE releasecomment ADD COLUMN nzb_guid VARCHAR(32) NOT NULL DEFAULT '';
ALTER TABLE releasecomment ADD COLUMN text_hash VARCHAR(32) NOT NULL DEFAULT '';
DROP TRIGGER IF EXISTS insert_MD5;
CREATE TRIGGER insert_MD5 BEFORE INSERT ON releasecomment FOR EACH ROW SET NEW.text_hash = MD5(NEW.text);
UPDATE releasecomment
SET text_hash = MD5(text);
ALTER IGNORE TABLE releasecomment ADD UNIQUE INDEX ix_releasecomment_hash_releaseID (text_hash, releaseID);

ALTER TABLE `releasefiles` ADD COLUMN `ishashed` TINYINT(1) NOT NULL DEFAULT '0'
AFTER `size`;
CREATE INDEX `ix_releasefiles_ishashed` ON `releasefiles` (`ishashed`);

DROP TABLE IF EXISTS predbhash;
CREATE TABLE predbhash (
  pre_id INT(11) UNSIGNED NOT NULL DEFAULT 0,
  hashes VARCHAR(512)     NOT NULL DEFAULT '',
  PRIMARY KEY (pre_id)
)
  ENGINE =MYISAM
  ROW_FORMAT = DYNAMIC
  DEFAULT CHARSET =utf8mb4
  COLLATE =utf8mb4_unicode_ci;

INSERT INTO predbhash (pre_id, hashes) (SELECT
                                          ID,
                                          CONCAT_WS(',', MD5(title), MD5(MD5(title)), SHA1(title))
                                        FROM prehash);

CREATE FULLTEXT INDEX ix_predbhash_hashes_ft ON predbhash (hashes);
ALTER IGNORE TABLE predbhash ADD UNIQUE INDEX ix_predbhash_hashes (hashes(32));

DROP TRIGGER IF EXISTS insert_hashes;

DELIMITER $$
CREATE TRIGGER insert_hashes AFTER INSERT ON prehash FOR EACH ROW BEGIN INSERT INTO predbhash (pre_id, hashes)
VALUES (NEW.ID, CONCAT_WS(',', MD5(NEW.title), MD5(MD5(NEW.title)), SHA1(NEW.title)));
END;
$$
DELIMITER ;

DROP TRIGGER IF EXISTS update_hashes;

DELIMITER $$
CREATE TRIGGER update_hashes AFTER UPDATE ON prehash FOR EACH ROW BEGIN IF NEW.title != OLD.title
THEN UPDATE predbhash
SET hashes = CONCAT_WS(',', MD5(NEW.title), MD5(MD5(NEW.title)), SHA1(NEW.title))
WHERE pre_id = OLD.ID; END IF;
END;
$$
DELIMITER ;

DROP TRIGGER IF EXISTS delete_hashes;

DELIMITER $$
CREATE TRIGGER delete_hashes AFTER DELETE ON prehash FOR EACH ROW BEGIN DELETE FROM predbhash
WHERE pre_id = OLD.ID;
END;
$$
DELIMITER ;

DROP TRIGGER IF EXISTS check_rfinsert;
DROP TRIGGER IF EXISTS check_rfupdate;

DELIMITER $$
CREATE TRIGGER check_rfinsert BEFORE INSERT ON releasefiles FOR EACH ROW BEGIN IF NEW.name REGEXP '[a-fA-F0-9]{32}'
THEN SET NEW.ishashed = 1; END IF;
END;
$$
CREATE TRIGGER check_rfupdate BEFORE UPDATE ON releasefiles FOR EACH ROW BEGIN IF NEW.name REGEXP '[a-fA-F0-9]{32}'
THEN SET NEW.ishashed = 1; END IF;
END;
$$
DELIMITER ;

INSERT INTO menu (href, title, tooltip, role, ordinal)
VALUES ('prehash', 'Prehash',
        'Prehash', 1, 68);
INSERT INTO menu (href, title, tooltip, role, ordinal ) VALUES ('newposterwall', 'New Releases', "Newest Releases Poster Wall", 1, 11);

INSERT INTO `site` (`setting`, `value`) VALUES
  ('categorizeforeign',	'1'),
  ('catwebdl',	'0'),
  ('giantbombkey', '');

DROP TRIGGER IF EXISTS check_insert;
DROP TRIGGER IF EXISTS check_update;

DELIMITER $$
CREATE TRIGGER check_insert BEFORE INSERT ON releases FOR EACH ROW BEGIN IF NEW.searchname REGEXP '[a-fA-F0-9]{32}' OR
                                                                            NEW.name REGEXP '[a-fA-F0-9]{32}'
THEN SET NEW.ishashed = 1;
ELSEIF NEW.name REGEXP '^\\[ ?([[:digit:]]{4,6}) ?\\]|^REQ\s*([[:digit:]]{4,6})|^([[:digit:]]{4,6})-[[:digit:]]{1}\\['
  THEN SET NEW.isrequestid = 1;
ELSEIF NEW.releasenfoID = 0
  THEN SET NEW.nfostatus = -1; END IF;
END;
$$
CREATE TRIGGER check_update BEFORE UPDATE ON releases FOR EACH ROW BEGIN IF NEW.searchname REGEXP '[a-fA-F0-9]{32}' OR
                                                                            NEW.name REGEXP '[a-fA-F0-9]{32}'
THEN SET NEW.ishashed = 1;
ELSEIF NEW.name REGEXP '^\\[ ?([[:digit:]]{4,6}) ?\\]|^REQ\s*([[:digit:]]{4,6})|^([[:digit:]]{4,6})-[[:digit:]]{1}\\['
  THEN SET NEW.isrequestid = 1;
ELSEIF NEW.releasenfoID = 0
  THEN SET NEW.nfostatus = -1; END IF;
END;
$$
CREATE TRIGGER insert_search AFTER INSERT ON releases FOR EACH ROW BEGIN INSERT INTO releasesearch (releaseID, guid, name, searchname)
VALUES (NEW.ID, NEW.guid, NEW.name, NEW.searchname);
END;
$$
CREATE TRIGGER update_search AFTER UPDATE ON releases FOR EACH ROW BEGIN IF NEW.guid != OLD.guid
THEN UPDATE releasesearch
SET guid = NEW.guid
WHERE releaseID = OLD.ID; END IF;
  IF NEW.name != OLD.name
  THEN UPDATE releasesearch
  SET name = NEW.name
  WHERE releaseID = OLD.ID; END IF;
  IF NEW.searchname != OLD.searchname
  THEN UPDATE releasesearch
  SET searchname = NEW.searchname
  WHERE releaseID = OLD.ID; END IF;
END;
$$
CREATE TRIGGER delete_search AFTER DELETE ON releases FOR EACH ROW BEGIN DELETE FROM releasesearch
WHERE releaseID = OLD.ID;
END;
$$
DELIMITER ;