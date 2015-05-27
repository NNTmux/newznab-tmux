INSERT IGNORE INTO tmux (setting, value) VALUE ('nntpretries', '10');
INSERT IGNORE INTO tmux (setting, value) VALUE ('alternate_nntp', '0');

UPDATE tmux SET value = '16' WHERE setting = 'sqlpatch';
