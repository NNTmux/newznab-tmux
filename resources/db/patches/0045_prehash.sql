ALTER TABLE prehash DROP INDEX ix_prehash_title;
ALTER IGNORE TABLE prehash ADD UNIQUE INDEX ix_prehash_title (title);
UPDATE `site` SET `value` = '45' WHERE `setting` = 'sqlpatch';