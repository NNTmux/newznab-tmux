INSERT IGNORE INTO tmux (setting, value) VALUE ('minsizetopostprocess', '1');
UPDATE `tmux` SET `value` = '51' WHERE `setting` = 'sqlpatch';