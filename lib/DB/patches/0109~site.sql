INSERT IGNORE INTO `site` (`setting`, `value`) VALUES
('showdroppedyencparts', '0'),
('delaytime',	'2');
UPDATE `tmux` set `value` = '109' where `setting` = 'sqlpatch';