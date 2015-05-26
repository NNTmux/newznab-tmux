INSERT IGNORE INTO tmux (setting, value) VALUE ('sharing_timer', '60');

UPDATE tmux SET value = '17' WHERE setting = 'sqlpatch';
