/* Drop the adddate index */
ALTER TABLE prehash DROP INDEX ix_prehash_adddate;

/* Drop the adddate column */
ALTER TABLE prehash DROP COLUMN adddate;

/* Use tmux table to keep the last pre time (unixtime) */
INSERT INTO tmux (setting, value) VALUES ('lastpretime', '0');

UPDATE site SET value = '14' WHERE setting = 'sqlpatch';