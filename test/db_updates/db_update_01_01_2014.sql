DROP INDEX ix_releases_mergedreleases ON releases;
DROP INDEX ix_releases_nzbstatus ON releases;
DROP TRIGGER IF EXISTS check_insert;
DROP TRIGGER IF EXISTS check_update;

ALTER TABLE `releases` ADD `request` BOOL DEFAULT false;
ALTER TABLE `releases` ADD `bitwise` SMALLINT UNSIGNED NOT NULL DEFAULT 0;
CREATE INDEX ix_releases_status ON releases (nzbstatus, ID, nfostatus, relnamestatus, passwordstatus, dehashstatus, reqidstatus, musicinfoID, consoleinfoID, bookinfoID, haspreview, hashed, request, categoryID);

DELIMITER $$
CREATE TRIGGER check_insert BEFORE INSERT ON releases FOR EACH ROW BEGIN IF NEW.searchname REGEXP '[a-fA-F0-9]{32}' OR NEW.name REGEXP '[a-fA-F0-9]{32}' THEN SET NEW.hashed = true;ELSEIF NEW.name REGEXP '^\\[[[:digit:]]+\\]' THEN SET NEW.request = true; END IF; END; $$
CREATE TRIGGER check_update BEFORE UPDATE ON releases FOR EACH ROW BEGIN IF NEW.searchname REGEXP '[a-fA-F0-9]{32}' OR NEW.name REGEXP '[a-fA-F0-9]{32}' THEN SET NEW.hashed = true;ELSEIF NEW.name REGEXP '^\\[[[:digit:]]+\\]' THEN SET NEW.request = true; END IF; END; $$
DELIMITER ;
UPDATE releases set bitwise = 0;
UPDATE releases SET request = true WHERE name REGEXP '^\\[[[:digit:]]+\\]';
UPDATE releases SET bitwise = ((bitwise & ~256)|256) WHERE nzbstatus = 1;
UPDATE releases SET bitwise = ((bitwise & ~256)|0) WHERE nzbstatus != 1;
UPDATE releases SET bitwise = ((bitwise & ~512)|512) WHERE searchname REGEXP '[a-fA-F0-9]{32}' OR name REGEXP '[a-fA-F0-9]{32}';
UPDATE releases SET bitwise = ((bitwise & ~1024)|1024) WHERE name REGEXP '^\\[[[:digit:]]+\\]';