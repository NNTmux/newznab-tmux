ALTER TABLE  `predb` ADD  `hash` VARCHAR( 32 ) NULL;
ALTER TABLE  `predb` ADD INDEX (  `hash` ( 32 ) );
ALTER TABLE  `releases` ADD  `dehashstatus` TINYINT( 1 ) NOT NULL DEFAULT  '0' AFTER  `haspreview`;
ALTER TABLE  `releases` ADD  `nfostatus` TINYINT NOT NULL DEFAULT 0 after `dehashstatus`;
ALTER TABLE  `releases` ADD  `relnamestatus` TINYINT NOT NULL DEFAULT 1 after `nfostatus`;
ALTER TABLE  `releases` ADD  `relstatus` TINYINT(4) NOT NULL DEFAULT 0 after `relnamestatus`;
ALTER TABLE	 `releases` ADD  `hashed` BOOL DEFAULT FALSE after `relstatus`; 
CREATE INDEX ix_releases_hashed on releases(hashed);
CREATE INDEX ix_releases_mergedreleases on releases(dehashstatus, relnamestatus, passwordstatus);
UPDATE releases SET hashed = true WHERE searchname REGEXP '[a-fA-F0-9]{32}' OR name REGEXP '[a-fA-F0-9]{32}'; 
delimiter //
CREATE TRIGGER check_insert BEFORE INSERT ON releases FOR EACH ROW BEGIN IF NEW.searchname REGEXP '[a-fA-F0-9]{32}' OR NEW.name REGEXP '[a-fA-F0-9]{32}' THEN SET NEW.hashed = true; END IF; END;//
delimiter ;
delimiter //
CREATE TRIGGER check_update BEFORE UPDATE ON releases FOR EACH ROW BEGIN IF NEW.searchname REGEXP '[a-fA-F0-9]{32}' OR NEW.name REGEXP '[a-fA-F0-9]{32}' THEN SET NEW.hashed = true; END IF; END;//
delimiter;
		




