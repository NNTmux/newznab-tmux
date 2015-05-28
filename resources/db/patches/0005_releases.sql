ALTER TABLE `releases` DROP COLUMN `nzbstatus`;

UPDATE `tmux` set `value` = '5' where `setting` = 'sqlpatch';