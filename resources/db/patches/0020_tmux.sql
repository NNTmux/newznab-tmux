DELETE FROM `tmux` WHERE `setting` = 'delete_parts';
DELETE FROM `tmux` WHERE `setting` = 'delete_timer';



UPDATE `tmux` set `value` = '20' where `setting` = 'sqlpatch';