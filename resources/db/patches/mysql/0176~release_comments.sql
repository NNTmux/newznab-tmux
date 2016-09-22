# This patch will create a temp table like releasecomment
# Then it will alter the nzb_guid column to be BINARY(16)
# It will insert all data from releasecomment into the temp table while unhexing nzb_guid
# Drop old releasecomment table
# Rename new release_comments temp table to release_comments

CREATE TABLE release_comments_tmp LIKE releasecomment;
ALTER TABLE release_comments_tmp MODIFY nzb_guid BINARY(16) NOT NULL DEFAULT '0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0';
INSERT IGNORE INTO release_comments_tmp (SELECT id, sourceid, gid, cid, releaseid, text, isvisible, issynced, username, userid, createddate, host, shared, shareid, siteid, UNHEX(nzb_guid), text_hash FROM releasecomment);

DROP TABLE releasecomment;
ALTER TABLE release_comments_tmp RENAME release_comments;
