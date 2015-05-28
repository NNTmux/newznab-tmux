INSERT IGNORE INTO `site` (`setting`, `value`) VALUES
 ('timeoutpath', ''),
 ('timeoutseconds', '0');
UPDATE `tmux` SET `value` = '80' WHERE `setting` = 'sqlpatch';