ALTER TABLE releasecomment DROP INDEX ix_releasecomment_hash_releaseID;
ALTER TABLE releasecomment DROP INDEX ix_releasecomment_hash_gid_sourceid;
ALTER IGNORE TABLE releasecomment ADD UNIQUE INDEX ix_releasecomment_hash_gid (text_hash, gid);