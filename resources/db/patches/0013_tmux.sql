INSERT IGNORE INTO tmux (setting, value) VALUE ('scrape_cz', 0);
INSERT IGNORE INTO tmux (setting, value) VALUE ('scrape_efnet', 0);

UPDATE tmux SET value = '13' WHERE setting = 'sqlpatch';
