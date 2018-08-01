# Rename user_roles_id to roles_id

ALTER TABLE users CHANGE user_roles_id roles_id INT(11) NOT NULL DEFAULT '1' COMMENT 'FK to roles.id';
ALTER TABLE role_excluded_categories CHANGE user_roles_id roles_id INT(11) NOT NULL DEFAULT '1' COMMENT 'FK to roles.id';
