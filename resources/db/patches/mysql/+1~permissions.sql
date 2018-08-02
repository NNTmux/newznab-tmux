# Add new user permission related tables

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS roles;
CREATE TABLE roles (
	id int(10) unsigned NOT NULL AUTO_INCREMENT,
	name varchar(255) NOT NULL,
	guard_name varchar(255) NOT NULL,
	apirequests int(10) unsigned NOT NULL,
	rate_limit int(11) NOT NULL DEFAULT 60,
	downloadrequests int(10) unsigned NOT NULL,
	defaultinvites int(10) unsigned NOT NULL,
	isdefault tinyint(1) NOT NULL DEFAULT 0,
	donation int(11) NOT NULL DEFAULT 0,
	addyears int(11) NOT NULL DEFAULT 0,
	created_at timestamp NULL DEFAULT NULL,
	updated_at timestamp NULL DEFAULT NULL,
	PRIMARY KEY (id)
)
	ENGINE=InnoDB
	AUTO_INCREMENT=1
	DEFAULT CHARSET=utf8
	COLLATE=utf8_unicode_ci;

DROP TABLE IF EXISTS role_has_permissions;
CREATE TABLE role_has_permissions (
	permission_id int(10) unsigned NOT NULL,
	role_id int(10) unsigned NOT NULL,
	PRIMARY KEY (permission_id,role_id),
	KEY role_has_permissions_role_id_foreign (role_id),
	CONSTRAINT role_has_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE,
	CONSTRAINT role_has_permissions_role_id_foreign FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE
)
	ENGINE=InnoDB
	DEFAULT CHARSET=utf8
	COLLATE=utf8_unicode_ci;

DROP TABLE IF EXISTS permissions;
CREATE TABLE permissions (
	id int(10) unsigned NOT NULL AUTO_INCREMENT,
	name varchar(255)  NOT NULL,
	guard_name varchar(255) NOT NULL,
	created_at timestamp NULL DEFAULT NULL,
	updated_at timestamp NULL DEFAULT NULL,
	PRIMARY KEY (id)
)
	ENGINE=InnoDB
	DEFAULT CHARSET=utf8
	COLLATE=utf8_unicode_ci;

DROP TABLE IF EXISTS model_has_permissions;
CREATE TABLE model_has_permissions (
	permission_id int(10) unsigned NOT NULL,
	model_type varchar(255) NOT NULL,
	model_id bigint(20) unsigned NOT NULL,
	PRIMARY KEY (permission_id,model_id,model_type),
	KEY model_has_permissions_model_type_model_id_index (model_type,model_id),
	CONSTRAINT model_has_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
)
	ENGINE=InnoDB
	DEFAULT CHARSET=utf8
	COLLATE=utf8_unicode_ci;

DROP TABLE IF EXISTS model_has_roles;
CREATE TABLE model_has_roles (
	role_id int(10) unsigned NOT NULL,
	model_type varchar(255) COLLATE utf8_unicode_ci NOT NULL,
	model_id bigint(20) unsigned NOT NULL,
	PRIMARY KEY (role_id,model_id,model_type),
	KEY model_has_roles_model_type_model_id_index (model_type,model_id),
	CONSTRAINT model_has_roles_role_id_foreign FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE
)
	ENGINE=InnoDB
	DEFAULT CHARSET=utf8
	COLLATE=utf8_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
