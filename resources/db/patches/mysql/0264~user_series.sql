# Rename user_series categories_id column to categories
ALTER TABLE user_series
CHANGE COLUMN categories_id categories INT NOT NULL,
COMMENT 'Array of categories for users tv shows';