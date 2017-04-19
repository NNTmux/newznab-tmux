# Create par_hashes table

DROP TABLE IF EXISTS par_hashes;
CREATE TABLE par_hashes (
  releases_id INT(11) NOT NULL COMMENT 'FK to releases.id',
  hash VARCHAR(32) NOT NULL COMMENT 'hash_16k block of par2',
  PRIMARY KEY (releases_id, hash)
  )
  ENGINE = MYISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci;
