ALTER TABLE `releases` ADD INDEX `ix_releases_releasenfoID` (`releasenfoid`);

UPDATE `site` set `value` = '22' where `setting` = 'sqlpatch';