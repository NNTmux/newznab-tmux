DROP TABLE IF EXISTS collections;
CREATE TABLE         collections (
  id             INT(11) UNSIGNED    NOT NULL AUTO_INCREMENT,
  subject        VARCHAR(255)        NOT NULL DEFAULT '',
  fromname       VARCHAR(255)        NOT NULL DEFAULT '',
  date           DATETIME            DEFAULT NULL,
  xref           VARCHAR(255)        NOT NULL DEFAULT '',
  totalfiles     INT(11) UNSIGNED    NOT NULL DEFAULT '0',
  group_id       INT(11) UNSIGNED    NOT NULL DEFAULT '0',
  collectionhash VARCHAR(255)        NOT NULL DEFAULT '0',
  dateadded      DATETIME            DEFAULT NULL,
  filecheck      TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
  filesize       BIGINT UNSIGNED     NOT NULL DEFAULT '0',
  releaseid      INT                 NULL,
  PRIMARY KEY                               (id),
  INDEX        fromname                     (fromname),
  INDEX        date                         (date),
  INDEX        group_id                     (group_id),
  INDEX        ix_collection_filecheck      (filecheck),
  INDEX        ix_collection_dateadded      (dateadded),
  INDEX        ix_collection_releaseid      (releaseid),
  UNIQUE INDEX ix_collection_collectionhash (collectionhash)
)
  ENGINE          = MYISAM
  DEFAULT CHARSET = utf8
  COLLATE         = utf8_unicode_ci
  AUTO_INCREMENT  = 1;

DROP TABLE IF EXISTS binaries;
CREATE TABLE binaries (
  id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  name          VARCHAR(1000)       NOT NULL DEFAULT '',
  collection_id INT(11) UNSIGNED    NOT NULL DEFAULT 0,
  filenumber    INT UNSIGNED        NOT NULL DEFAULT '0',
  totalparts    INT(11) UNSIGNED    NOT NULL DEFAULT 0,
  currentparts  INT UNSIGNED        NOT NULL DEFAULT 0,
  binaryhash    VARCHAR(255)        NOT NULL DEFAULT '0',
  partcheck     BIT                 NOT NULL DEFAULT 0,
  partsize      BIGINT UNSIGNED     NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE INDEX ix_binary_binaryhash (binaryhash),
  INDEX ix_binary_partcheck  (partcheck),
  INDEX ix_binary_collection (collection_id)
)
  ENGINE = MYISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT = 1;

DROP TABLE IF EXISTS parts;
CREATE TABLE parts (
  id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  binaryid      BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
  collection_id INT(11) UNSIGNED    NOT NULL DEFAULT '0',
  messageid     VARCHAR(255)        NOT NULL DEFAULT '',
  number        BIGINT UNSIGNED     NOT NULL DEFAULT '0',
  partnumber    INT UNSIGNED        NOT NULL DEFAULT '0',
  size          BIGINT UNSIGNED     NOT NULL DEFAULT '0',
  PRIMARY KEY (id),
  KEY binaryid               (binaryid),
  KEY ix_parts_collection_id (collection_id)
)
  ENGINE = MYISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  AUTO_INCREMENT = 1;

DROP TABLE IF EXISTS partrepair;
CREATE TABLE partrepair (
  id       INT(16) UNSIGNED NOT NULL AUTO_INCREMENT,
  numberid BIGINT UNSIGNED  NOT NULL,
  group_id INT(11) UNSIGNED NOT NULL DEFAULT '0'
  COMMENT 'FK to groups',
  attempts TINYINT(1)       NOT NULL DEFAULT '0',
  PRIMARY KEY (id),
  INDEX ix_partrepair_attempts                  (attempts),
  INDEX ix_partrepair_groupid_attempts          (group_id, attempts),
  INDEX ix_partrepair_numberid_groupid_attempts (numberid, group_id, attempts),
  UNIQUE INDEX ix_partrepair_numberid_groupid          (numberid, group_id)
)
  ENGINE = MYISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci;

CREATE TRIGGER delete_collections BEFORE DELETE ON collections FOR EACH ROW BEGIN DELETE FROM binaries WHERE collection_id = OLD.id;DELETE FROM parts WHERE collection_id = OLD.id;END;