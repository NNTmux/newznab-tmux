ALTER TABLE `releases` DROP COLUMN `nzb_guid`;
ALTER TABLE `releases` DROP INDEX `ix_releases_nzb_guid`;

UPDATE `site` set `value` = '31' where `setting` = 'sqlpatch';