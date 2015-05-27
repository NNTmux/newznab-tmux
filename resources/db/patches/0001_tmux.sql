DELETE FROM `tmux` WHERE `setting` = 'releases_threaded';

UPDATE `tmux` set `value` = '1' where `setting` = 'sqlpatch';