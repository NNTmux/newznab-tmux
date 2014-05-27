DROP TRIGGER IF EXISTS update_hashes;
CREATE TRIGGER update_hashes AFTER UPDATE ON predhash FOR EACH ROW BEGIN IF NEW.title != OLD.title
THEN UPDATE predbhash
SET hashes = CONCAT_WS(',', MD5(NEW.title), MD5(MD5(NEW.title)), SHA1(NEW.title))
WHERE pre_id = OLD.ID; END IF;
END;
UPDATE `tmux`
SET value = '42'
WHERE `setting` = 'sqlpatch';