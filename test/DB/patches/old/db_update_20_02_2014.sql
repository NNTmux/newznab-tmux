ALTER TABLE `releases`
    DROP INDEX ix_releases_status,
    ADD INDEX ix_releases_status (nzbstatus, iscategorized, isrenamed, nfostatus, ishashed, isrequestid, passwordstatus, dehashstatus, reqidstatus, releasenfoID, musicinfoID, consoleinfoID, bookinfoID, haspreview, categoryID, imdbID, rageID);

