ALTER TABLE `releases`
    DROP INDEX ix_releases_status,
    CHANGE COLUMN `nzbstatus` `nzbstatus` BIT NOT NULL DEFAULT 0,
    CHANGE COLUMN `iscategorized` `iscategorized` BIT NOT NULL DEFAULT 0,
    CHANGE COLUMN `isrenamed` `isrenamed` BIT NOT NULL DEFAULT 0,
    CHANGE COLUMN `ishashed` `ishashed` BIT NOT NULL DEFAULT 0,
    CHANGE COLUMN `isrequestid` `isrequestid` BIT NOT NULL DEFAULT 0,
    ADD COLUMN `proc_par2` BIT NOT NULL DEFAULT 0,
    ADD COLUMN `proc_nfo` BIT NOT NULL DEFAULT 0,
    ADD COLUMN `proc_files` BIT NOT NULL DEFAULT 0,
    ADD INDEX ix_releases_status (iscategorized, isrenamed, nfostatus, ishashed, passwordstatus, dehashstatus, reqidstatus, musicinfoID, consoleinfoID, bookinfoID, haspreview, categoryID, imdbID, rageID);

UPDATE releases SET proc_par2 = 1 WHERE (bitwise & 32) = 32;
UPDATE releases SET proc_nfo = 1 WHERE (bitwise & 64) = 64;
UPDATE releases SET proc_files = 1 WHERE (bitwise & 128) = 128;
ALTER TABLE `releases` DROP COLUMN `bitwise`;

