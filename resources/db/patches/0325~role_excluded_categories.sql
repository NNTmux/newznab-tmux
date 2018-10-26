# Alter the role column, rename it to user_roles_id

ALTER TABLE role_excluded_categories CHANGE role user_roles_id INT(11) NOT NULL;