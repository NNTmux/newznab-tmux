DROP TABLE IF EXISTS tvrage_titles;
CREATE TABLE tvrage_titles (
  id           INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  rageid       INT              NOT NULL,
  tvdbid       INT              NOT NULL DEFAULT '0',
  releasetitle VARCHAR(255)     NOT NULL DEFAULT '',
  description  VARCHAR(10000)   NULL,
  genre        VARCHAR(64)      NULL DEFAULT NULL,
  country      VARCHAR(2)       NULL DEFAULT NULL,
  imgdata      MEDIUMBLOB       NULL,
  hascover     TINYINT(1)       NOT NULL DEFAULT 0 COMMENT 'Does series have cover art?',
  createddate  DATETIME         DEFAULT NULL,
  prevdate     DATETIME         NULL,
  previnfo     VARCHAR(255)     NULL,
  nextdate     DATETIME         NULL,
  nextinfo     VARCHAR(255)     NULL,
  PRIMARY KEY                                (id),
  INDEX        ix_tvrage_rageid              (rageid),
  INDEX        ix_tvrage_releasetitle        (releasetitle),
  UNIQUE INDEX ux_tvrage_rageid_releasetitle (rageid, releasetitle)
)
  ENGINE          = MYISAM
  DEFAULT CHARSET = utf8
  COLLATE         = utf8_unicode_ci
  AUTO_INCREMENT  = 1000001;


DROP TABLE IF EXISTS tvrage_episodes;
CREATE TABLE tvrage_episodes (
  id        INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  rageid    INT(11) UNSIGNED NOT NULL,
  showtitle VARCHAR(255) DEFAULT NULL,
  airdate   DATETIME         NOT NULL,
  link      VARCHAR(255) DEFAULT NULL,
  fullep    VARCHAR(20)      NOT NULL,
  eptitle   VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE INDEX ix_tvrageepisodes_rageid (rageid, fullep)
)
  ENGINE = MYISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT = 1000001;