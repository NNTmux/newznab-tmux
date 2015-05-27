ALTER TABLE  `groups` ADD  `backfill` TINYINT(1) NOT NULL DEFAULT '0' AFTER  `active` ;

UPDATE `tmux` set `value` = '84' where `setting` = 'sqlpatch';