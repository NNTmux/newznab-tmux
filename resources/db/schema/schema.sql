ALTER TABLE releases ADD COLUMN ishashed TINYINT(1) NOT NULL DEFAULT '0';
ALTER TABLE releases ADD COLUMN isrequestid TINYINT(1) NOT NULL DEFAULT '0';
ALTER TABLE releases ADD COLUMN nzbstatus TINYINT(1) NOT NULL DEFAULT '0';
ALTER TABLE releases ADD COLUMN iscategorized TINYINT(1) NOT NULL DEFAULT '0';
ALTER TABLE releases ADD COLUMN isrenamed TINYINT(1) NOT NULL DEFAULT '0';
ALTER TABLE releases ADD COLUMN jpgstatus TINYINT(1) NOT NULL DEFAULT '0';
ALTER TABLE releases ADD COLUMN videostatus TINYINT(1) NOT NULL DEFAULT '0';
ALTER TABLE releases ADD COLUMN audiostatus TINYINT(1) NOT NULL DEFAULT '0';
ALTER TABLE releases ADD COLUMN dehashstatus TINYINT(1) NOT NULL DEFAULT '0';
ALTER TABLE releases ADD COLUMN reqidstatus TINYINT(1) NOT NULL DEFAULT '0';
ALTER TABLE releases ADD COLUMN nfostatus TINYINT(1) NOT NULL DEFAULT '0';
ALTER TABLE releases ADD COLUMN proc_pp TINYINT(1) NOT NULL DEFAULT '0';
ALTER TABLE releases ADD COLUMN proc_sorter TINYINT(1) NOT NULL DEFAULT '0';
ALTER TABLE releases ADD COLUMN proc_par2 TINYINT(1) NOT NULL DEFAULT '0';
ALTER TABLE releases ADD COLUMN proc_nfo TINYINT(1) NOT NULL DEFAULT '0';
ALTER TABLE releases ADD COLUMN proc_files TINYINT(1) NOT NULL DEFAULT '0';
ALTER TABLE releases ADD COLUMN prehashid INT UNSIGNED NOT NULL DEFAULT '0';
ALTER TABLE releasecomment ADD COLUMN shared   TINYINT(1)  NOT NULL DEFAULT '1';
ALTER TABLE releasecomment ADD COLUMN shareid  VARCHAR(40) NOT NULL DEFAULT '';
ALTER TABLE releasecomment ADD COLUMN siteid   VARCHAR(40) NOT NULL DEFAULT '';
ALTER TABLE releasecomment ADD COLUMN nzb_guid VARCHAR(32) NOT NULL DEFAULT '';
ALTER TABLE releasecomment ADD COLUMN text_hash VARCHAR(32) NOT NULL DEFAULT '';
ALTER TABLE releasefiles ADD COLUMN ishashed TINYINT(1) NOT NULL DEFAULT '0';

INSERT INTO site (setting, value) VALUES
('sqlpatch',	'0'),
('categorizeforeign',	'1'),
('catwebdl',	'0'),
('giantbombkey', ''),
('lookupxxx', 1),
('maxxxxprocessed', 100),
('anidbkey', ''),
('alternate_nntp', '0'),
('amazonsleep', '1000'),
('request_hours', '1'),
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
('safebackfilldate', '2012-06-24'),
('postthreads', '1'),
('releasethreads', '1'),
('nzbthreads', '1'),
('maxsizetopostprocess', '100'),
('minsizetopostprocess', '1'),
('postthreadsamazon', '1'),
('postthreadsnon', '1'),
('segmentstodownload', '2'),
('passchkattempts', '1'),
('maxpartsprocessed', '3'),
('trakttvkey', ''),
('fanarttvkey', ''),
('lookuppar2', '1'),
('addpar2', '1'),
('fixnamethreads', '1'),
('fixnamesperrun', '1'),
('zippath', ''),
('processjpg', '1'),
('scrape', '1'),
('nntpretries', '10'),
('imdburl', '0'),
('yydecoderpath', ''),
('ffmpeg_duration', '5'),
('ffmpeg_image_time', '5'),
('processvideos', '0'),
('maxsizetoprocessnfo', '100'),
('minsizetoprocessnfo', '1'),
('nfothreads', '1'),
('extractusingrarinfo', '0'),
('maxnestedlevels', '3'),
('innerfileblacklist', ''),
('miscotherretentionhours',	'0'),
('mischashedretentionhours',	'0'),
('maxnforetries', '5'),
('timeoutpath', ''),
('timeoutseconds', '0'),
('safepartrepair','0'),
('partrepairmaxtries', '3'),
('tablepergroup', '0'),
('nntpproxy','0'),
('book_reqids','7010'),
('intanidbupdate','7'),
('lastanidbupdate','0'),
('banned','0'),
('grabstatus', 1),
('crossposttime',	2),
('deletepossiblerelease', 0),
('nzbsplitlevel', 1),
('maxsizetoformrelease', 0),
('magic_file_path', ''),
('request_url', 'http://reqid.newznab-tmux.pw/'),
('userselstyle', '0');

ALTER TABLE  site
ADD  section VARCHAR(25)  NOT NULL DEFAULT '',
ADD  subsection VARCHAR(25)  NOT NULL DEFAULT '',
ADD  name    VARCHAR(25)  NOT NULL DEFAULT '',
ADD  hint   TEXT NOT NULL;
ALTER TABLE site DROP COLUMN id;
ALTER TABLE site DROP INDEX setting;
UPDATE site SET name = setting;
ALTER TABLE site
ADD PRIMARY KEY (section, subsection, name),
ADD UNIQUE INDEX ui_settings_setting (setting);
RENAME TABLE site TO settings;

DROP TABLE IF EXISTS tmux;
CREATE TABLE tmux
(
    id INT UNSIGNED PRIMARY KEY NOT NULL AUTO_INCREMENT,
    setting VARCHAR(64) NOT NULL,
    value VARCHAR(19000),
    updateddate TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL
);
CREATE UNIQUE INDEX setting ON tmux (setting);

INSERT INTO tmux (setting, value) VALUES
('defrag_cache', '900'),
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
('backfill_order', '2'),
('backfill_days', '1'),
('post_amazon', '0'),
('post_non', '0'),
('post_timer_amazon', '30'),
('post_timer_non', '30'),
('monitor_path_a', ''),
('monitor_path_b', ''),
('showquery', '0'),
('fix_crap_opt', 'Disabled'),
('showprocesslist', '0'),
('processupdate', '2'),
('maxmssgs', '20000'),
('currentppticket', '0'),
('nextppticket', '0'),
('debuginfo', '0'),
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
('lastpretime', '0'),
('run_sharing', '0'),
('sharing_timer', '60'),
('optimize', '0'),
('optimize_timer', '86400'),
('run_ircscraper', '0'),
('colors_start', '1'),
('colors_end', '250'),
('colors_exc', '4, 8, 9, 11, 15, 16, 17, 18, 19, 46, 47, 48, 49, 50, 51, 52, 53, 59, 60'),
('import_count', '50000');

DROP TABLE IF EXISTS prehash;
CREATE TABLE prehash (
    id INT UNSIGNED PRIMARY KEY NOT NULL AUTO_INCREMENT,
    filename VARCHAR(255) DEFAULT '' NOT NULL,
    title VARCHAR(255) DEFAULT '' NOT NULL,
    nfo VARCHAR(255),
    size VARCHAR(50),
    category VARCHAR(255),
    predate DATETIME,
    source VARCHAR(50) DEFAULT '' NOT NULL,
    requestid INT UNSIGNED DEFAULT 0 NOT NULL,
    groupid INT UNSIGNED DEFAULT 0 NOT NULL,
    nuked TINYINT DEFAULT 0 NOT NULL,
    nukereason VARCHAR(255),
    files VARCHAR(50),
    searched TINYINT DEFAULT 0 NOT NULL
);
CREATE UNIQUE INDEX ix_prehash_title ON prehash (title);
CREATE INDEX ix_prehash_category ON prehash (category);
CREATE INDEX ix_prehash_filename ON prehash (filename);
CREATE INDEX ix_prehash_nfo ON prehash (nfo);
CREATE INDEX ix_prehash_predate ON prehash (predate);
CREATE INDEX ix_prehash_requestid ON prehash (requestid, groupid);
CREATE INDEX ix_prehash_searched ON prehash (searched);
CREATE INDEX ix_prehash_size ON prehash (size);
CREATE INDEX ix_prehash_source ON prehash (source);

DROP TABLE IF EXISTS shortgroups;
CREATE TABLE shortgroups (
    id INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) DEFAULT '' NOT NULL,
    first_record BIGINT UNSIGNED DEFAULT 0 NOT NULL,
    last_record BIGINT UNSIGNED DEFAULT 0 NOT NULL,
    updated DATETIME
);
CREATE INDEX ix_shortgroups_id ON shortgroups (id);
CREATE INDEX ix_shortgroups_name ON shortgroups (name);

DROP TABLE IF EXISTS country;
DROP TABLE IF EXISTS countries;

CREATE TABLE countries (
  code CHAR(2) NOT NULL DEFAULT "",
  name VARCHAR(255) NOT NULL DEFAULT "",
  PRIMARY KEY (name)
) ENGINE=MYISAM DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci AUTO_INCREMENT=1;

CREATE INDEX ix_countries_name ON countries (name);

INSERT INTO countries (code, name) VALUES
('AF', 'Afghanistan'),
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
('KP', 'Korea, North'),
('KR', 'Korea, South'),
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

DROP TABLE IF EXISTS category;
CREATE TABLE category
(
  id                   INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
  title                VARCHAR(255)    NOT NULL,
  parentid             INT             NULL,
  status               INT             NOT NULL DEFAULT '1',
  minsizetoformrelease BIGINT UNSIGNED NOT NULL DEFAULT '0',
  maxsizetoformrelease BIGINT UNSIGNED NOT NULL DEFAULT '0',
  description          VARCHAR(255)    NULL,
  disablepreview       TINYINT(1)      NOT NULL DEFAULT '0'
)
  ENGINE =INNODB
  DEFAULT CHARACTER SET utf8
  COLLATE utf8_unicode_ci
  AUTO_INCREMENT =100000;

INSERT INTO category (id, title) VALUES (0000, 'Other');
INSERT INTO category (id, title) VALUES (1000, 'Console');
INSERT INTO category (id, title) VALUES (2000, 'Movies');
INSERT INTO category (id, title) VALUES (3000, 'Audio');
INSERT INTO category (id, title) VALUES (4000, 'PC');
INSERT INTO category (id, title) VALUES (5000, 'TV');
INSERT INTO category (id, title) VALUES (6000, 'XXX');
INSERT INTO category (id, title) VALUES (7000, 'Books');

INSERT INTO category (id, title, parentid) VALUES (0010, 'Misc', 0000);
INSERT INTO category (id, title, parentid) VALUES (0020, 'Hashed', 0000);

INSERT INTO category (id, title, parentid) VALUES (1010, 'NDS', 1000);
INSERT INTO category (id, title, parentid) VALUES (1020, 'PSP', 1000);
INSERT INTO category (id, title, parentid) VALUES (1030, 'Wii', 1000);
INSERT INTO category (id, title, parentid) VALUES (1040, 'Xbox', 1000);
INSERT INTO category (id, title, parentid) VALUES (1050, 'Xbox 360', 1000);
INSERT INTO category (id, title, parentid) VALUES (1060, 'WiiWare/VC', 1000);
INSERT INTO category (id, title, parentid) VALUES (1070, 'XBOX 360 DLC', 1000);
INSERT INTO category (id, title, parentid) VALUES (1080, 'PS3', 1000);
INSERT INTO category (id, title, parentid) VALUES (1999, 'Other', 1000);
INSERT INTO category (id, title, parentid) VALUES (1110, '3DS', 1000);
INSERT INTO category (id, title, parentid) VALUES (1120, 'PS Vita', 1000);
INSERT INTO category (id, title, parentid) VALUES (1130, 'WiiU', 1000);
INSERT INTO category (id, title, parentid) VALUES (1140, 'Xbox One', 1000);
INSERT INTO category (id, title, parentid) VALUES (1180, 'PS4', 1000);

INSERT INTO category (id, title, parentid) VALUES (2010, 'Foreign', 2000);
INSERT INTO category (id, title, parentid) VALUES (2999, 'Other', 2000);
INSERT INTO category (id, title, parentid) VALUES (2030, 'SD', 2000);
INSERT INTO category (id, title, parentid) VALUES (2040, 'HD', 2000);
INSERT INTO category (id, title, parentid) VALUES (2050, '3D', 2000);
INSERT INTO category (id, title, parentid) VALUES (2060, 'BluRay', 2000);
INSERT INTO category (id, title, parentid) VALUES (2070, 'DVD', 2000);
INSERT INTO category (id, title, parentid) VALUES (2080, 'WEB-DL', 2000);

INSERT INTO category (id, title, parentid) VALUES (3010, 'MP3', 3000);
INSERT INTO category (id, title, parentid) VALUES (3020, 'Video', 3000);
INSERT INTO category (id, title, parentid) VALUES (3030, 'Audiobook', 3000);
INSERT INTO category (id, title, parentid) VALUES (3040, 'Lossless', 3000);
INSERT INTO category (id, title, parentid) VALUES (3999, 'Other', 3000);
INSERT INTO category (id, title, parentid) VALUES (3060, 'Foreign', 3000);

INSERT INTO category (id, title, parentid) VALUES (4010, '0day', 4000);
INSERT INTO category (id, title, parentid) VALUES (4020, 'ISO', 4000);
INSERT INTO category (id, title, parentid) VALUES (4030, 'Mac', 4000);
INSERT INTO category (id, title, parentid) VALUES (4040, 'Mobile-Other', 4000);
INSERT INTO category (id, title, parentid) VALUES (4050, 'Games', 4000);
INSERT INTO category (id, title, parentid) VALUES (4060, 'Mobile-iOS', 4000);
INSERT INTO category (id, title, parentid) VALUES (4070, 'Mobile-Android', 4000);

INSERT INTO category (id, title, parentid) VALUES (5010, 'WEB-DL', 5000);
INSERT INTO category (id, title, parentid) VALUES (5020, 'Foreign', 5000);
INSERT INTO category (id, title, parentid) VALUES (5030, 'SD', 5000);
INSERT INTO category (id, title, parentid) VALUES (5040, 'HD', 5000);
INSERT INTO category (id, title, parentid) VALUES (5999, 'Other', 5000);
INSERT INTO category (id, title, parentid) VALUES (5060, 'Sport', 5000);
INSERT INTO category (id, title, parentid) VALUES (5070, 'Anime', 5000);
INSERT INTO category (id, title, parentid) VALUES (5080, 'Documentary', 5000);

INSERT INTO category (id, title, parentid) VALUES (6010, 'DVD', 6000);
INSERT INTO category (id, title, parentid) VALUES (6020, 'WMV', 6000);
INSERT INTO category (id, title, parentid) VALUES (6030, 'XviD', 6000);
INSERT INTO category (id, title, parentid) VALUES (6040, 'x264', 6000);
INSERT INTO category (id, title, parentid) VALUES (6050, 'Pack', 6000);
INSERT INTO category (id, title, parentid) VALUES (6060, 'ImgSet', 6000);
INSERT INTO category (id, title, parentid) VALUES (6999, 'Other', 6000);

INSERT INTO category (id, title, parentid) VALUES (7010, 'Mags', 7000);
INSERT INTO category (id, title, parentid) VALUES (7020, 'Ebook', 7000);
INSERT INTO category (id, title, parentid) VALUES (7030, 'Comics', 7000);
INSERT INTO category (id, title, parentid) VALUES (7040, 'Technical', 7000);
INSERT INTO category (id, title, parentid) VALUES (7999, 'Other', 7000);
INSERT INTO category (id, title, parentid) VALUES (7060, 'Foreign', 7000);

DROP TABLE IF EXISTS sharing_sites;
CREATE TABLE sharing_sites (
	id             INT(11) UNSIGNED   NOT NULL AUTO_INCREMENT,
	site_name      VARCHAR(255)       NOT NULL DEFAULT '',
	site_guid      VARCHAR(40)        NOT NULL DEFAULT '',
	last_time      DATETIME           DEFAULT NULL,
	first_time     DATETIME           DEFAULT NULL,
	enabled        TINYINT(1)         NOT NULL DEFAULT '0',
	comments       MEDIUMINT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY    (id)
) ENGINE=MYISAM DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci AUTO_INCREMENT=1;

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
	max_pull       INT UNSIGNED       NOT NULL DEFAULT '200',
	PRIMARY KEY    (site_guid)
) ENGINE=MYISAM DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;

DROP TABLE IF EXISTS predbhash;
CREATE TABLE predbhash (
  pre_id INT(11) UNSIGNED NOT NULL DEFAULT '0',
  hash VARBINARY(20)      NOT NULL DEFAULT '',
  PRIMARY KEY (hash)
)
  ENGINE = MYISAM
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;


DROP TABLE IF EXISTS predb_imports;
CREATE TABLE predb_imports (
  title      VARCHAR(255)
               COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  nfo        VARCHAR(255)
               COLLATE utf8_unicode_ci          DEFAULT NULL,
  size       VARCHAR(50)
               COLLATE utf8_unicode_ci          DEFAULT NULL,
  category   VARCHAR(255)
               COLLATE utf8_unicode_ci          DEFAULT NULL,
  predate    DATETIME                         DEFAULT NULL,
  source     VARCHAR(50)
               COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  requestid  INT(10) UNSIGNED        NOT NULL DEFAULT '0',
  group_id   INT(10) UNSIGNED        NOT NULL DEFAULT '0'
    COMMENT 'FK to groups',
  nuked      TINYINT(1)              NOT NULL DEFAULT '0'
    COMMENT 'Is this pre nuked? 0 no 2 yes 1 un nuked 3 mod nuked',
  nukereason VARCHAR(255)
               COLLATE utf8_unicode_ci          DEFAULT NULL
  COMMENT 'If this pre is nuked, what is the reason?',
  files      VARCHAR(50)
               COLLATE utf8_unicode_ci          DEFAULT NULL
  COMMENT 'How many files does this pre have ?',
  filename   VARCHAR(255)
               COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  searched   TINYINT(1)              NOT NULL DEFAULT '0',
  groupname  VARCHAR(255)
               COLLATE utf8_unicode_ci          DEFAULT NULL
)
  ENGINE =MYISAM
  DEFAULT CHARSET =utf8
  COLLATE =utf8_unicode_ci;

INSERT INTO predbhash (pre_id, hash) (SELECT id, CONCAT_WS(',', MD5(title), MD5(MD5(title)), SHA1(title)) FROM prehash);

DELIMITER $$
CREATE TRIGGER insert_hashes AFTER INSERT ON prehash FOR EACH ROW
  BEGIN
    INSERT INTO predbhash (hash, pre_id) VALUES (UNHEX(MD5(NEW.title)), NEW.id), (UNHEX(MD5(MD5(NEW.title))), NEW.id), ( UNHEX(SHA1(NEW.title)), NEW.id);
  END; $$

CREATE TRIGGER update_hashes AFTER UPDATE ON prehash FOR EACH ROW
  BEGIN
    IF NEW.title != OLD.title
      THEN
         DELETE FROM predbhash WHERE hash IN ( UNHEX(md5(OLD.title)), UNHEX(md5(md5(OLD.title))), UNHEX(sha1(OLD.title)) ) AND pre_id = OLD.id;
         INSERT INTO predbhash (hash, pre_id) VALUES ( UNHEX(MD5(NEW.title)), NEW.id ), ( UNHEX(MD5(MD5(NEW.title))), NEW.id ), ( UNHEX(SHA1(NEW.title)), NEW.id );
    END IF;
  END; $$

CREATE TRIGGER delete_hashes AFTER DELETE ON prehash FOR EACH ROW
  BEGIN
    DELETE FROM predbhash WHERE hash IN ( UNHEX(md5(OLD.title)), UNHEX(md5(md5(OLD.title))), UNHEX(sha1(OLD.title)) ) AND pre_id = OLD.id;
  END; $$

DELIMITER ;
