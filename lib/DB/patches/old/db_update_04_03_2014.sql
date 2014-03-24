DROP TRIGGER IF EXISTS check_insert;
DROP TRIGGER IF EXISTS check_update;

DELIMITER $$
CREATE TRIGGER check_insert BEFORE INSERT ON releases FOR EACH ROW BEGIN IF NEW.searchname REGEXP '[a-fA-F0-9]{32}' OR NEW.name REGEXP '[a-fA-F0-9]{32}' THEN SET NEW.ishashed = 1;ELSEIF NEW.name REGEXP '^\\[[[:digit:]]+\\]' THEN SET NEW.isrequestid = 1;ELSEIF NEW.releasenfoID = 0 THEN SET NEW.nfostatus = -1; END IF; END;$$
CREATE TRIGGER check_update BEFORE UPDATE ON releases FOR EACH ROW BEGIN IF NEW.searchname REGEXP '[a-fA-F0-9]{32}' OR NEW.name REGEXP '[a-fA-F0-9]{32}' THEN SET NEW.ishashed = 1;ELSEIF NEW.name REGEXP '^\\[[[:digit:]]+\\]' THEN SET NEW.isrequestid = 1;ELSEIF NEW.releasenfoID = 0 THEN SET NEW.nfostatus = -1; END IF; END;$$
DELIMITER ;

UPDATE releases SET nfostatus = -7 where releasenfoID = -1;
UPDATE releases SET nfostatus = 1 where releasenfoID NOT IN (0,-1);
