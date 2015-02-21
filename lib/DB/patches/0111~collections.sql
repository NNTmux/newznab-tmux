ALTER table collections add noise char(32) not null default '' after releaseid;
UPDATE `tmux` SET `value` = '111' WHERE `setting` = 'sqlpatch';