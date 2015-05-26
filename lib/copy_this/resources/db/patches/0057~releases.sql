ALTER TABLE releases ADD COLUMN proc_sorter TINYINT(1) NOT NULL DEFAULT '0';
UPDATE `tmux` SET `value` = '57' WHERE `setting` = 'sqlpatch';