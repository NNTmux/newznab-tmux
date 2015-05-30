ALTER TABLE `releases` DROP COLUMN `nzbstatus`;

UPDATE `site` set `value` = '5' where `setting` = 'sqlpatch';