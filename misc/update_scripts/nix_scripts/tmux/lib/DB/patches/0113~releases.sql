ALTER TABLE releases ADD INDEX ix_releases_preid_searchname (preid, searchname);
UPDATE `tmux` SET `value` = '113' WHERE `setting` = 'sqlpatch';