# Rename role column to user_roles_id

ALTER TABLE users CHANGE role user_roles_id INT(11) NOT NULL DEFAULT '1' COMMENT 'FK to user_roles.id';