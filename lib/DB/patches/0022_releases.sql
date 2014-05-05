ALTER TABLE `releases` ADD INDEX `ix_releases_releasenfoID` (`releasenfoID`);

UPDATE `tmux` set `value` = '22' where `setting` = 'sqlpatch';