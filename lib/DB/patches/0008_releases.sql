ALTER TABLE `releases` ADD `prehashid` INT(12) NULL DEFAULT NULL;

UPDATE `tmux` set `value` = '8' where `setting` = 'sqlpatch';