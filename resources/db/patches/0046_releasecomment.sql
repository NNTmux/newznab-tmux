ALTER TABLE releasecomment ADD COLUMN text_hash VARCHAR(32) NOT NULL DEFAULT '';
DROP TRIGGER IF EXISTS insert_MD5;
CREATE TRIGGER insert_MD5 BEFORE INSERT ON releasecomment FOR EACH ROW SET NEW.text_hash = MD5(NEW.text);
UPDATE releasecomment
SET text_hash = MD5(text);
ALTER IGNORE TABLE releasecomment ADD UNIQUE INDEX ix_releasecomment_hash_releaseID (text_hash, releaseid);