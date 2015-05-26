ALTER TABLE users ADD COLUMN gameview INT AFTER consoleview;
UPDATE `tmux` SET `value` = '55' WHERE `setting` = 'sqlpatch';