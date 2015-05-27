INSERT IGNORE INTO tmux (setting, value) VALUE ('yydecoderpath', '');

UPDATE tmux SET value = '25' WHERE setting = 'sqlpatch';
