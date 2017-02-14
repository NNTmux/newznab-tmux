# Create the new releases_regexes table

DROP TABLE IF EXISTS releases_regexes;
CREATE TABLE releases_regexes (
  releases_id           INT(11) UNSIGNED    NOT NULL AUTO_INCREMENT,
  regex_id              INT(11) UNSIGNED        NOT NULL DEFAULT '0',
  PRIMARY KEY (releases_id, regex_id)
)
  ENGINE          = MYISAM
  DEFAULT CHARSET = utf8
  COLLATE         = utf8_unicode_ci
  AUTO_INCREMENT = 1;
