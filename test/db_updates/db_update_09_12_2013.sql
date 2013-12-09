ALTER TABLE `prehash` ADD COLUMN `requestid` INT NOT NULL DEFAULT '0';
ALTER TABLE `prehash` ADD COLUMN `groupid` INT NOT NULL DEFAULT '0';
CREATE INDEX ix_prehash_requestid on prehash(requestid, groupid);