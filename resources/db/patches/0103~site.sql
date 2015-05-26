INSERT IGNORE INTO `site` (`setting`, `value`) VALUES
  ('intanidbupdate', 7),
  ('lastanidbupdate',	0),
  ('timeoutpath',	''),
  ('timeoutseconds', 0);

UPDATE `tmux` set `value` = '103' where `setting` = 'sqlpatch';
