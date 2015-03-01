DELETE FROM `tmux` WHERE `setting` = 'max_load';
DELETE FROM `tmux` WHERE `setting` = 'max_load_releases';
UPDATE `tmux` set `value` = '112' where `setting` = 'sqlpatch';