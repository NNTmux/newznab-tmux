DROP INDEX ix_prehash_md5 ON prehash;
ALTER IGNORE TABLE prehash ADD CONSTRAINT ix_prehash_md5 UNIQUE (hash);
