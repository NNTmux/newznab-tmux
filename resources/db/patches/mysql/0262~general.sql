# Change release_comments.userid to release_comments.users_id to follow lithium convention.
ALTER TABLE release_comments CHANGE COLUMN userid users_id INT(11) UNSIGNED NOT NULL COMMENT 'FK to users.id';

# Change forumpost.userid to forumpost.users_id to follow lithium convention.
ALTER TABLE forumpost CHANGE COLUMN userid users_id INT(11) UNSIGNED NOT NULL COMMENT 'FK to users.id';

# Change user_movies.userid to user_movies.users_id to follow lithium convention.
ALTER TABLE user_movies CHANGE COLUMN userid users_id INT(11) UNSIGNED NOT NULL COMMENT 'FK to users.id';

# Change users_releases.userid to users_releases.users_id to follow lithium convention.
ALTER TABLE users_releases CHANGE COLUMN userid users_id INT(11) UNSIGNED NOT NULL COMMENT 'FK to users.id';

# Change user_excluded_cat.userid to user_excluded_cat.users_id to follow lithium convention.
ALTER TABLE user_excluded_categories CHANGE COLUMN userid users_id INT(11) UNSIGNED NOT NULL COMMENT 'FK to users.id';

# Change user_downloads.userid to user_downloads.users_id to follow lithium convention.
ALTER TABLE user_downloads CHANGE COLUMN userid users_id INT(11) UNSIGNED NOT NULL COMMENT 'FK to users.id';

# Change user_requests.userid to user_requests.users_id to follow lithium convention.
ALTER TABLE user_requests CHANGE COLUMN userid users_id INT(11) UNSIGNED NOT NULL COMMENT 'FK to users.id';

# Change user_series.userid to user_series.users_id to follow lithium convention.
ALTER TABLE user_series CHANGE COLUMN userid users_id INT(11) UNSIGNED NOT NULL COMMENT 'FK to users.id';

# Change invitations.userid to invitations.users_id to follow lithium convention.
ALTER TABLE invitations CHANGE COLUMN userid users_id INT(11) UNSIGNED NOT NULL COMMENT 'FK to users.id';

# Change dnzb_failures.userid to dnzb_failuress.users_id to follow lithium convention.
ALTER TABLE dnzb_failures CHANGE COLUMN userid users_id INT(11) UNSIGNED NOT NULL COMMENT 'FK to users.id';
