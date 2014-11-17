ALTER TABLE releasecomment DROP INDEX ix_releasecomment_hash_releaseID;
ALTER IGNORE TABLE releasecomment ADD UNIQUE INDEX ix_releasecomment_hash_gid_sourceid (text_hash, gid, sourceID);
UPDATE `tmux` set `value` = '104' where `setting` = 'sqlpatch';