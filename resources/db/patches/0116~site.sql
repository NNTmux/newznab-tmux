INSERT IGNORE INTO `site` (`setting`, `value`) VALUES
('request_url',	'http://reqid.newznab-tmux.pw/');
UPDATE `tmux` SET `value` = '116' WHERE `setting` = 'sqlpatch';