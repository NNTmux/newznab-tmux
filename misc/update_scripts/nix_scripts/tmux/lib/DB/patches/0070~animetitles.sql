ALTER TABLE animetitles CHANGE createddate unixtime INT(12) UNSIGNED NOT NULL;
UPDATE `tmux` SET `value` = '70' WHERE `setting` = 'sqlpatch';