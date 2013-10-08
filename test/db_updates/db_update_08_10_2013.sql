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
