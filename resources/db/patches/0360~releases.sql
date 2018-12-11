# Add proc_crc32 column to releases table

ALTER ONLINE TABLE releases ADD COLUMN IF NOT EXISTS proc_crc32 BOOLEAN DEFAULT 0 COMMENT 'Has the release been crc32 processed';
