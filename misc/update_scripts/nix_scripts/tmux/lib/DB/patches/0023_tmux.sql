INSERT IGNORE INTO tmux (setting, value) VALUE ('fanarttvkey', '');

UPDATE site SET value = '23' WHERE setting = 'sqlpatch';
