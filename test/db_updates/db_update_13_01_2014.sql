DROP INDEX ix_prehash_releaseID ON prehash;
ALTER TABLE `prehash` change hash md5 VARCHAR(255) NOT NULL DEFAULT '0';
ALTER TABLE `predb` change hash md5 VARCHAR( 32 ) NULL;
