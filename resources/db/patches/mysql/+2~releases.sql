# Add movieinfo_id column to releases table

ALTER TABLE releases ADD movieinfo_id INT DEFAULT NULL;
ALTER TABLE releases ADD INDEX ix_releases_movieinfo_id(movieinfo_id);
