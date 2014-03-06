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

DROP TABLE IF EXISTS prehash;
CREATE TABLE prehash (
	ID INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
	title VARCHAR(255) NOT NULL DEFAULT '',
	nfo VARCHAR(255) NULL,
	size VARCHAR(50) NULL,
	category VARCHAR(255) NULL,
	predate DATETIME DEFAULT NULL,
	adddate DATETIME DEFAULT NULL,
	source VARCHAR(50) NOT NULL DEFAULT '',
	md5 VARCHAR(255) NOT NULL DEFAULT '0',
	requestID INT(10) UNSIGNED NOT NULL DEFAULT '0',
	groupID INT(10) UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (ID)
) ENGINE=INNODB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci AUTO_INCREMENT=1;

CREATE INDEX ix_prehash_title ON prehash(title);
CREATE INDEX ix_prehash_nfo ON prehash(nfo);
CREATE INDEX ix_prehash_predate ON prehash(predate);
CREATE INDEX ix_prehash_adddate ON prehash(adddate);
CREATE INDEX ix_prehash_source ON prehash(source);
CREATE INDEX ix_prehash_requestid on prehash(requestID, groupID);
CREATE UNIQUE INDEX ix_prehash_md5 ON prehash(md5);

DROP TRIGGER IF EXISTS check_insert;
DROP TRIGGER IF EXISTS check_update;

DELIMITER $$
CREATE TRIGGER check_insert BEFORE INSERT ON releases FOR EACH ROW BEGIN IF NEW.searchname REGEXP '[a-fA-F0-9]{32}' OR NEW.name REGEXP '[a-fA-F0-9]{32}' THEN SET NEW.ishashed = 1;ELSEIF NEW.name REGEXP '^\\[[[:digit:]]+\\]' THEN SET NEW.isrequestid = 1; END IF; END;$$
CREATE TRIGGER check_update BEFORE UPDATE ON releases FOR EACH ROW BEGIN IF NEW.searchname REGEXP '[a-fA-F0-9]{32}' OR NEW.name REGEXP '[a-fA-F0-9]{32}' THEN SET NEW.ishashed = 1;ELSEIF NEW.name REGEXP '^\\[[[:digit:]]+\\]' THEN SET NEW.isrequestid = 1; END IF; END;$$
DELIMITER ;






