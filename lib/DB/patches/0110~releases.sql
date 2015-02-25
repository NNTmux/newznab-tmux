ALTER TABLE releases ADD INDEX ix_releases_preid_searchname (preID, searchname);
UPDATE `tmux` SET `value` = '110' WHERE `setting` = 'sqlpatch';