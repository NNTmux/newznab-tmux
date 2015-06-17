ALTER TABLE xxxinfo MODIFY COLUMN classused varchar(4) DEFAULT 'ade';
UPDATE xxxinfo SET classused = 'aebn' WHERE classused = 'aeb';
UPDATE `site` SET `value` = '68' WHERE `setting` = 'sqlpatch';