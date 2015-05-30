ALTER TABLE users ADD COLUMN gameview INT AFTER consoleview;
UPDATE `site` SET `value` = '55' WHERE `setting` = 'sqlpatch';