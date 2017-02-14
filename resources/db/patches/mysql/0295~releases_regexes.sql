# Create the new release_regexes table

DROP TABLE IF EXISTS release_regexes;
CREATE TABLE release_regexes (
  releases_id           INT(11) UNSIGNED    NOT NULL DEFAULT '0',
  collection_regex_id   INT(11) UNSIGNED    NOT NULL DEFAULT '0',
  naming_regex_id       INT(11) UNSIGNED    NOT NULL DEFAULT '0',
  PRIMARY KEY (releases_id, collection_regex_id, naming_regex_id)
)
  ENGINE          = MYISAM
  DEFAULT CHARSET = utf8
  COLLATE         = utf8_unicode_ci;
