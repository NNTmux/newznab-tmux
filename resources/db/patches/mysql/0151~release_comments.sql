# This patch will remove column, trigger and index

#Drop the trigger
DROP TRIGGER IF EXISTS insert_MD5;

#Drop the indexes
ALTER TABLE release_comments DROP INDEX ix_releasecomment_text_releaseID;
ALTER TABLE release_comments DROP INDEX ix_releasecomment_hash_gid;

#Drop the text_hash column
ALTER TABLE release_comments DROP text_hash;