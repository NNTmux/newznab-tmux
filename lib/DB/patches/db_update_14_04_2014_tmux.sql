INSERT IGNORE INTO tmux (setting, value) VALUE ('imdburl', '0');

UPDATE tmux SET value = '24' WHERE setting = 'sqlpatch';
