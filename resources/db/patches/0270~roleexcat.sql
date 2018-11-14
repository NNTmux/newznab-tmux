#Rename roleexcat categoryid to categories_id column.

ALTER TABLE roleexcat
CHANGE COLUMN categoryid categories_id INT NOT NULL,
COMMENT 'Which category id to exclude';
