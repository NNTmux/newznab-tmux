DELETE FROM `tmux` WHERE `setting` = 'scrape_cz';
DELETE FROM `tmux` WHERE `setting` = 'scrape_efnet';
INSERT IGNORE INTO `tmux` (setting, value) VALUE ('scrape', '0');

UPDATE `tmux` SET value = '34' WHERE `setting` = 'sqlpatch';
