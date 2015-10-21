ALTER TABLE releases ADD COLUMN nzbstatus TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE releases ADD COLUMN nzb_guid VARCHAR(50) NULL;
ALTER TABLE releases DROP INDEX ix_releases_status;
ALTER TABLE releases ADD INDEX ix_releases_status (nzbstatus, iscategorized, isrenamed, nfostatus, ishashed, passwordstatus, dehashstatus, releasenfoid, musicinfoid, consoleinfoid, bookinfoid, haspreview, categoryid, imdbid);
CREATE INDEX ix_releases_nzb_guid ON releases (nzb_guid);

UPDATE releases SET nzbstatus = 1 WHERE nzbstatus = 0;
