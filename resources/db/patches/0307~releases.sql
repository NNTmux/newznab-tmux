# Add proc_srr column to releases table

ALTER TABLE releases ADD COLUMN proc_srr TINYINT(1) NOT NULL DEFAULT '0' COMMENT 'Has the release been srr
processed';

# Add proc_hash16k column to releases table

ALTER TABLE releases ADD COLUMN proc_hash16k TINYINT(1) NOT NULL DEFAULT '0' COMMENT 'Has the release been
hash16k processed';
