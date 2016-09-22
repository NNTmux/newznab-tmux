# Rename user_series categories_id column to categories
ALTER TABLE user_series
CHANGE COLUMN categories categories VARCHAR(64) NULL DEFAULT NULL COMMENT 'List of categories for users tv shows';
