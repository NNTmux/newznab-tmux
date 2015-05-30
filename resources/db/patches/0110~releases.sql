ALTER TABLE releases ADD INDEX ix_releases_preid_searchname (preid, searchname);
UPDATE `site` SET `value` = '110' WHERE `setting` = 'sqlpatch';