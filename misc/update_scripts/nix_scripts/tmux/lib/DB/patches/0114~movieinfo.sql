ALTER TABLE movieinfo ADD COLUMN banner TINYINT(1) UNSIGNED NOT NULL DEFAULT '0';
UPDATE `site` set `value` = '114' where `setting` = 'sqlpatch';