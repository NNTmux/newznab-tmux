# Change releases.groupid to releases.groups_id to follow lithium convention.
ALTER TABLE releases CHANGE COLUMN groupid groups_id INT(11) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'FK to groups.id';

# Change predb.groupid to predb.groups_id to follow lithium convention.
ALTER TABLE predb CHANGE COLUMN groupid groups_id INT(11) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'FK to groups.id';

# Change predb_imports.groupid to predb_imports.groups_id to follow lithium convention.
ALTER TABLE predb_imports CHANGE COLUMN groupid groups_id INT(11) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'FK to groups.id';
