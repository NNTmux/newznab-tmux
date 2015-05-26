ALTER TABLE  `releases` CHANGE  `nzbstatus`  `nzbstatus` TINYINT( 1 ) NOT NULL DEFAULT  '0';
UPDATE `tmux` SET `value` = '74' WHERE `setting` = 'sqlpatch';