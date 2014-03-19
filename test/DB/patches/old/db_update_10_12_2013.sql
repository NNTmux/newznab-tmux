ALTER TABLE releases ADD reqidstatus TINYINT(1) NOT NULL DEFAULT '0' AFTER relnamestatus;
CREATE INDEX ix_releases_reqidstatus ON releases(reqidstatus ASC) USING HASH;
