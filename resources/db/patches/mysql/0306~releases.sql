# Increase VARCHAR of releases.name column to 400
ALTER TABLE releases MODIFY COLUMN name VARCHAR(400) NOT NULL DEFAULT '' COMMENT 'Release usenet name';

# Increase VARCHAR or releases.searchname column to 300
ALTER TABLE releases MODIFY COLUMN searchname VARCHAR(300) NOT NULL DEFAULT '' COMMENT 'Release search name';
