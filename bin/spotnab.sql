CREATE TABLE `spotnab_sources` (
		`ID` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		`userName` VARCHAR(64) NOT NULL DEFAULT 'NNTP',
		`userEmail` VARCHAR(128) NOT NULL DEFAULT 'SPOT@NNTP.COM',
		`usenetGroup` VARCHAR(64) NOT NULL DEFAULT 'alt.binaries.test2',
		`publicKey` VARCHAR(512) NOT NULL DEFAULT '',
		`active` TINYINT(1) NOT NULL DEFAULT '1',
		`description` VARCHAR(255) NULL DEFAULT '',
		`lastUpdate` DATETIME DEFAULT NULL,
		PRIMARY KEY  (`ID`)
		) ENGINE=MYISAM DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci AUTO_INCREMENT=1 ;

ALTER TABLE `releases` ADD  `GID` VARCHAR( 32 ) NULL AFTER  `ID` ;
ALTER TABLE `releases` ADD INDEX `ix_releases_GID` (  `GID` );

INSERT INTO `site` (`ID` , `setting` , `value` , `updateddate` )
	VALUES ( NULL ,  'spotnabsitepubkey',  '', NOW( ));
INSERT INTO `site` (`ID` , `setting` , `value` , `updateddate` )
	VALUES ( NULL ,  'spotnabsiteprvkey',  '', NOW( ));
INSERT INTO `site` (`ID` , `setting` , `value` , `updateddate` )
	VALUES ( NULL ,  'spotnabuser',  'NNTP', NOW( ));
INSERT INTO `site` (`ID` , `setting` , `value` , `updateddate` )
	VALUES ( NULL ,  'spotnabemail',  'SPOT@NNTP.COM', NOW( ));
INSERT INTO `site` (`ID` , `setting` , `value` , `updateddate` )
	VALUES ( NULL ,  'spotnabgroup',  'alt.binaries.test2', NOW( ));
INSERT INTO `site` (`ID` , `setting` , `value` , `updateddate` )
	VALUES ( NULL ,  'spotnabpost',  '1', NOW( ));

ALTER TABLE `releasecomment` ADD  `sourceID` BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER  `ID` ;
ALTER TABLE `releasecomment` ADD  `GID` VARCHAR( 32 ) DEFAULT NULL AFTER  `sourceID` ;
ALTER TABLE `releasecomment` ADD  `CID` VARCHAR( 32 ) DEFAULT NULL AFTER `GID` ;
ALTER TABLE `releasecomment` ADD  `username` VARCHAR( 50 ) DEFAULT NULL AFTER `userID` ;
ALTER TABLE `releasecomment` ADD  `isVisible` TINYINT(1) DEFAULT 1 AFTER `text` ;
ALTER TABLE `releasecomment` ADD  `isSynced` TINYINT(1) DEFAULT 0 AFTER `isVisible` ;
ALTER TABLE `releasecomment` ADD INDEX `ix_releasecomment_CID` ( `CID` );
ALTER TABLE `releasecomment` ADD INDEX `ix_releasecomment_GID` ( `GID` );
