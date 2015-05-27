ALTER TABLE prehash DROP md5;
ALTER TABLE prehash DROP sha1;
UPDATE `tmux` SET value = '43' WHERE `setting` = 'sqlpatch';