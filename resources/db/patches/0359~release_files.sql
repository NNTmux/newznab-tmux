# Add crc32 column

ALTER TABLE release_files ADD crc32 VARCHAR(255) DEFAULT '' COMMENT 'crc32 checksum of the file';