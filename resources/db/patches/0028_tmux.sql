INSERT IGNORE INTO tmux (setting, value) VALUE ('bins_kill_timer', '0');

UPDATE site SET value = '28' WHERE setting = 'sqlpatch';
