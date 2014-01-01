ALTER TABLE  `predb` ADD  `hash` VARCHAR( 32 ) NULL;
ALTER TABLE  `predb` ADD INDEX (  `hash` ( 32 ) );
ALTER TABLE  `releases` ADD  `dehashstatus` TINYINT( 1 ) NOT NULL DEFAULT  '0' AFTER  `haspreview`;
ALTER TABLE  `releases` ADD  `nfostatus` TINYINT NOT NULL DEFAULT 0 after `dehashstatus`;
ALTER TABLE  `releases` ADD  `relnamestatus` TINYINT NOT NULL DEFAULT 1 after `nfostatus`;
ALTER TABLE	 `releases` ADD  `hashed` BOOL DEFAULT FALSE after `relnamestatus`;
ALTER TABLE	 `releases` ADD  `nzbstatus` TINYINT NOT NULL DEFAULT 0 after `hashed`;
ALTER TABLE  `releases` ADD  `reqidstatus` TINYINT(1) NOT NULL DEFAULT '0' AFTER `relnamestatus`;
ALTER TABLE  `releases` ADD  `request` BOOL DEFAULT false AFTER `reqidstatus`;
ALTER TABLE `releases` ADD `bitwise` SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER `request`;

CREATE INDEX ix_releases_hashed on releases(hashed);
CREATE INDEX ix_releases_nfostatus ON releases (nfostatus ASC) USING HASH;
CREATE INDEX ix_releases_reqidstatus ON releases(reqidstatus ASC) USING HASH;
CREATE INDEX ix_releases_status ON releases (nzbstatus, ID, nfostatus, relnamestatus, passwordstatus, dehashstatus, reqidstatus, musicinfoID, consoleinfoID, bookinfoID, haspreview, hashed, request, categoryID);
UPDATE releases SET hashed = true WHERE searchname REGEXP '[a-fA-F0-9]{32}' OR name REGEXP '[a-fA-F0-9]{32}'; 
UPDATE releases SET nzbstatus = 1;
UPDATE releases SET reqidstatus = -1 WHERE reqidstatus = 0 AND nzbstatus = 1 AND relnamestatus IN (0, 1) AND name REGEXP '^\\[[[:digit:]]+\\]' = 0;
DELIMITER $$
CREATE TRIGGER check_insert BEFORE INSERT ON releases FOR EACH ROW BEGIN IF NEW.searchname REGEXP '[a-fA-F0-9]{32}' OR NEW.name REGEXP '[a-fA-F0-9]{32}' THEN SET NEW.hashed = true;ELSEIF NEW.name REGEXP '^\\[[[:digit:]]+\\]' THEN SET NEW.request = true; END IF; END; $$
CREATE TRIGGER check_update BEFORE UPDATE ON releases FOR EACH ROW BEGIN IF NEW.searchname REGEXP '[a-fA-F0-9]{32}' OR NEW.name REGEXP '[a-fA-F0-9]{32}' THEN SET NEW.hashed = true;ELSEIF NEW.name REGEXP '^\\[[[:digit:]]+\\]' THEN SET NEW.request = true; END IF; END; $$
DELIMITER ;

UPDATE releases SET request = true WHERE name REGEXP '^\\[[[:digit:]]+\\]';
UPDATE releases set bitwise = 0;
UPDATE releases SET bitwise = ((bitwise & ~256)|256) WHERE nzbstatus = 1;
UPDATE releases SET bitwise = ((bitwise & ~256)|0) WHERE nzbstatus != 1;
UPDATE releases SET bitwise = ((bitwise & ~512)|512) WHERE searchname REGEXP '[a-fA-F0-9]{32}' OR name REGEXP '[a-fA-F0-9]{32}';
UPDATE releases SET bitwise = ((bitwise & ~1024)|1024) WHERE name REGEXP '^\\[[[:digit:]]+\\]';
		




