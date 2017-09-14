# Add updateddate column to users table

ALTER TABLE users ADD COLUMN updateddate DATETIME NOT NULL COMMENT 'User related data update time' AFTER createddate;