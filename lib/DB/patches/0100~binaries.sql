DROP TRIGGER IF EXISTS delete_binaries;

DELIMITER $$
CREATE TRIGGER delete_binaries BEFORE DELETE ON binaries FOR EACH ROW
  BEGIN
    DELETE FROM parts WHERE binaryID = OLD.ID;
  END; $$
DELIMITER ;

UPDATE tmux set value = '100' where setting = 'sqlpatch';