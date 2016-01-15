#rename the old PreDB table to predb_old
RENAME TABLE predb TO predb_old;

#rename the existing prehash table to predb
RENAME TABLE prehash TO predb;

#Redo the triggers after the table name change
DROP TRIGGER IF EXISTS delete_hashes;
DROP TRIGGER IF EXISTS insert_hashes;
DROP TRIGGER IF EXISTS update_hashes;

DELIMITER $$

CREATE TRIGGER delete_hashes AFTER DELETE ON predb FOR EACH ROW BEGIN DELETE FROM predbhash WHERE hash IN ( UNHEX(md5(OLD.title)), UNHEX(md5(md5(OLD.title))), UNHEX(sha1(OLD.title)) ) AND pre_id = OLD.id; END $$

CREATE TRIGGER insert_hashes AFTER INSERT ON predb FOR EACH ROW BEGIN INSERT INTO predbhash (hash, pre_id) VALUES (UNHEX(MD5(NEW.title)), NEW.id), (UNHEX(MD5(MD5(NEW.title))), NEW.id), ( UNHEX(SHA1(NEW.title)), NEW.id); END $$

CREATE TRIGGER update_hashes AFTER UPDATE ON predb FOR EACH ROW BEGIN IF NEW.title != OLD.title THEN DELETE FROM predbhash WHERE hash IN ( UNHEX(md5(OLD.title)), UNHEX(md5(md5(OLD.title))), UNHEX(sha1(OLD.title)) ) AND pre_id = OLD.id; INSERT INTO predbhash (hash, pre_id) VALUES ( UNHEX(MD5(NEW.title)), NEW.id ), ( UNHEX(MD5(MD5(NEW.title))), NEW.id ), ( UNHEX(SHA1(NEW.title)), NEW.id ); END IF; END $$

DELIMITER ;
