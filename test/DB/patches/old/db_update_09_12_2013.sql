ALTER TABLE `prehash` ADD COLUMN `requestID` INT NOT NULL DEFAULT '0';
ALTER TABLE `prehash` ADD COLUMN `groupID` INT NOT NULL DEFAULT '0';
CREATE INDEX ix_prehash_requestid on prehash(requestID, groupID);