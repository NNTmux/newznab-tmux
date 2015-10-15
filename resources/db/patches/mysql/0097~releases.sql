ALTER TABLE releases ADD COLUMN nzb_guid VARCHAR(50) NULL;
ALTER TABLE releases ADD INDEX ix_releases_nzb_guid (nzb_guid);