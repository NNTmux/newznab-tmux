ALTER TABLE  `predb`
  ADD `md5` VARCHAR( 32 ) NULL,
  ADD INDEX (`md5`);
ALTER TABLE  `releases`
  ADD `dehashstatus` TINYINT( 1 ) NOT NULL DEFAULT  '0' AFTER  `haspreview`,
  ADD `nfostatus` TINYINT NOT NULL DEFAULT 0 after `dehashstatus`,
  ADD `reqidstatus` TINYINT(1) NOT NULL DEFAULT '0' AFTER `nfostatus`,
  ADD COLUMN `nzbstatus` BIT NOT NULL DEFAULT 0,
  ADD COLUMN `iscategorized` BIT NOT NULL DEFAULT 0,
  ADD COLUMN `isrenamed` BIT NOT NULL DEFAULT 0,
  ADD COLUMN `ishashed` BIT NOT NULL DEFAULT 0,
  ADD COLUMN `isrequestid` BIT NOT NULL DEFAULT 0,
  ADD COLUMN `proc_par2` BIT NOT NULL DEFAULT 0,
  ADD COLUMN `proc_nfo` BIT NOT NULL DEFAULT 0,
  ADD COLUMN `proc_files` BIT NOT NULL DEFAULT 0,
  ADD INDEX `ix_releases_nfostatus` (`nfostatus` ASC) USING HASH,
  ADD INDEX `ix_releases_reqidstatus` (`reqidstatus` ASC) USING HASH,
  ADD INDEX `ix_releases_passwordstatus` (`passwordstatus`),
  ADD INDEX `ix_releases_dehashstatus` (`dehashstatus`),
  ADD INDEX `ix_releases_haspreview` (`haspreview` ASC) USING HASH,
  ADD INDEX `ix_releases_postdate_name` (`postdate`, `name`),
  ADD INDEX `ix_releases_status` (`iscategorized`, `isrenamed`, `nfostatus`, `ishashed`, `passwordstatus`, `dehashstatus`, `releasenfoID`, `musicinfoID`, `consoleinfoID`, `bookinfoID`, `haspreview`, `categoryID`, `imdbID`, `rageID`);

DROP TRIGGER IF EXISTS check_insert;
DROP TRIGGER IF EXISTS check_update;

DELIMITER $$
CREATE TRIGGER check_insert BEFORE INSERT ON releases FOR EACH ROW BEGIN IF NEW.searchname REGEXP '[a-fA-F0-9]{32}' OR NEW.name REGEXP '[a-fA-F0-9]{32}' THEN SET NEW.ishashed = 1;ELSEIF NEW.name REGEXP '^\\[[[:digit:]]+\\]' THEN SET NEW.isrequestid = 1; END IF; END;$$
CREATE TRIGGER check_update BEFORE UPDATE ON releases FOR EACH ROW BEGIN IF NEW.searchname REGEXP '[a-fA-F0-9]{32}' OR NEW.name REGEXP '[a-fA-F0-9]{32}' THEN SET NEW.ishashed = 1;ELSEIF NEW.name REGEXP '^\\[[[:digit:]]+\\]' THEN SET NEW.isrequestid = 1; END IF; END;$$
DELIMITER ;






