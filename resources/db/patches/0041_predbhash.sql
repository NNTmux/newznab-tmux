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

INSERT IGNORE INTO predbhash (pre_id, hashes) (SELECT
                                          id,
                                          CONCAT_WS(',', MD5(title), MD5(MD5(title)), SHA1(title))
                                        FROM prehash);

CREATE FULLTEXT INDEX ix_predbhash_hashes_ft ON predbhash (hashes);
ALTER IGNORE TABLE predbhash ADD UNIQUE INDEX ix_predbhash_hashes (hashes(32));

DROP TRIGGER IF EXISTS insert_hashes;

CREATE TRIGGER insert_hashes AFTER INSERT ON prehash FOR EACH ROW BEGIN INSERT IGNORE INTO predbhash (pre_id, hashes) VALUES (NEW.id, CONCAT_WS(',', MD5(NEW.title), MD5(MD5(NEW.title)), SHA1(NEW.title)));END;

DROP TRIGGER IF EXISTS update_hashes;
CREATE TRIGGER update_hashes AFTER UPDATE ON prehash FOR EACH ROW BEGIN IF NEW.title != OLD.title THEN UPDATE predbhash SET hashes = CONCAT_WS(',', MD5(NEW.title), MD5(MD5(NEW.title)), SHA1(NEW.title)); END IF;END;

DROP TRIGGER IF EXISTS delete_hashes;
CREATE TRIGGER delete_hashes AFTER DELETE ON prehash FOR EACH ROW BEGIN DELETE FROM predbhash WHERE pre_id = OLD.id;END;
