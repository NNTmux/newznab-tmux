INSERT IGNORE INTO tmux (setting, value) VALUE ('lastpretime', '0');

UPDATE `site` SET `value` = '40' WHERE `setting` = 'sqlpatch';