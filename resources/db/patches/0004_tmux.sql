INSERT IGNORE INTO `tmux` (`setting`, `value`) VALUES ('partrepair', 1);

UPDATE `site` set `value` = '4' where `setting` = 'sqlpatch';