ALTER TABLE releasecomment MODIFY text varchar(255);
DROP INDEX ix_releasecomment_text_releaseID ON releasecomment;
CREATE UNIQUE INDEX ix_releasecomment_text_releaseID ON releasecomment (text, releaseid);

