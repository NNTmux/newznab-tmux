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
ADD `gamesinfo_id` INT SIGNED NOT NULL DEFAULT '0',
ADD `xxxinfo_id` INT SIGNED NOT NULL DEFAULT '0',
ADD `nzbstatus` TINYINT(1) NOT NULL DEFAULT 0,
ADD `proc_sorter` TINYINT(1) NOT NULL DEFAULT '0';
CREATE INDEX `ix_releases_nfostatus` ON `releases` (`nfostatus` ASC) USING HASH;
CREATE INDEX `ix_releases_reqidstatus` ON `releases` (`reqidstatus` ASC) USING HASH;
CREATE INDEX `ix_releases_passwordstatus` ON `releases` (`passwordstatus`);
CREATE INDEX `ix_releases_releasenfoID` ON `releases` (`releasenfoID`);
CREATE INDEX `ix_releases_dehashstatus` ON `releases` (`dehashstatus`);
CREATE INDEX `ix_releases_haspreview` ON `releases` (`haspreview` ASC) USING HASH;
CREATE INDEX `ix_releases_postdate_name` ON `releases` (`postdate`, `name`);
CREATE INDEX `ix_releases_prehashid_searchname` ON `releases` (`prehashID`, `searchname`);
CREATE INDEX `ix_releases_gamesinfo_id` ON `releases` (`gamesinfo_id`);
CREATE INDEX `ix_releases_xxxinfo_id` ON `releases` (`xxxinfo_id`);
CREATE INDEX `ix_releases_status` ON `releases` (`nzbstatus`, `iscategorized`, `isrenamed`, `nfostatus`, `ishashed`, `passwordstatus`, `dehashstatus`, `releasenfoID`, `musicinfoID`, `consoleinfoID`, `bookinfoID`, `haspreview`, `categoryID`, `imdbID`, `rageID`);

ALTER TABLE users ADD COLUMN gameview INT AFTER consoleview;
ALTER TABLE users ADD COLUMN xxxview INT AFTER consoleview;
ALTER TABLE users ADD COLUMN cp_api  VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE users ADD COLUMN cp_url  VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE users ADD COLUMN queuetype TINYINT(1) NOT NULL DEFAULT 1;


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
('request_hours', '1'),
('trakttvkey', ''),
('fanarttvkey', ''),
('lookuppar2', '0'),
('addpar2', '0'),
('fixnamethreads', '1'),
('fixnamesperrun', '10'),
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
  ('sqlpatch', '73');

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
  publisher   VARCHAR(255)        DEFAULT NULL,
  genre_id     INT(10)             NULL DEFAULT NULL,
  esrb        VARCHAR(255)        NULL DEFAULT NULL,
  releasedate DATETIME            DEFAULT NULL,
  review      VARCHAR(3000)       DEFAULT NULL,
  cover       TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
  backdrop    TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
  trailer     VARCHAR(1000)       NOT NULL DEFAULT '',
  classused   VARCHAR(10)         NOT NULL DEFAULT 'steam' ,
  createddate DATETIME            NOT NULL,
  updateddate DATETIME            NOT NULL,
  PRIMARY KEY                    (id),
  UNIQUE INDEX ix_gamesinfo_asin (asin)
)
  ENGINE          = InnoDB
  DEFAULT CHARSET = utf8
  COLLATE         = utf8_unicode_ci
  AUTO_INCREMENT  = 1;

DROP TABLE IF EXISTS xxxinfo;
CREATE TABLE         xxxinfo (
  id          INT(10) UNSIGNED               NOT NULL AUTO_INCREMENT,
  title       VARCHAR(255)                   NOT NULL,
  tagline     VARCHAR(1024)                  NOT NULL,
  plot        BLOB                           NULL DEFAULT NULL,
  genre       VARCHAR(64)                    NOT NULL,
  director    VARCHAR(64)                    DEFAULT NULL,
  actors      VARCHAR(2000)                  NOT NULL,
  extras      TEXT                           DEFAULT NULL,
  productinfo TEXT                           DEFAULT NULL,
  trailers    TEXT                           DEFAULT NULL,
  directurl   VARCHAR(2000)                  NOT NULL,
  classused   VARCHAR(3)                     NOT NULL,
  cover       TINYINT(1) UNSIGNED            NOT NULL DEFAULT '0',
  backdrop    TINYINT(1) UNSIGNED            NOT NULL DEFAULT '0',
  createddate DATETIME                       NOT NULL,
  updateddate DATETIME                       NOT NULL,
  PRIMARY KEY                      (id),
  INDEX        ix_xxxinfo_title  (title)
)
  ENGINE          = InnoDB
  DEFAULT CHARSET = utf8
  COLLATE         = utf8_unicode_ci
  AUTO_INCREMENT  = 1;

DROP TABLE IF EXISTS `genres`;
CREATE TABLE IF NOT EXISTS `genres` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `type` int(4) DEFAULT NULL,
  `disabled` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ROW_FORMAT=DYNAMIC AUTO_INCREMENT=635;

INSERT IGNORE INTO `genres` (`ID`, `title`, `type`, `disabled`) VALUES
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
  (634, 'Double Penetration (Dp)', 6000, 0);

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

UPDATE releasecomment
SET text_hash = MD5(text);
ALTER IGNORE TABLE releasecomment ADD UNIQUE INDEX ix_releasecomment_hash_gid (text_hash, gid);

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

INSERT INTO menu (href, title, tooltip, role, ordinal)
VALUES ('prehash', 'Prehash',
        'Prehash', 1, 68);
INSERT INTO menu (href, title, tooltip, role, ordinal ) VALUES ('newposterwall', 'New Releases', "Newest Releases Poster Wall", 1, 11);

INSERT INTO `site` (`setting`, `value`) VALUES
  ('categorizeforeign',	'1'),
  ('catwebdl',	'0'),
  ('giantbombkey', ''),
  ('lookupxxx', 1),
  ('maxxxxprocessed', 100),
  ('rottentomatoquality', 'profile'),
  ('anidbkey', '');

ALTER TABLE animetitles CHANGE createddate unixtime INT(12) UNSIGNED NOT NULL;

DROP TRIGGER IF EXISTS insert_hashes;

DELIMITER $$
CREATE TRIGGER insert_hashes AFTER INSERT ON prehash FOR EACH ROW BEGIN INSERT INTO predbhash (pre_id, hashes) VALUES (NEW.ID, CONCAT_WS(',', MD5(NEW.title), MD5(MD5(NEW.title)), SHA1(NEW.title)));END;
$$
DELIMITER ;

DROP TRIGGER IF EXISTS update_hashes;

DELIMITER $$
CREATE TRIGGER update_hashes AFTER UPDATE ON prehash FOR EACH ROW BEGIN IF NEW.title != OLD.title THEN UPDATE predbhash SET hashes = CONCAT_WS(',', MD5(NEW.title), MD5(MD5(NEW.title)), SHA1(NEW.title)) WHERE pre_id = OLD.ID; END IF;
END;
$$
DELIMITER ;

DROP TRIGGER IF EXISTS delete_hashes;

DELIMITER $$
CREATE TRIGGER delete_hashes AFTER DELETE ON prehash FOR EACH ROW BEGIN DELETE FROM predbhash WHERE pre_id = OLD.ID; END;
$$
DELIMITER ;

DROP TRIGGER IF EXISTS check_rfinsert;
DROP TRIGGER IF EXISTS check_rfupdate;

DELIMITER $$
CREATE TRIGGER check_rfinsert BEFORE INSERT ON releasefiles FOR EACH ROW BEGIN IF NEW.name REGEXP '[a-fA-F0-9]{32}'
THEN SET NEW.ishashed = 1; END IF;
END;
$$
CREATE TRIGGER check_rfupdate BEFORE UPDATE ON releasefiles FOR EACH ROW BEGIN IF NEW.name REGEXP '[a-fA-F0-9]{32}' THEN SET NEW.ishashed = 1; END IF; END;
$$
DELIMITER ;

DROP TRIGGER IF EXISTS check_insert;
DROP TRIGGER IF EXISTS check_update;

DELIMITER $$
CREATE TRIGGER check_insert BEFORE INSERT ON releases FOR EACH ROW BEGIN IF NEW.searchname REGEXP '[a-fA-F0-9]{32}' OR NEW.name REGEXP '[a-fA-F0-9]{32}' THEN SET NEW.ishashed = 1;
ELSEIF NEW.name REGEXP '^\\[ ?([[:digit:]]{4,6}) ?\\]|^REQ\s*([[:digit:]]{4,6})|^([[:digit:]]{4,6})-[[:digit:]]{1}\\[' THEN SET NEW.isrequestid = 1; END IF; END;
$$
CREATE TRIGGER check_update BEFORE UPDATE ON releases FOR EACH ROW BEGIN IF NEW.searchname REGEXP '[a-fA-F0-9]{32}' OR NEW.name REGEXP '[a-fA-F0-9]{32}' THEN SET NEW.ishashed = 1;
ELSEIF NEW.name REGEXP '^\\[ ?([[:digit:]]{4,6}) ?\\]|^REQ\s*([[:digit:]]{4,6})|^([[:digit:]]{4,6})-[[:digit:]]{1}\\[' THEN SET NEW.isrequestid = 1;END IF;
END;
$$
CREATE TRIGGER insert_search AFTER INSERT ON releases FOR EACH ROW BEGIN INSERT INTO releasesearch (releaseID, guid, name, searchname) VALUES (NEW.ID, NEW.guid, NEW.name, NEW.searchname);
END;
$$
CREATE TRIGGER update_search AFTER UPDATE ON releases FOR EACH ROW BEGIN IF NEW.guid != OLD.guid THEN UPDATE releasesearch SET guid = NEW.guid WHERE releaseID = OLD.ID; END IF;
IF NEW.name != OLD.name THEN UPDATE releasesearch SET name = NEW.name WHERE releaseID = OLD.ID; END IF; IF NEW.searchname != OLD.searchname THEN UPDATE releasesearch SET searchname = NEW.searchname WHERE releaseID = OLD.ID; END IF;
END;
$$
CREATE TRIGGER delete_search AFTER DELETE ON releases FOR EACH ROW BEGIN DELETE FROM releasesearch WHERE releaseID = OLD.ID; END;
$$
DELIMITER ;

DROP TRIGGER IF EXISTS insert_MD5;
CREATE TRIGGER insert_MD5 BEFORE INSERT ON releasecomment FOR EACH ROW SET NEW.text_hash = MD5(NEW.text);