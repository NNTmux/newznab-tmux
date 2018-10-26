DROP TABLE IF EXISTS anidb_episodes;
CREATE TABLE anidb_episodes (
  anidbid       INT(10) UNSIGNED        NOT NULL
  COMMENT 'id of title from AniDB',
  episodeid     INT(10) UNSIGNED        NOT NULL DEFAULT '0'
  COMMENT 'anidb id for this episode',
  episode_no    SMALLINT(5) UNSIGNED    NOT NULL
  COMMENT 'Numeric version of episode (leave 0 for combined episodes).',
  episode_title VARCHAR(255)
                COLLATE utf8_unicode_ci NOT NULL
  COMMENT 'Title of the episode (en, x-jat)',
  airdate       DATE                    NOT NULL,
  PRIMARY KEY (anidbid, episodeid)
)
  ENGINE = InnoDB
  DEFAULT CHARSET =utf8
  COLLATE =utf8_unicode_ci;


DROP TABLE IF EXISTS anidb_info;
CREATE TABLE anidb_info (
  anidbid     INT(10) UNSIGNED NOT NULL
  COMMENT 'id of title from AniDB',
  type        VARCHAR(32)
              COLLATE utf8_unicode_ci DEFAULT NULL,
  startdate   DATE                    DEFAULT NULL,
  enddate     DATE                    DEFAULT NULL,
  updated     TIMESTAMP               DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  related     VARCHAR(1024)
              COLLATE utf8_unicode_ci DEFAULT NULL,
  similar     VARCHAR(1024)
              COLLATE utf8_unicode_ci DEFAULT NULL,
  creators    VARCHAR(1024)
              COLLATE utf8_unicode_ci DEFAULT NULL,
  description TEXT
              COLLATE utf8_unicode_ci DEFAULT NULL,
  rating      VARCHAR(5)
              COLLATE utf8_unicode_ci DEFAULT NULL,
  picture     VARCHAR(255)
              COLLATE utf8_unicode_ci DEFAULT NULL,
  categories  VARCHAR(1024)
              COLLATE utf8_unicode_ci DEFAULT NULL,
  characters  VARCHAR(1024)
              COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (anidbid),
  KEY ix_anidb_info_datetime (startdate, enddate, updated)
)
  ENGINE = InnoDB
  DEFAULT CHARSET =utf8
  COLLATE =utf8_unicode_ci;


DROP TABLE IF EXISTS anidb_titles;
CREATE TABLE anidb_titles (
  anidbid INT(10) UNSIGNED        NOT NULL
  COMMENT 'id of title from AniDB',
  type    VARCHAR(25)
          COLLATE utf8_unicode_ci NOT NULL
  COMMENT 'type of title.',
  lang    VARCHAR(25)
          COLLATE utf8_unicode_ci NOT NULL,
  title   VARCHAR(255)
          COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (anidbid, type, lang, title)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci;