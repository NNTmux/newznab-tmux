DROP TABLE IF EXISTS releasesearch;
CREATE TABLE releasesearch (
  ID         INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  releaseID  INT(11) UNSIGNED NOT NULL,
  guid       VARCHAR(50)      NOT NULL,
  name       VARCHAR(255)     NOT NULL DEFAULT '',
  searchname VARCHAR(255)     NOT NULL DEFAULT '',
  PRIMARY KEY (ID)
) ENGINE =MyISAM DEFAULT CHARSET =utf8 COLLATE =utf8_unicode_ci AUTO_INCREMENT =1;

CREATE FULLTEXT INDEX ix_releasesearch_name_searchname_ft ON releasesearch (name, searchname);
CREATE INDEX ix_releasesearch_releaseid ON releasesearch (releaseID);
CREATE INDEX ix_releasesearch_guid ON releasesearch (guid);

ALTER TABLE `releases`
ADD `proc_filenames` BIT NOT NULL DEFAULT 0;

DELIMITER $$
CREATE TRIGGER insert_search AFTER INSERT ON releases FOR EACH ROW BEGIN INSERT INTO releasesearch (releaseID, guid, name, searchname) VALUES (NEW.ID, NEW.guid, NEW.name, NEW.searchname);END;$$
CREATE TRIGGER update_search AFTER UPDATE ON releases FOR EACH ROW BEGIN IF NEW.guid != OLD.guid THEN UPDATE releasesearch SET guid = NEW.guid WHERE releaseID = OLD.ID; END IF; IF NEW.name != OLD.name THEN UPDATE releasesearch SET name = NEW.name WHERE releaseID = OLD.ID; END IF; IF NEW.searchname != OLD.searchname THEN UPDATE releasesearch SET searchname = NEW.searchname WHERE releaseID = OLD.ID; END IF;END;$$
CREATE TRIGGER delete_search AFTER DELETE ON releases FOR EACH ROW BEGIN DELETE FROM releasesearch WHERE releaseID = OLD.ID;END;$$
DELIMITER ;

UPDATE `tmux` SET value = '38' WHERE `setting` = 'sqlpatch';

