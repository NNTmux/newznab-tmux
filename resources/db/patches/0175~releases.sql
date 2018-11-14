# This patch will create a temp table like releases
# Then it will alter the nzb_guid column to be BINARY(16)
# It will insert all data from releases into the temp table while unhexing nzb_guid
# Drop old releases table
# Rename new releases temp table to releases

CREATE TABLE releases_tmp LIKE releases;
ALTER TABLE releases_tmp MODIFY nzb_guid BINARY(16) NULL;
INSERT IGNORE INTO releases_tmp (SELECT id, gid, name, searchname, totalpart, groupid, size, postdate, adddate, updatedate, guid, fromname, completion, categoryid, regexid, tvinfoid, seriesfull, season, episode, tvtitle, tvairdate, imdbid, episodeinfoid, musicinfoid, consoleinfoid, bookinfoid, preid, anidbid, reqid, releasenfoid, grabs, comments, passwordstatus, rarinnerfilecount, haspreview, dehashstatus, nfostatus, jpgstatus, audiostatus, videostatus, reqidstatus, prehashid, iscategorized, isrenamed, ishashed, isrequestid, proc_pp, proc_par2, proc_nfo, proc_files, gamesinfo_id, xxxinfo_id, proc_sorter, nzbstatus, UNHEX(nzb_guid) FROM releases);

DROP TABLE releases;
ALTER TABLE releases_tmp RENAME releases;
