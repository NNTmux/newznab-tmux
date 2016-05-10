ALTER TABLE releases
  CHANGE COLUMN preid predb_id INT(11) UNSIGNED NOT NULL COMMENT 'id, of the predb entry, this hash belongs to',
  CHANGE COLUMN musicinfoid musicinfo_id INT(11) UNSIGNED NULL COMMENT 'FK to musicinfo.id',
  CHANGE COLUMN consoleinfoid consoleinfo_id INT(11) UNSIGNED NULL COMMENT 'FK to consoleinfo.id',
  CHANGE COLUMN bookinfoid bookinfo_id INT(11) UNSIGNED NULL COMMENT 'FK to bookinfo.id'
PARTITION BY RANGE (categories_id) (
PARTITION misc VALUES LESS THAN (1000),
PARTITION console VALUES LESS THAN (2000),
PARTITION movies VALUES LESS THAN (3000),
PARTITION audio VALUES LESS THAN (4000),
PARTITION pc VALUES LESS THAN (5000),
PARTITION tv VALUES LESS THAN (6000),
PARTITION xxx VALUES LESS THAN (7000),
PARTITION books VALUES LESS THAN (8000)
);

DROP TRIGGER IF EXISTS delete_search;
DROP TRIGGER IF EXISTS insert_search;
DROP TRIGGER IF EXISTS update_search;

DELIMITER $$

CREATE TRIGGER insert_search AFTER INSERT ON releases FOR EACH ROW BEGIN INSERT INTO release_search_data (releases_id, guid, name, searchname, fromname) VALUES (NEW.id, NEW.guid, NEW.name, NEW.searchname, NEW.fromname); END; $$
CREATE TRIGGER update_search AFTER UPDATE ON releases FOR EACH ROW BEGIN IF NEW.guid != OLD.guid THEN UPDATE release_search_data SET guid = NEW.guid WHERE releases_id = OLD.id; END IF; IF NEW.name != OLD.name THEN UPDATE release_search_data SET name = NEW.name WHERE releases_id = OLD.id; END IF; IF NEW.searchname != OLD.searchname THEN UPDATE release_search_data SET searchname = NEW.searchname WHERE releases_id = OLD.id; END IF; IF NEW.fromname != OLD.fromname THEN UPDATE release_search_data SET fromname = NEW.fromname WHERE releases_id = OLD.id; END IF; END; $$
CREATE TRIGGER delete_search AFTER DELETE ON releases FOR EACH ROW BEGIN DELETE FROM release_search_data WHERE releases_id = OLD.id; END; $$

DELIMITER ;
