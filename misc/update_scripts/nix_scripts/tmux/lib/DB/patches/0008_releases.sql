ALTER TABLE `releases` ADD `prehashid` INT(12) NULL DEFAULT NULL;

UPDATE `site` set `value` = '8' where `setting` = 'sqlpatch';