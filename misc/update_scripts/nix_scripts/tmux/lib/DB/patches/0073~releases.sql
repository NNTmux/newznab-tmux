CREATE INDEX `ix_releases_xxxinfo_id` ON `releases` (`xxxinfo_id`);
UPDATE `tmux` SET `value` = '73' WHERE `setting` = 'sqlpatch';