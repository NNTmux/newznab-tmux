# Rename createddate and updateddate columns into created_at and updated_at respectively

ALTER TABLE bookinfo CHANGE COLUMN createddate created_at DATETIME NOT NULL;
ALTER TABLE bookinfo CHANGE COLUMN updateddate updated_at DATETIME NOT NULL;

ALTER TABLE consoleinfo CHANGE COLUMN createddate created_at DATETIME NOT NULL;
ALTER TABLE consoleinfo CHANGE COLUMN updateddate updated_at DATETIME NOT NULL;

ALTER TABLE forumpost CHANGE COLUMN createddate created_at DATETIME NOT NULL;
ALTER TABLE forumpost CHANGE COLUMN updateddate updated_at DATETIME NOT NULL;

ALTER TABLE gamesinfo CHANGE COLUMN createddate created_at DATETIME NOT NULL;
ALTER TABLE gamesinfo CHANGE COLUMN updateddate updated_at DATETIME NOT NULL;

ALTER TABLE invitations CHANGE COLUMN createddate created_at DATETIME NOT NULL;
ALTER TABLE invitations ADD COLUMN updated_at DATETIME NOT NULL;

ALTER TABLE movieinfo CHANGE COLUMN createddate created_at DATETIME NOT NULL;
ALTER TABLE movieinfo CHANGE COLUMN updateddate updated_at DATETIME NOT NULL;

ALTER TABLE musicinfo CHANGE COLUMN createddate created_at DATETIME NOT NULL;
ALTER TABLE musicinfo CHANGE COLUMN updateddate updated_at DATETIME NOT NULL;

ALTER TABLE release_comments CHANGE COLUMN createddate created_at DATETIME NOT NULL;
ALTER TABLE release_comments ADD COLUMN updated_at DATETIME NOT NULL;

ALTER TABLE release_files CHANGE COLUMN createddate created_at DATETIME DEFAULT NULL;
ALTER TABLE release_files ADD COLUMN updated_at DATETIME NOT NULL;

ALTER TABLE users CHANGE COLUMN createddate created_at DATETIME NOT NULL;
ALTER TABLE users CHANGE COLUMN updateddate updated_at DATETIME NOT NULL;

ALTER TABLE users_releases CHANGE COLUMN createddate created_at DATETIME NOT NULL;
ALTER TABLE users_releases ADD COLUMN updated_at DATETIME NOT NULL;

ALTER TABLE user_excluded_categories CHANGE COLUMN createddate created_at DATETIME NOT NULL;
ALTER TABLE user_excluded_categories ADD COLUMN updated_at DATETIME NOT NULL;

ALTER TABLE role_excluded_categories CHANGE COLUMN createddate created_at DATETIME NOT NULL;
ALTER TABLE role_excluded_categories ADD COLUMN updated_at DATETIME NOT NULL;

ALTER TABLE user_movies CHANGE COLUMN createddate created_at DATETIME NOT NULL;
ALTER TABLE user_movies ADD COLUMN updated_at DATETIME NOT NULL;

ALTER TABLE user_series CHANGE COLUMN createddate created_at DATETIME NOT NULL;
ALTER TABLE user_series ADD COLUMN updated_at DATETIME NOT NULL;

ALTER TABLE xxxinfo CHANGE COLUMN createddate created_at DATETIME NOT NULL;
ALTER TABLE xxxinfo CHANGE COLUMN updateddate updated_at DATETIME NOT NULL;