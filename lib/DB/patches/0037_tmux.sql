INSERT IGNORE INTO tmux (setting, value) VALUE ('run_sharing', '0');

UPDATE `tmux` SET value = '37' WHERE `setting` = 'sqlpatch';