INSERT IGNORE INTO `tmux` (`setting`, `value`) VALUES ('partrepair', 1);

UPDATE `tmux` set `value` = '4' where `setting` = 'sqlpatch';