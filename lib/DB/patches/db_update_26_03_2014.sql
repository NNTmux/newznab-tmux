ALTER TABLE `releases` CHANGE COLUMN `prehashID` `prehashID` INT UNSIGNED NOT NULL DEFAULT '0';
UPDATE `tmux` set `value` = '9' where `setting` = 'sqlpatch';