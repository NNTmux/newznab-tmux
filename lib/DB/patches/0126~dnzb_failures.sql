RENAME TABLE failed_downloads TO dnzb_failures;
ALTER TABLE dnzb_failures DROP COLUMN status;
DROP INDEX ux_index_failed ON dnzb_failures;
DROP INDEX ix_failed_downloads_guid ON dnzb_failures;
DROP INDEX ix_failed_downloads_userid ON dnzb_failures;
CREATE UNIQUE INDEX ux_dnzb_failures ON dnzb_failures (userid, guid);
 UPDATE `tmux` set `value` = '126' where `setting` = 'sqlpatch';