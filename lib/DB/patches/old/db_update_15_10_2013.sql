ALTER TABLE `releases` ADD  `nzbstatus` TINYINT NOT NULL DEFAULT 0 after `hashed`; 
CREATE INDEX ix_releases_nzbstatus ON releases(nzbstatus);
UPDATE releases SET nzbstatus = 1;

