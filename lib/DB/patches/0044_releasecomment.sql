ALTER TABLE releasecomment MODIFY text varchar(255);
ALTER IGNORE TABLE releasecomment ADD UNIQUE INDEX ix_releasecomment_text_releaseID (text, releaseID);
UPDATE `tmux` SET value = '44' WHERE `setting` = 'sqlpatch';

