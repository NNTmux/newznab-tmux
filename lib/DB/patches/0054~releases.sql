ALTER TABLE releases ADD COLUMN gamesinfo_id INT AFTER consoleinfoID;
CREATE INDEX ix_releases_gamesinfo_id ON releases (gamesinfo_id);
UPDATE `tmux` SET `value` = '54' WHERE `setting` = 'sqlpatch';