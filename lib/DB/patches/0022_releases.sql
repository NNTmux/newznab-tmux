ALTER TABLE `releases` ADD INDEX `ix_releases_releasenfoID` (`releasenfoid`);

UPDATE `tmux` set `value` = '22' where `setting` = 'sqlpatch';