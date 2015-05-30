DELETE FROM `tmux` WHERE `setting` = 'releases_threaded';

UPDATE `site` set `value` = '1' where `setting` = 'sqlpatch';