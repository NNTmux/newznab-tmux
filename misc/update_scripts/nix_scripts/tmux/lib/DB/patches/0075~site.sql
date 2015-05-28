INSERT IGNORE INTO `site` (`setting`, `value`) VALUE ('alternate_nntp', '0');
UPDATE `tmux` SET `value` = '75' WHERE `setting` = 'sqlpatch';