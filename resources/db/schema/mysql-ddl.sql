DROP TABLE IF EXISTS site;
DROP TABLE IF EXISTS anidb;
DROP TABLE IF EXISTS animetitles;

DROP TABLE IF EXISTS anidb_episodes;
CREATE TABLE anidb_episodes
(
    anidbid INT(10) unsigned NOT NULL COMMENT 'id of title from AniDB',
    episodeid INT(10) unsigned DEFAULT '0' NOT NULL COMMENT 'anidb id for this episode',
    episode_no SMALLINT(5) unsigned NOT NULL COMMENT 'Numeric version of episode (leave 0 for combined episodes).',
    episode_title VARCHAR(255) NOT NULL COMMENT 'Title of the episode (en, x-jat)',
    airdate DATE NOT NULL,
    CONSTRAINT `PRIMARY` PRIMARY KEY (anidbid, episodeid)
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci;

DROP TABLE IF EXISTS anidb_info;
CREATE TABLE anidb_info
(
    anidbid INT(10) unsigned PRIMARY KEY NOT NULL COMMENT 'id of title from AniDB',
    type VARCHAR(32),
    startdate DATE,
    enddate DATE,
    updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
    related VARCHAR(1024),
    similar VARCHAR(1024),
    creators VARCHAR(1024),
    description TEXT,
    rating VARCHAR(5),
    picture VARCHAR(255),
    categories VARCHAR(1024),
    characters VARCHAR(1024)
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci;

CREATE INDEX ix_anidb_info_datetime ON anidb_info (startdate, enddate, updated);

DROP TABLE IF EXISTS anidb_titles;
CREATE TABLE anidb_titles
(
    anidbid INT(10) unsigned NOT NULL COMMENT 'id of title from AniDB',
    type VARCHAR(25) NOT NULL COMMENT 'type of title.',
    lang VARCHAR(25) NOT NULL,
    title VARCHAR(255) NOT NULL,
    CONSTRAINT `PRIMARY` PRIMARY KEY (anidbid, type, lang, title)
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci;

DROP TABLE IF EXISTS audio_data;
CREATE TABLE audio_data
(
    id INT(11) unsigned PRIMARY KEY NOT NULL AUTO_INCREMENT,
    releases_id INT(11) NOT NULL COMMENT 'FK to releases.id',
    audioid INT(2) unsigned,
    audioformat VARCHAR(255),
    audiomode VARCHAR(255),
    audiobitratemode VARCHAR(255),
    audiobitrate VARCHAR(255),
    audiochannels VARCHAR(255),
    audiosamplerate VARCHAR(255),
    audiolibrary VARCHAR(255),
    audiolanguage VARCHAR(255),
    audiotitle VARCHAR(255)
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci;
CREATE UNIQUE INDEX releaseid ON audio_data (releases_id, audioid);

DROP TABLE IF EXISTS binaries;
CREATE TABLE binaries
(
    id BIGINT(20) unsigned PRIMARY KEY NOT NULL AUTO_INCREMENT,
    name VARCHAR(1000) DEFAULT '' NOT NULL,
    collection_id INT(11) unsigned DEFAULT '0' NOT NULL,
    filenumber INT(10) unsigned DEFAULT '0' NOT NULL,
    totalparts INT(11) unsigned DEFAULT '0' NOT NULL,
    currentparts INT(10) unsigned DEFAULT '0' NOT NULL,
    binaryhash BINARY(16) DEFAULT '0               ' NOT NULL,
    partcheck TINYINT(1) unsigned DEFAULT '0' NOT NULL,
    partsize BIGINT(20) unsigned DEFAULT '0' NOT NULL
)

ENGINE = MyISAM
DEFAULT CHARSET = utf8
COLLATE = utf8_unicode_ci
AUTO_INCREMENT = 1;

CREATE UNIQUE INDEX ix_binary_binaryhash ON binaries (binaryhash);
CREATE INDEX ix_binary_collection ON binaries (collection_id);
CREATE INDEX ix_binary_partcheck ON binaries (partcheck);

DROP TABLE IF EXISTS binaryblacklist;
CREATE TABLE binaryblacklist
(
    id INT(11) unsigned PRIMARY KEY NOT NULL AUTO_INCREMENT,
    groupname VARCHAR(255),
    regex VARCHAR(2000) NOT NULL,
    msgcol INT(11) unsigned DEFAULT '1' NOT NULL,
    optype INT(11) unsigned DEFAULT '1' NOT NULL,
    status INT(11) unsigned DEFAULT '1' NOT NULL,
    description VARCHAR(1000),
    last_activity DATE
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci
    AUTO_INCREMENT = 100001;

DROP TABLE IF EXISTS bookinfo;
CREATE TABLE bookinfo
(
    id INT(10) unsigned PRIMARY KEY NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    asin VARCHAR(255),
    url VARCHAR(1000),
    author VARCHAR(255),
    publisher VARCHAR(255),
    publishdate DATETIME,
    review VARCHAR(10000),
    genreid INT(10),
    dewey VARCHAR(10),
    ean VARCHAR(20),
    isbn VARCHAR(20),
    pages INT(10),
    cover TINYINT(1) unsigned DEFAULT '0' NOT NULL,
    createddate DATETIME NOT NULL,
    updateddate DATETIME NOT NULL,
    salesrank INT(10) unsigned,
    overview VARCHAR(3000),
    genre VARCHAR(255) NOT NULL
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci
    AUTO_INCREMENT = 1;
CREATE FULLTEXT INDEX ix_bookinfo_author_title_ft ON bookinfo (author, title);
CREATE INDEX ix_bookinfo_title ON bookinfo (title);

DROP TABLE IF EXISTS categories;
CREATE TABLE categories
(
    id INT(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    parentid INT(11),
    status INT(11) DEFAULT '1' NOT NULL,
    minsizetoformrelease BIGINT(20) unsigned DEFAULT '0' NOT NULL,
    maxsizetoformrelease BIGINT(20) unsigned DEFAULT '0' NOT NULL,
    description VARCHAR(255),
    disablepreview TINYINT(1) DEFAULT '0' NOT NULL
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci
    AUTO_INCREMENT = 1000001;

DROP TABLE IF EXISTS category_regexes;
CREATE TABLE category_regexes
(
    id INT(10) unsigned PRIMARY KEY NOT NULL AUTO_INCREMENT,
    group_regex VARCHAR(255) DEFAULT '' NOT NULL COMMENT 'This is a regex to match against usenet groups',
    regex VARCHAR(5000) DEFAULT '' NOT NULL COMMENT 'Regex used to match a release name to categorize it',
    status TINYINT(1) unsigned DEFAULT '1' NOT NULL COMMENT '1 = ON 0 = OFF',
    description VARCHAR(1000) DEFAULT '' NOT NULL COMMENT 'Optional extra details on this regex',
    ordinal INT(11) DEFAULT '0' NOT NULL COMMENT 'Order to run the regex in',
    categories_id SMALLINT(5) unsigned DEFAULT '10' NOT NULL COMMENT 'Which category id to put the release in'
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci
    AUTO_INCREMENT = 1;
CREATE INDEX ix_category_regexes_category_id ON category_regexes (categories_id);
CREATE INDEX ix_category_regexes_group_regex ON category_regexes (group_regex);
CREATE INDEX ix_category_regexes_ordinal ON category_regexes (ordinal);
CREATE INDEX ix_category_regexes_status ON category_regexes (status);

DROP TABLE IF EXISTS collection_regexes;
CREATE TABLE collection_regexes
(
    id INT(10) unsigned PRIMARY KEY NOT NULL AUTO_INCREMENT,
    group_regex VARCHAR(255) DEFAULT '' NOT NULL COMMENT 'This is a regex to match against usenet groups',
    regex VARCHAR(5000) DEFAULT '' NOT NULL COMMENT 'Regex used for collection grouping',
    status TINYINT(1) unsigned DEFAULT '1' NOT NULL COMMENT '1 = ON 0 = OFF',
    description VARCHAR(1000),
    ordinal INT(11) DEFAULT '0' NOT NULL COMMENT 'Order to run the regex in'
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci
    AUTO_INCREMENT = 100001;
CREATE INDEX ix_collection_regexes_group_regex ON collection_regexes (group_regex);
CREATE INDEX ix_collection_regexes_ordinal ON collection_regexes (ordinal);
CREATE INDEX ix_collection_regexes_status ON collection_regexes (status);

DROP TABLE IF EXISTS collections;
CREATE TABLE collections
(
    id INT(11) unsigned PRIMARY KEY NOT NULL AUTO_INCREMENT,
    subject VARCHAR(255) DEFAULT '' NOT NULL,
    fromname VARCHAR(255) DEFAULT '' NOT NULL,
    DATE DATETIME,
    xref VARCHAR(255) DEFAULT '' NOT NULL,
    totalfiles INT(11) unsigned DEFAULT '0' NOT NULL,
    group_id INT(11) unsigned DEFAULT '0' NOT NULL,
    collectionhash VARCHAR(255) DEFAULT '0' NOT NULL,
    dateadded DATETIME,
    filecheck TINYINT(3) unsigned DEFAULT '0' NOT NULL,
    filesize BIGINT(20) unsigned DEFAULT '0' NOT NULL,
    releaseid INT(11),
    noise CHAR(32) DEFAULT '' NOT NULL,
    added TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci
    AUTO_INCREMENT = 1;
CREATE INDEX DATE ON collections (DATE);
CREATE INDEX fromname ON collections (fromname);
CREATE INDEX group_id ON collections (group_id);
CREATE INDEX ix_collection_added ON collections (added);
CREATE UNIQUE INDEX ix_collection_collectionhash ON collections (collectionhash);
CREATE INDEX ix_collection_dateadded ON collections (dateadded);
CREATE INDEX ix_collection_filecheck ON collections (filecheck);
CREATE INDEX ix_collection_releaseid ON collections (releaseid);

DROP TABLE IF EXISTS consoleinfo;
CREATE TABLE consoleinfo
(
    id INT(10) unsigned PRIMARY KEY NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    asin VARCHAR(128),
    url VARCHAR(1000),
    salesrank INT(10) unsigned,
    platform VARCHAR(255),
    publisher VARCHAR(255),
    genreid INT(10),
    esrb VARCHAR(255),
    releasedate DATETIME,
    review VARCHAR(10000),
    cover TINYINT(1) unsigned DEFAULT '0' NOT NULL,
    createddate DATETIME NOT NULL,
    updateddate DATETIME NOT NULL
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci
    AUTO_INCREMENT = 1;
CREATE INDEX ix_consoleinfo_title ON consoleinfo (title);
CREATE FULLTEXT INDEX ix_consoleinfo_title_platform_ft ON consoleinfo (title, platform);

DROP TABLE IF EXISTS content;
CREATE TABLE content
(
    id INT(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    url VARCHAR(2000),
    body TEXT,
    metadescription VARCHAR(1000) NOT NULL,
    metakeywords VARCHAR(1000) NOT NULL,
    contenttype INT(11) NOT NULL,
    showinmenu INT(11) NOT NULL,
    status INT(11) NOT NULL,
    ordinal INT(11),
    role INT(11) DEFAULT '0' NOT NULL
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci
    AUTO_INCREMENT  =  1000001;

DROP TABLE IF EXISTS countries;
CREATE TABLE countries
(
    id CHAR(2) PRIMARY KEY NOT NULL COMMENT '2 character code.',
    iso3 CHAR(3) NOT NULL COMMENT '3 character code.',
    country VARCHAR(180) NOT NULL COMMENT 'Name of the country.'
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci;
CREATE UNIQUE INDEX code3 ON countries (iso3);
CREATE UNIQUE INDEX country ON countries (country);

DROP TABLE IF EXISTS dnzb_failures;
CREATE TABLE dnzb_failures
(
    release_id INT(11) unsigned NOT NULL,
    users_id INT(11) unsigned NOT NULL COMMENT 'FK to users.id',
    failed INT(10) unsigned DEFAULT '0' NOT NULL,
    CONSTRAINT `PRIMARY` PRIMARY KEY (release_id, users_id)
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci;

DROP TABLE IF EXISTS episodeinfo;

DROP TABLE IF EXISTS forumpost;
CREATE TABLE forumpost
(
    id INT(11) unsigned PRIMARY KEY NOT NULL AUTO_INCREMENT,
    forumid INT(11),
    parentid INT(11),
    users_id INT(11) unsigned NOT NULL COMMENT 'FK to users.id',
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    locked TINYINT(1) unsigned DEFAULT '0' NOT NULL,
    sticky TINYINT(1) unsigned DEFAULT '0' NOT NULL,
    replies INT(11) unsigned DEFAULT '0' NOT NULL,
    createddate DATETIME NOT NULL,
    updateddate DATETIME NOT NULL
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci
    AUTO_INCREMENT = 1;
CREATE INDEX createddate ON forumpost (createddate);
CREATE INDEX parentid ON forumpost (parentid);
CREATE INDEX updateddate ON forumpost (updateddate);
CREATE INDEX userid ON forumpost (users_id);

DROP TABLE IF EXISTS gamesinfo;
CREATE TABLE gamesinfo
(
    id INT(10) unsigned PRIMARY KEY NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    asin VARCHAR(128),
    url VARCHAR(1000),
    platform VARCHAR(255),
    publisher VARCHAR(255),
    genre_id INT(10),
    esrb VARCHAR(255),
    releasedate DATETIME,
    review VARCHAR(3000),
    cover TINYINT(1) unsigned DEFAULT '0' NOT NULL,
    backdrop TINYINT(1) DEFAULT '0',
    trailer VARCHAR(1000) DEFAULT '',
    classused VARCHAR(10) DEFAULT 'steam',
    createddate DATETIME NOT NULL,
    updateddate DATETIME NOT NULL
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci
    AUTO_INCREMENT = 1;
CREATE UNIQUE INDEX ix_gamesinfo_asin ON gamesinfo (asin);

DROP TABLE IF EXISTS genres;
CREATE TABLE genres
(
    id INT(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    type INT(4),
    disabled TINYINT(1) DEFAULT '0' NOT NULL
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci
    AUTO_INCREMENT  =  1000001;

DROP TABLE IF EXISTS groups;
CREATE TABLE groups
(
    id INT(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) DEFAULT '' NOT NULL,
    backfill_target INT(4) DEFAULT '1' NOT NULL,
    first_record BIGINT(20) unsigned DEFAULT '0' NOT NULL,
    first_record_postdate DATETIME,
    last_record BIGINT(20) unsigned DEFAULT '0' NOT NULL,
    last_record_postdate DATETIME,
    last_updated DATETIME,
    minfilestoformrelease INT(4),
    minsizetoformrelease BIGINT(20),
    regexmatchonly TINYINT(1) DEFAULT '1' NOT NULL,
    active TINYINT(1) DEFAULT '0' NOT NULL,
    backfill TINYINT(1) DEFAULT '0' NOT NULL,
    description VARCHAR(255) DEFAULT ''
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci
    AUTO_INCREMENT = 100001;
CREATE INDEX active ON groups (active);
CREATE UNIQUE INDEX name ON groups (name);

DROP TABLE IF EXISTS invitations;
CREATE TABLE invitations
(
    id INT(11) unsigned PRIMARY KEY NOT NULL AUTO_INCREMENT,
    guid VARCHAR(50) NOT NULL,
    users_id INT(11) unsigned NOT NULL COMMENT 'FK to users.id',
    createddate DATETIME NOT NULL
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci
    AUTO_INCREMENT = 1;

DROP TABLE IF EXISTS logging;
CREATE TABLE logging
(
    id INT(10) unsigned PRIMARY KEY NOT NULL AUTO_INCREMENT,
    time DATETIME,
    username VARCHAR(50),
    host VARCHAR(40)
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci
    AUTO_INCREMENT = 1;

DROP TABLE IF EXISTS menu;
CREATE TABLE menu
(
    id INT(11) unsigned PRIMARY KEY NOT NULL AUTO_INCREMENT,
    href VARCHAR(2000) DEFAULT '' NOT NULL,
    title VARCHAR(2000) DEFAULT '' NOT NULL,
    newwindow INT(1) unsigned DEFAULT '0' NOT NULL,
    tooltip VARCHAR(2000) DEFAULT '' NOT NULL,
    role INT(11) unsigned NOT NULL,
    ordinal INT(11) unsigned NOT NULL,
    menueval VARCHAR(2000) DEFAULT '' NOT NULL
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci
    AUTO_INCREMENT  =  1000001;

DROP TABLE IF EXISTS missed_parts;
CREATE TABLE missed_parts
(
    id INT(16) unsigned PRIMARY KEY NOT NULL AUTO_INCREMENT,
    numberid BIGINT(20) unsigned NOT NULL,
    group_id INT(11) unsigned DEFAULT '0' NOT NULL COMMENT 'FK to groups',
    attempts TINYINT(1) DEFAULT '0' NOT NULL
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci
    AUTO_INCREMENT = 1;
CREATE INDEX ix_partrepair_attempts ON missed_parts (attempts);
CREATE INDEX ix_partrepair_groupid_attempts ON missed_parts (group_id, attempts);
CREATE UNIQUE INDEX ix_partrepair_numberid_groupid ON missed_parts (numberid, group_id);
CREATE INDEX ix_partrepair_numberid_groupid_attempts ON missed_parts (numberid, group_id, attempts);

DROP TABLE IF EXISTS movieinfo;
CREATE TABLE movieinfo
(
    id INT(10) unsigned PRIMARY KEY NOT NULL AUTO_INCREMENT,
    imdbid MEDIUMINT(7) unsigned zerofill,
    tmdbid INT(10) unsigned DEFAULT '0' NOT NULL,
    title VARCHAR(255) DEFAULT '' NOT NULL,
    tagline VARCHAR(1024) DEFAULT '' NOT NULL,
    rating VARCHAR(4) DEFAULT '' NOT NULL,
    plot VARCHAR(1024) DEFAULT '' NOT NULL,
    year VARCHAR(4) DEFAULT '' NOT NULL,
    genre VARCHAR(64) DEFAULT '' NOT NULL,
    type VARCHAR(32) DEFAULT '' NOT NULL,
    director VARCHAR(64) DEFAULT '' NOT NULL,
    actors VARCHAR(2000) DEFAULT '' NOT NULL,
    language VARCHAR(64) DEFAULT '' NOT NULL,
    cover TINYINT(1) unsigned DEFAULT '0' NOT NULL,
    backdrop TINYINT(1) unsigned DEFAULT '0' NOT NULL,
    trailer VARCHAR(255),
    createddate DATETIME NOT NULL,
    updateddate DATETIME NOT NULL,
    banner TINYINT(1) unsigned DEFAULT '0' NOT NULL
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci
    AUTO_INCREMENT = 1;
CREATE UNIQUE INDEX imdbid ON movieinfo (imdbid);
CREATE INDEX ix_movieinfo_title ON movieinfo (title);

DROP TABLE IF EXISTS musicinfo;
CREATE TABLE musicinfo
(
    id INT(10) unsigned PRIMARY KEY NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    asin VARCHAR(128),
    url VARCHAR(1000),
    salesrank INT(10) unsigned,
    artist VARCHAR(255),
    publisher VARCHAR(255),
    releasedate DATETIME,
    review VARCHAR(10000),
    year VARCHAR(4),
    genreid INT(10),
    tracks VARCHAR(3000),
    cover TINYINT(1) unsigned DEFAULT '0' NOT NULL,
    createddate DATETIME NOT NULL,
    updateddate DATETIME NOT NULL
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci
    AUTO_INCREMENT = 1;
CREATE FULLTEXT INDEX ix_musicinfo_artist_title_ft ON musicinfo (artist, title);
CREATE INDEX ix_musicinfo_title ON musicinfo (title);

DROP TABLE IF EXISTS parts;
CREATE TABLE parts
(
    binaryid BIGINT(20) unsigned DEFAULT '0' NOT NULL,
    messageid VARCHAR(255) DEFAULT '' NOT NULL,
    number BIGINT(20) unsigned DEFAULT '0' NOT NULL,
    partnumber MEDIUMINT(10) unsigned DEFAULT '0' NOT NULL,
    size MEDIUMINT(20) unsigned DEFAULT '0' NOT NULL,
    CONSTRAINT `PRIMARY` PRIMARY KEY (binaryid, number)
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci;

DROP TABLE IF EXISTS predb;
CREATE TABLE predb
(
    id INT(11) unsigned PRIMARY KEY NOT NULL AUTO_INCREMENT,
    filename VARCHAR(255) DEFAULT '' NOT NULL,
    title VARCHAR(255) DEFAULT '' NOT NULL,
    nfo VARCHAR(255),
    size VARCHAR(50),
    category VARCHAR(255),
    predate DATETIME,
    source VARCHAR(50) DEFAULT '' NOT NULL,
    requestid INT(10) unsigned DEFAULT '0' NOT NULL,
    groups_id INT(11) unsigned DEFAULT '0' NOT NULL COMMENT 'FK to groups.id',
    nuked TINYINT(1) DEFAULT '0' NOT NULL,
    nukereason VARCHAR(255),
    files VARCHAR(50),
    searched TINYINT(1) DEFAULT '0' NOT NULL
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci
    AUTO_INCREMENT = 1;
CREATE FULLTEXT INDEX ft_predb_filename ON predb (filename);
CREATE INDEX ix_predb_category ON predb (category);
CREATE INDEX ix_predb_filename ON predb (filename);
CREATE INDEX ix_predb_nfo ON predb (nfo);
CREATE INDEX ix_predb_predate ON predb (predate);
CREATE INDEX ix_predb_requestid ON predb (requestid, groups_id);
CREATE INDEX ix_predb_searched ON predb (searched);
CREATE INDEX ix_predb_size ON predb (size);
CREATE INDEX ix_predb_source ON predb (source);
CREATE UNIQUE INDEX ix_predb_title ON predb (title);

DROP TABLE IF EXISTS predb_hashes;
CREATE TABLE predb_hashes
(
    hash VARBINARY(20) PRIMARY KEY NOT NULL,
    predb_id INT(11) unsigned NOT NULL COMMENT 'id, of the predb entry, this hash belongs to'
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8mb4
    COLLATE = utf8mb4_unicode_ci;

DROP TABLE IF EXISTS predb_imports;
CREATE TABLE predb_imports
(
    title VARCHAR(255) DEFAULT '' NOT NULL,
    nfo VARCHAR(255),
    size VARCHAR(50),
    category VARCHAR(255),
    predate DATETIME,
    source VARCHAR(50) DEFAULT '' NOT NULL,
    requestid INT(10) unsigned DEFAULT '0' NOT NULL,
    groups_id INT(11) unsigned DEFAULT '0' NOT NULL COMMENT 'FK to groups.id',
    nuked TINYINT(1) DEFAULT '0' NOT NULL COMMENT 'Is this pre nuked? 0 no 2 yes 1 un nuked 3 mod nuked',
    nukereason VARCHAR(255) COMMENT 'If this pre is nuked, what is the reason?',
    files VARCHAR(50) COMMENT 'How many files does this pre have ?',
    filename VARCHAR(255) DEFAULT '' NOT NULL,
    searched TINYINT(1) DEFAULT '0' NOT NULL,
    groupname VARCHAR(255)
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci;

DROP TABLE IF EXISTS release_comments;
CREATE TABLE release_comments
(
    id INT(11) unsigned PRIMARY KEY NOT NULL AUTO_INCREMENT,
    sourceid BIGINT(20) unsigned,
    gid VARCHAR(32),
    cid VARCHAR(32),
    releases_id INT(11) NOT NULL COMMENT 'FK to releases.id',
    TEXT VARCHAR(255),
    isvisible TINYINT(1) DEFAULT '1',
    issynced TINYINT(1) DEFAULT '0',
    users_id INT(11) unsigned NOT NULL COMMENT 'FK to users.id',
    username VARCHAR(50),
    createddate DATETIME,
    host VARCHAR(40),
    shared TINYINT(1) DEFAULT '1' NOT NULL,
    shareid VARCHAR(40) DEFAULT '' NOT NULL,
    siteid VARCHAR(40) DEFAULT '' NOT NULL,
    nzb_guid BINARY(16) DEFAULT '0               ' NOT NULL,
    text_hash VARCHAR(32) DEFAULT '' NOT NULL
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci
    AUTO_INCREMENT = 1;
CREATE INDEX ix_releasecomment_cid ON release_comments (cid);
CREATE INDEX ix_releasecomment_gid ON release_comments (gid);
CREATE INDEX ix_releasecomment_releaseid ON release_comments (releases_id);
CREATE INDEX ix_releasecomment_userid ON release_comments (users_id);
CREATE UNIQUE INDEX ux_text_hash_siteid_nzb_guid ON release_comments (text_hash, siteid, nzb_guid);
CREATE UNIQUE INDEX ux_text_siteid_nzb_guid ON release_comments (TEXT, siteid, nzb_guid);

DROP TABLE IF EXISTS release_files;
CREATE TABLE release_files
(
    releases_id INT(11) NOT NULL COMMENT 'FK to releases.id',
    name VARCHAR(255) DEFAULT '' NOT NULL,
    size BIGINT(20) unsigned DEFAULT '0' NOT NULL,
    ishashed TINYINT(1) DEFAULT '0' NOT NULL,
    createddate DATETIME,
    passworded TINYINT(1) unsigned DEFAULT '0' NOT NULL,
    CONSTRAINT `PRIMARY` PRIMARY KEY (releases_id, name)
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci;
CREATE INDEX ix_releasefiles_ishashed ON release_files (ishashed);

DROP TABLE IF EXISTS release_naming_regexes;
CREATE TABLE release_naming_regexes
(
    id INT(10) unsigned PRIMARY KEY NOT NULL AUTO_INCREMENT,
    group_regex VARCHAR(255) DEFAULT '' NOT NULL COMMENT 'This is a regex to match against usenet groups',
    regex VARCHAR(2000) NOT NULL,
    status TINYINT(1) unsigned DEFAULT '1' NOT NULL COMMENT '1 = ON 0 = OFF',
    description VARCHAR(1000),
    ordinal INT(11) DEFAULT '0' NOT NULL COMMENT 'Order to run the regex in'
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci
    AUTO_INCREMENT = 1000001;
CREATE INDEX ix_release_naming_regexes_group_regex ON release_naming_regexes (group_regex);
CREATE INDEX ix_release_naming_regexes_ordinal ON release_naming_regexes (ordinal);
CREATE INDEX ix_release_naming_regexes_status ON release_naming_regexes (status);

DROP TABLE IF EXISTS release_unique;
CREATE TABLE release_unique
(
    releases_id INT(11) unsigned NOT NULL COMMENT 'FK to releases.id.',
    uniqueid BINARY(16) DEFAULT '0               ' NOT NULL COMMENT 'Unique_ID from mediainfo.',
    CONSTRAINT `PRIMARY` PRIMARY KEY (releases_id, uniqueid)
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci;

DROP TABLE IF EXISTS releaseextrafull;
CREATE TABLE releaseextrafull
(
    releases_id INT(11) PRIMARY KEY NOT NULL COMMENT 'FK to releases.id',
    mediainfo TEXT
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci;

DROP TABLE IF EXISTS release_nfos;
CREATE TABLE release_nfos
(
    id INT(11) unsigned PRIMARY KEY NOT NULL AUTO_INCREMENT,
    releases_id INT(11) unsigned,
    binaryid INT(11) unsigned,
    nfo BLOB
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci
    AUTO_INCREMENT = 1;
CREATE INDEX ix_releasenfo_binaryid ON release_nfos (binaryid);
CREATE UNIQUE INDEX ix_releasenfo_releaseid ON release_nfos (releases_id);

DROP TABLE IF EXISTS releases;
CREATE TABLE releases
(
    id INT(11) unsigned NOT NULL AUTO_INCREMENT,
    gid VARCHAR(32),
    name VARCHAR(255) DEFAULT '' NOT NULL,
    searchname VARCHAR(255) DEFAULT '' NOT NULL,
    totalpart INT(11) DEFAULT '0',
    groups_id INT(11) unsigned DEFAULT '0' NOT NULL COMMENT 'FK to groups.id',
    size BIGINT(20) unsigned DEFAULT '0' NOT NULL,
    postdate DATETIME,
    adddate DATETIME,
    updatedate TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
    guid VARCHAR(50) NOT NULL,
    leftguid CHAR(1) NOT NULL COMMENT 'The first letter of the release guid',
    fromname VARCHAR(255),
    completion FLOAT DEFAULT '0' NOT NULL,
    categories_id INT(11) DEFAULT '10' NOT NULL,
    videos_id MEDIUMINT(11) unsigned DEFAULT '0' NOT NULL COMMENT 'FK to videos.id of the parent series.',
    tv_episodes_id MEDIUMINT(11) DEFAULT '0' NOT NULL COMMENT 'FK to tv_episodes.id of the episode',
    imdbid MEDIUMINT(7) unsigned zerofill,
    musicinfo_id INT(11) unsigned COMMENT 'FK to musicinfo.id',
    consoleinfo_id INT(11) unsigned COMMENT 'FK to consoleinfo.id',
    bookinfo_id INT(11) unsigned COMMENT 'FK to bookinfo.id',
    anidbid INT(11),
    reqid VARCHAR(255),
    releasenfoid INT(11),
    grabs INT(10) unsigned DEFAULT '0' NOT NULL,
    comments INT(11) DEFAULT '0' NOT NULL,
    passwordstatus INT(11) DEFAULT '0' NOT NULL,
    rarinnerfilecount INT(11) DEFAULT '0' NOT NULL,
    haspreview INT(11) DEFAULT '0' NOT NULL,
    dehashstatus TINYINT(1) DEFAULT '0' NOT NULL,
    nfostatus TINYINT(4) DEFAULT '0' NOT NULL,
    jpgstatus TINYINT(1) DEFAULT '0' NOT NULL,
    audiostatus TINYINT(1) DEFAULT '0' NOT NULL,
    videostatus TINYINT(1) DEFAULT '0' NOT NULL,
    reqidstatus TINYINT(1) DEFAULT '0' NOT NULL,
    predb_id INT(11) unsigned NOT NULL COMMENT 'id, of the predb entry, this hash belongs to',
    iscategorized TINYINT(1) DEFAULT '0' NOT NULL,
    isrenamed TINYINT(1) DEFAULT '0' NOT NULL,
    ishashed TINYINT(1) DEFAULT '0' NOT NULL,
    isrequestid TINYINT(1) DEFAULT '0' NOT NULL,
    proc_pp TINYINT(1) DEFAULT '0' NOT NULL,
    proc_par2 TINYINT(1) DEFAULT '0' NOT NULL,
    proc_nfo TINYINT(1) DEFAULT '0' NOT NULL,
    proc_files TINYINT(1) DEFAULT '0' NOT NULL,
    proc_uid TINYINT(1) DEFAULT '0' NOT NULL COMMENT 'Has the release been UID processed?',
    proc_sorter TINYINT(1) DEFAULT '0' NOT NULL,
    gamesinfo_id INT(10) DEFAULT '0' NOT NULL,
    xxxinfo_id INT(10) DEFAULT '0' NOT NULL,
    nzbstatus TINYINT(1) DEFAULT '0' NOT NULL,
    nzb_guid BINARY(16),
    CONSTRAINT `PRIMARY` PRIMARY KEY (id, categories_id)
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci
    AUTO_INCREMENT = 1
    PARTITION BY RANGE (categories_id)(
    PARTITION misc VALUES LESS THAN (1000),
    PARTITION console VALUES LESS THAN (2000),
    PARTITION movies VALUES LESS THAN (3000),
    PARTITION audio VALUES LESS THAN (4000),
    PARTITION pc VALUES LESS THAN (5000),
    PARTITION tv VALUES LESS THAN (6000),
    PARTITION xxx VALUES LESS THAN (7000),
    PARTITION books VALUES LESS THAN (8000));
CREATE INDEX ix_releases_anidbid ON releases (anidbid);
CREATE INDEX ix_releases_bookinfoid ON releases (bookinfo_id);
CREATE INDEX ix_releases_consoleinfoid ON releases (consoleinfo_id);
CREATE INDEX ix_releases_dehashstatus ON releases (dehashstatus, ishashed);
CREATE INDEX ix_releases_gamesinfo_id ON releases (gamesinfo_id);
CREATE INDEX ix_releases_gid ON releases (gid);
CREATE INDEX ix_releases_group_id ON releases (groups_id, passwordstatus);
CREATE INDEX ix_releases_guid ON releases (guid);
CREATE INDEX ix_releases_haspreview_passwordstatus ON releases (haspreview, passwordstatus);
CREATE INDEX ix_releases_imdbid ON releases (imdbid);
CREATE INDEX ix_releases_leftguid ON releases (leftguid, predb_id);
CREATE INDEX ix_releases_musicinfoid ON releases (musicinfo_id, passwordstatus);
CREATE INDEX ix_releases_name ON releases (name);
CREATE INDEX ix_releases_nfostatus ON releases (nfostatus, size);
CREATE INDEX ix_releases_nzb_guid ON releases (nzb_guid);
CREATE INDEX ix_releases_passwordstatus ON releases (passwordstatus);
CREATE INDEX ix_releases_postdate_searchname ON releases (postdate, searchname);
CREATE INDEX ix_releases_preid_searchname ON releases (predb_id, searchname);
CREATE INDEX ix_releases_reqidstatus ON releases (adddate, reqidstatus, isrequestid);
CREATE INDEX ix_releases_tv_episodes_id ON releases (tv_episodes_id);
CREATE INDEX ix_releases_videos_id ON releases (videos_id);
CREATE INDEX ix_releases_xxxinfo_id ON releases (xxxinfo_id);

DROP TABLE IF EXISTS release_search_data;
CREATE TABLE release_search_data
(
    id INT(11) unsigned PRIMARY KEY NOT NULL AUTO_INCREMENT,
    releases_id INT(11) unsigned NOT NULL,
    guid VARCHAR(50) NOT NULL,
    name VARCHAR(255) DEFAULT '' NOT NULL,
    searchname VARCHAR(255) DEFAULT '' NOT NULL,
    fromname VARCHAR(255)
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci
    AUTO_INCREMENT = 1;
CREATE FULLTEXT INDEX ix_releasesearch_fromname_ft ON release_search_data (fromname);
CREATE INDEX ix_releasesearch_guid ON release_search_data (guid);
CREATE FULLTEXT INDEX ix_releasesearch_name_ft ON release_search_data (name);
CREATE INDEX ix_releasesearch_releaseid ON release_search_data (releases_id);
CREATE FULLTEXT INDEX ix_releasesearch_searchname_ft ON release_search_data (searchname);

DROP TABLE IF EXISTS release_subtitles;
CREATE TABLE release_subtitles
(
    id INT(11) unsigned PRIMARY KEY NOT NULL AUTO_INCREMENT,
    releases_id INT(11) unsigned NOT NULL,
    subsid INT(2) unsigned NOT NULL,
    subslanguage VARCHAR(50) NOT NULL
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci
    AUTO_INCREMENT = 1;
CREATE UNIQUE INDEX releaseid ON release_subtitles (releases_id, subsid);

DROP TABLE IF EXISTS roleexcat;
CREATE TABLE roleexcat
(
    id INT(16) unsigned PRIMARY KEY NOT NULL AUTO_INCREMENT,
    role INT(11) NOT NULL,
    categories_id INT(11),
    createddate DATETIME NOT NULL
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci
    AUTO_INCREMENT = 1;
CREATE UNIQUE INDEX ix_roleexcat_rolecat ON roleexcat (role, categories_id);

DROP TABLE IF EXISTS settings;
CREATE TABLE settings
(
    setting VARCHAR(64) NOT NULL,
    value VARCHAR(19000),
    updateddate TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
    section VARCHAR(25) DEFAULT '' NOT NULL,
    subsection VARCHAR(25) DEFAULT '' NOT NULL,
    name VARCHAR(25) DEFAULT '' NOT NULL,
    hint TEXT NOT NULL,
    CONSTRAINT `PRIMARY` PRIMARY KEY (section, subsection, name)
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci;
CREATE UNIQUE INDEX ui_settings_setting ON settings (setting);

DROP TABLE IF EXISTS sharing;
CREATE TABLE sharing
(
    site_guid VARCHAR(40) PRIMARY KEY NOT NULL,
    site_name VARCHAR(255) DEFAULT '' NOT NULL,
    enabled TINYINT(1) DEFAULT '0' NOT NULL,
    posting TINYINT(1) DEFAULT '0' NOT NULL,
    start_position TINYINT(1) DEFAULT '0' NOT NULL,
    fetching TINYINT(1) DEFAULT '1' NOT NULL,
    auto_enable TINYINT(1) DEFAULT '1' NOT NULL,
    hide_users TINYINT(1) DEFAULT '1' NOT NULL,
    last_article BIGINT(20) unsigned DEFAULT '0' NOT NULL,
    max_push MEDIUMINT(8) unsigned DEFAULT '40' NOT NULL,
    max_pull INT(10) unsigned DEFAULT '200' NOT NULL,
    max_download MEDIUMINT(8) unsigned DEFAULT '150' NOT NULL
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci;

DROP TABLE IF EXISTS sharing_sites;
CREATE TABLE sharing_sites
(
    id INT(11) unsigned PRIMARY KEY NOT NULL AUTO_INCREMENT,
    site_name VARCHAR(255) DEFAULT '' NOT NULL,
    site_guid VARCHAR(40) DEFAULT '' NOT NULL,
    last_time DATETIME,
    first_time DATETIME,
    enabled TINYINT(1) DEFAULT '0' NOT NULL,
    comments MEDIUMINT(8) unsigned DEFAULT '0' NOT NULL
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci
    AUTO_INCREMENT = 1;

DROP TABLE IF EXISTS short_groups;
CREATE TABLE short_groups
(
    id INT(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) DEFAULT '' NOT NULL,
    first_record BIGINT(20) unsigned DEFAULT '0' NOT NULL,
    last_record BIGINT(20) unsigned DEFAULT '0' NOT NULL,
    updated DATETIME
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci
    AUTO_INCREMENT = 1;
CREATE INDEX ix_shortgroups_id ON short_groups (id);
CREATE INDEX ix_shortgroups_name ON short_groups (name);

DROP TABLE IF EXISTS sphinx;

DROP TABLE IF EXISTS spotnabsources;
CREATE TABLE spotnabsources
(
    id BIGINT(20) unsigned PRIMARY KEY NOT NULL AUTO_INCREMENT,
    username VARCHAR(64) DEFAULT 'nntp' NOT NULL,
    useremail VARCHAR(128) DEFAULT 'spot@nntp.com' NOT NULL,
    usenetgroup VARCHAR(64) DEFAULT 'alt.binaries.backup' NOT NULL,
    publickey VARCHAR(512) NOT NULL,
    active TINYINT(1) DEFAULT '0' NOT NULL,
    description VARCHAR(255) DEFAULT '',
    lastupdate DATETIME,
    lastbroadcast DATETIME,
    lastarticle BIGINT(20) unsigned DEFAULT '0' NOT NULL,
    dateadded DATETIME
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci
    AUTO_INCREMENT = 1;
CREATE UNIQUE INDEX spotnabsources_ix1 ON spotnabsources (username, useremail, usenetgroup);

DROP TABLE IF EXISTS tmux;
CREATE TABLE tmux
(
    id INT(10) unsigned PRIMARY KEY NOT NULL AUTO_INCREMENT,
    setting VARCHAR(64) NOT NULL,
    value VARCHAR(19000),
    updateddate TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci
    AUTO_INCREMENT = 1;
CREATE UNIQUE INDEX setting ON tmux (setting);

DROP TABLE IF EXISTS tv_episodes;
CREATE TABLE tv_episodes
(
    id INT(11) unsigned PRIMARY KEY NOT NULL AUTO_INCREMENT,
    videos_id MEDIUMINT(11) unsigned NOT NULL COMMENT 'FK to videos.id of the parent series.',
    series SMALLINT(5) unsigned DEFAULT '0' NOT NULL COMMENT 'Number of series/season.',
    episode SMALLINT(5) unsigned DEFAULT '0' NOT NULL COMMENT 'Number of episode within series',
    se_complete VARCHAR(10) NOT NULL COMMENT 'String version of Series/Episode as taken from release subject (i.e. S02E21+22).',
    title VARCHAR(180) NOT NULL COMMENT 'Title of the episode.',
    firstaired DATE,
    summary TEXT NOT NULL COMMENT 'Description/summary of the episode.'
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci
    AUTO_INCREMENT = 1;
CREATE INDEX ux_videoid_series_episode_aired ON tv_episodes (videos_id, series, episode, firstaired);

DROP TABLE IF EXISTS tv_info;
CREATE TABLE tv_info
(
    videos_id MEDIUMINT(11) unsigned PRIMARY KEY NOT NULL COMMENT 'FK to video.id',
    summary TEXT NOT NULL COMMENT 'Description/summary of the show.',
    publisher VARCHAR(50) NOT NULL COMMENT 'The channel/network of production/release (ABC, BBC, Showtime, etc.).',
    localzone VARCHAR(50) DEFAULT '' NOT NULL COMMENT 'The linux tz style identifier',
    image TINYINT(1) unsigned DEFAULT '0' NOT NULL COMMENT 'Does the video have a cover image?'
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci;
CREATE INDEX ix_tv_info_image ON tv_info (image);

DROP TABLE IF EXISTS upcoming_releases;
CREATE TABLE upcoming_releases
(
    id INT(10) PRIMARY KEY NOT NULL AUTO_INCREMENT,
    source VARCHAR(20) NOT NULL,
    typeid INT(10),
    info TEXT,
    updateddate TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci
    AUTO_INCREMENT = 1;
CREATE UNIQUE INDEX source ON upcoming_releases (source, typeid);

DROP TABLE IF EXISTS user_downloads;
CREATE TABLE user_downloads
(
    id INT(10) unsigned PRIMARY KEY NOT NULL AUTO_INCREMENT,
    users_id INT(11) unsigned NOT NULL COMMENT 'FK to users.id',
    hosthash VARCHAR(50),
    TIMESTAMP DATETIME NOT NULL,
    releases_id INT(11) NOT NULL COMMENT 'FK to releases.id'
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci
    AUTO_INCREMENT = 1;
CREATE INDEX hosthash ON user_downloads (hosthash);
CREATE INDEX releaseid ON user_downloads (releases_id);
CREATE INDEX TIMESTAMP ON user_downloads (TIMESTAMP);
CREATE INDEX userid ON user_downloads (users_id);

DROP TABLE IF EXISTS user_excluded_categories;
CREATE TABLE user_excluded_categories
(
    id INT(16) unsigned PRIMARY KEY NOT NULL AUTO_INCREMENT,
    users_id INT(11),
    categories_id INT(11) NOT NULL,
    createddate DATETIME NOT NULL
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci
    AUTO_INCREMENT = 1;
CREATE UNIQUE INDEX ix_userexcat_usercat ON user_excluded_categories (users_id, categories_id);

DROP TABLE IF EXISTS user_movies;
CREATE TABLE user_movies
(
    id INT(16) unsigned PRIMARY KEY NOT NULL AUTO_INCREMENT,
    users_id INT(11) unsigned NOT NULL COMMENT 'FK to users.id',
    imdbid MEDIUMINT(7) unsigned zerofill,
    categories VARCHAR(64) COMMENT 'List of categories for user movies',
    createddate DATETIME NOT NULL
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci
    AUTO_INCREMENT = 1;
CREATE INDEX ix_usermovies_userid ON user_movies (users_id, imdbid);

DROP TABLE IF EXISTS user_requests;
CREATE TABLE user_requests
(
    id INT(10) unsigned PRIMARY KEY NOT NULL AUTO_INCREMENT,
    users_id INT(11) unsigned NOT NULL COMMENT 'FK to users.id',
    request VARCHAR(255) NOT NULL,
    hosthash VARCHAR(50),
    TIMESTAMP DATETIME NOT NULL
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci
    AUTO_INCREMENT = 1;
CREATE INDEX hosthash ON user_requests (hosthash);
CREATE INDEX TIMESTAMP ON user_requests (TIMESTAMP);
CREATE INDEX userid ON user_requests (users_id);

DROP TABLE IF EXISTS user_roles;
CREATE TABLE user_roles
(
    id INT(10) PRIMARY KEY NOT NULL AUTO_INCREMENT,
    name VARCHAR(32) NOT NULL,
    apirequests INT(10) unsigned NOT NULL,
    downloadrequests INT(10) unsigned NOT NULL,
    defaultinvites INT(10) unsigned NOT NULL,
    isdefault TINYINT(1) unsigned DEFAULT '0' NOT NULL,
    canpreview TINYINT(1) unsigned DEFAULT '0' NOT NULL,
    canpre TINYINT(1) unsigned DEFAULT '0' NOT NULL,
    hideads TINYINT(1) unsigned DEFAULT '0' NOT NULL
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci
    AUTO_INCREMENT = 4;

DROP TABLE IF EXISTS user_series;
CREATE TABLE user_series
(
    id INT(16) unsigned PRIMARY KEY NOT NULL AUTO_INCREMENT,
    users_id INT(11) unsigned NOT NULL COMMENT 'FK to users.id',
    videos_id INT(16) NOT NULL COMMENT 'FK to videos.id',
    categories VARCHAR(64) COMMENT 'List of categories for users tv shows',
    createddate DATETIME NOT NULL
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci
    AUTO_INCREMENT = 1;
CREATE INDEX ix_userseries_videos_id ON user_series (users_id, videos_id);

DROP TABLE IF EXISTS usercart;
DROP TABLE IF EXISTS users_releases;
CREATE TABLE users_releases
(
    id INT(16) unsigned PRIMARY KEY NOT NULL AUTO_INCREMENT,
    users_id INT(11),
    releases_id INT(11),
    createddate DATETIME NOT NULL
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci
    AUTO_INCREMENT = 1;
CREATE UNIQUE INDEX ix_usercart_userrelease ON users_releases (users_id, releases_id);

DROP TABLE IF EXISTS users;
CREATE TABLE users
(
    id INT(16) unsigned PRIMARY KEY NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role INT(11) DEFAULT '1' NOT NULL,
    host VARCHAR(40),
    grabs INT(11) DEFAULT '0' NOT NULL,
    rsstoken VARCHAR(32) NOT NULL,
    createddate DATETIME NOT NULL,
    resetguid VARCHAR(50),
    lastlogin DATETIME,
    apiaccess DATETIME,
    invites INT(11) DEFAULT '0' NOT NULL,
    invitedby INT(11),
    movieview INT(11) DEFAULT '1' NOT NULL,
    musicview INT(11) DEFAULT '0' NOT NULL,
    consoleview INT(11) DEFAULT '0' NOT NULL,
    xxxview INT(11),
    gameview INT(11),
    bookview INT(11) DEFAULT '0' NOT NULL,
    saburl VARCHAR(255),
    sabapikey VARCHAR(255),
    sabapikeytype TINYINT(1),
    sabpriority TINYINT(1),
    nzbgeturl VARCHAR(255),
    nzbgetusername VARCHAR(255),
    nzbgetpassword VARCHAR(255),
    userseed VARCHAR(50) NOT NULL,
    notes VARCHAR(255),
    rolechangedate DATETIME,
    nzbvortex_api_key VARCHAR(10),
    nzbvortex_server_url VARCHAR(255),
    cp_api VARCHAR(255),
    cp_url VARCHAR(255),
    queuetype TINYINT(1) DEFAULT '1' NOT NULL,
    style VARCHAR(255)
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci
    AUTO_INCREMENT = 1;

DROP TABLE IF EXISTS video_data;
CREATE TABLE video_data
(
    releases_id INT(11) PRIMARY KEY NOT NULL COMMENT 'FK to releases.id',
    containerformat VARCHAR(255),
    overallbitrate VARCHAR(255),
    videoduration VARCHAR(255),
    videoformat VARCHAR(255),
    videocodec VARCHAR(255),
    videowidth INT(10),
    videoheight INT(10),
    videoaspect VARCHAR(255),
    videoframerate FLOAT(7,4),
    videolibrary VARCHAR(255),
    definition INT(10)
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci;

DROP TABLE IF EXISTS videos;
CREATE TABLE videos
(
    id MEDIUMINT(11) unsigned PRIMARY KEY NOT NULL COMMENT 'Show ID to be used in other tables as reference.' AUTO_INCREMENT,
    type TINYINT(1) unsigned DEFAULT '0' NOT NULL COMMENT '0  =  TV, 1  =  Film, 2  =  Anime',
    title VARCHAR(180) NOT NULL COMMENT 'Name of the video.',
    countries_id CHAR(2) DEFAULT '' NOT NULL COMMENT 'Two character country code (FK to countries table).',
    started DATETIME NOT NULL COMMENT 'Date (UTC) of productions first airing.',
    anidb MEDIUMINT(11) unsigned DEFAULT '0' NOT NULL COMMENT 'ID number for anidb site',
    imdb MEDIUMINT(11) unsigned DEFAULT '0' NOT NULL COMMENT 'ID number for IMDB site (without the 'tt' prefix).',
    tmdb MEDIUMINT(11) unsigned DEFAULT '0' NOT NULL COMMENT 'ID number for TMDB site.',
    trakt MEDIUMINT(11) unsigned DEFAULT '0' NOT NULL COMMENT 'ID number for TraktTV site.',
    tvdb MEDIUMINT(11) unsigned DEFAULT '0' NOT NULL COMMENT 'ID number for TVDB site',
    tvmaze MEDIUMINT(11) unsigned DEFAULT '0' NOT NULL COMMENT 'ID number for TVMaze site.',
    tvrage MEDIUMINT(11) unsigned DEFAULT '0' NOT NULL COMMENT 'ID number for TVRage site.',
    source TINYINT(1) unsigned DEFAULT '0' NOT NULL COMMENT 'Which site did we use for info?'
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci
    AUTO_INCREMENT = 1;
CREATE INDEX ix_videos_imdb ON videos (imdb);
CREATE UNIQUE INDEX ix_videos_title ON videos (title, type, started, countries_id);
CREATE INDEX ix_videos_tmdb ON videos (tmdb);
CREATE INDEX ix_videos_trakt ON videos (trakt);
CREATE INDEX ix_videos_tvdb ON videos (tvdb);
CREATE INDEX ix_videos_tvmaze ON videos (tvmaze);
CREATE INDEX ix_videos_tvrage ON videos (tvrage);
CREATE INDEX ix_videos_type_source ON videos (type, source);

DROP TABLE IF EXISTS videos_aliases;
CREATE TABLE videos_aliases
(
    videos_id MEDIUMINT(11) unsigned NOT NULL COMMENT 'FK to videos.id of the parent title.',
    title VARCHAR(180) NOT NULL COMMENT 'AKA of the video.',
    CONSTRAINT `PRIMARY` PRIMARY KEY (videos_id, title)
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci;

DROP TABLE IF EXISTS xxxinfo;
CREATE TABLE xxxinfo
(
    id INT(10) unsigned PRIMARY KEY NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    tagline VARCHAR(1024) NOT NULL,
    plot BLOB,
    genre VARCHAR(64) NOT NULL,
    director VARCHAR(64),
    actors VARCHAR(2000) NOT NULL,
    extras TEXT,
    productinfo TEXT,
    trailers TEXT,
    directurl VARCHAR(2000) NOT NULL,
    classused VARCHAR(4) DEFAULT 'ade',
    cover TINYINT(1) unsigned DEFAULT '0' NOT NULL,
    backdrop TINYINT(1) unsigned DEFAULT '0' NOT NULL,
    createddate DATETIME NOT NULL,
    updateddate DATETIME NOT NULL
)
    ENGINE = MyISAM
    DEFAULT CHARSET = utf8
    COLLATE = utf8_unicode_ci
    AUTO_INCREMENT = 1;
CREATE INDEX ix_xxxinfo_title ON xxxinfo (title);

DELIMITER $$
CREATE TRIGGER check_insert BEFORE INSERT ON releases FOR EACH ROW
    BEGIN
        IF NEW.searchname REGEXP '[a-fA-F0-9]{32}' OR NEW.name REGEXP '[a-fA-F0-9]{32}'
        THEN SET NEW.ishashed = 1;
        ELSEIF NEW.name REGEXP '^\\[ ?([[:digit:]]{4,6}) ?\\]|^REQ\\s*([[:digit:]]{4,6})|^([[:digit:]]{4,6})-[[:digit:]]{1}\\s?\\['
            THEN SET NEW.isrequestid = 1;
        END IF;
    END; $$

CREATE TRIGGER check_update BEFORE UPDATE ON releases FOR EACH ROW
    BEGIN
        IF NEW.searchname REGEXP '[a-fA-F0-9]{32}' OR NEW.name REGEXP '[a-fA-F0-9]{32}'
        THEN SET NEW.ishashed = 1;
        ELSEIF NEW.name REGEXP '^\\[ ?([[:digit:]]{4,6}) ?\\]|^REQ\\s*([[:digit:]]{4,6})|^([[:digit:]]{4,6})-[[:digit:]]{1}\\s?\\['
            THEN SET NEW.isrequestid = 1;
        END IF;
    END; $$

CREATE TRIGGER check_rfinsert BEFORE INSERT ON release_files FOR EACH ROW
    BEGIN
        IF NEW.name REGEXP '[a-fA-F0-9]{32}'
        THEN SET NEW.ishashed = 1;
        END IF;
    END; $$

CREATE TRIGGER check_rfupdate BEFORE UPDATE ON release_files FOR EACH ROW
    BEGIN
        IF NEW.name REGEXP '[a-fA-F0-9]{32}'
        THEN SET NEW.ishashed = 1;
        END IF;
    END; $$

CREATE TRIGGER insert_search AFTER INSERT ON releases FOR EACH ROW
    BEGIN
        INSERT INTO release_search_data (releases_id, guid, name, searchname, fromname) VALUES (NEW.id, NEW.guid, NEW.name, NEW.searchname, NEW.fromname);
    END; $$

CREATE TRIGGER update_search AFTER UPDATE ON releases FOR EACH ROW
    BEGIN
        IF NEW.guid != OLD.guid
        THEN UPDATE release_search_data SET guid = NEW.guid WHERE releases_id = OLD.id;
        END IF;
        IF NEW.name != OLD.name
        THEN UPDATE release_search_data SET name = NEW.name WHERE releases_id = OLD.id;
        END IF;
        IF NEW.searchname != OLD.searchname
        THEN UPDATE release_search_data SET searchname = NEW.searchname WHERE releases_id = OLD.id;
        END IF;
        IF NEW.fromname != OLD.fromname
        THEN UPDATE release_search_data SET fromname = NEW.fromname WHERE releases_id = OLD.id;
        END IF;
    END; $$

CREATE TRIGGER delete_search AFTER DELETE ON releases FOR EACH ROW
    BEGIN
        DELETE FROM release_search_data WHERE releases_id = OLD.id;
    END; $$

CREATE TRIGGER insert_hashes AFTER INSERT ON predb FOR EACH ROW
    BEGIN
        INSERT INTO predb_hashes (hash, predb_id) VALUES (UNHEX(MD5(NEW.title)), NEW.id), (UNHEX(MD5
                                                                                                 (MD5(NEW.title))), NEW.id), ( UNHEX(SHA1(NEW.title)), NEW.id);
    END; $$

CREATE TRIGGER update_hashes AFTER UPDATE ON predb FOR EACH ROW
    BEGIN
        IF NEW.title != OLD.title
        THEN
            DELETE FROM predb_hashes WHERE hash IN ( UNHEX(md5(OLD.title)), UNHEX(md5(md5(OLD.title))), UNHEX(sha1(OLD.title)) ) AND predb_id = OLD.id;
            INSERT INTO predb_hashes (hash, predb_id) VALUES ( UNHEX(MD5(NEW.title)), NEW.id ), ( UNHEX(MD5(MD5(NEW.title))), NEW.id ), ( UNHEX(SHA1(NEW.title)), NEW.id );
        END IF;
    END; $$

CREATE TRIGGER delete_hashes AFTER DELETE ON predb FOR EACH ROW
    BEGIN
        DELETE FROM predb_hashes WHERE hash IN ( UNHEX(md5(OLD.title)), UNHEX(md5(md5(OLD.title))), UNHEX(sha1(OLD.title)) ) AND predb_id = OLD.id;
    END; $$

CREATE TRIGGER insert_MD5 BEFORE INSERT ON release_comments FOR EACH ROW
    SET
    NEW.text_hash = MD5(NEW.text);
$$

DELIMITER ;
