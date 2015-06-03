DELETE FROM `tmux` WHERE `setting` = 'max_load';
DELETE FROM `tmux` WHERE `setting` = 'max_load_releases';
UPDATE `site` set `value` = '112' where `setting` = 'sqlpatch';