INSERT IGNORE INTO tmux (setting, value) VALUE ('fanarttvkey', '');

UPDATE tmux SET value = '23' WHERE setting = 'sqlpatch';
