DROP TABLE IF EXISTS shortgroups;
CREATE TABLE shortgroups (
	ID INT(11) NOT NULL AUTO_INCREMENT,
	name VARCHAR(255) NOT NULL DEFAULT "",
	first_record BIGINT UNSIGNED NOT NULL DEFAULT "0",
	last_record BIGINT UNSIGNED NOT NULL DEFAULT "0",
	updated DATETIME DEFAULT NULL,
	PRIMARY KEY (ID)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci AUTO_INCREMENT=1;

CREATE INDEX ix_shortgroups_id ON shortgroups(ID);
CREATE INDEX ix_shortgroups_name ON shortgroups(name);

INSERT IGNORE INTO `tmux` (`setting`, `value`) VALUES ('safebackfilldate', '2012-06-24'), ('safepartrepair', '0');

UPDATE `tmux` set `value` = '3' where `setting` = 'sqlpatch';