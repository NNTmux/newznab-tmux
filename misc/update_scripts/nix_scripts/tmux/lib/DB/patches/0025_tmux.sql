INSERT IGNORE INTO tmux (setting, value) VALUE ('yydecoderpath', '');

UPDATE site SET value = '25' WHERE setting = 'sqlpatch';
