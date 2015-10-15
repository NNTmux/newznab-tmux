ALTER TABLE prehash DROP INDEX ix_prehash_title;
ALTER IGNORE TABLE prehash ADD UNIQUE INDEX ix_prehash_title (title);