# Create par_hashes table

DROP TABLE IF EXISTS par_hashes;
CREATE TABLE par_hashes (
  releases_id INT(11) NOT NULL COMMENT 'FK to releases.id',
  hash VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'hash of first 16k of rar files in par2 file',
  PRIMARY KEY (releases_id, hash)
  )
  ENGINE = MYISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci;
