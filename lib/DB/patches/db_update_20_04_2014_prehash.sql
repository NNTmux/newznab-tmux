ALTER TABLE prehash ADD COLUMN sha1 varchar(40) NOT NULL DEFAULT '0';
ALTER TABLE prehash MODIFY COLUMN md5 varchar(32) NOT NULL DEFAULT '0';
UPDATE prehash SET sha1 = sha1(title);
ALTER TABLE prehash ADD UNIQUE INDEX ix_prehash_sha1 (sha1);

UPDATE tmux SET value = '27' WHERE setting = 'sqlpatch';
