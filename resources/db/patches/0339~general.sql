# Recreate delete_release stored procedure

DELIMITER $$

DROP PROCEDURE IF EXISTS delete_release;
CREATE PROCEDURE delete_release(IN is_numeric BOOLEAN, IN identifier VARCHAR(40))
  COMMENT 'Cascade deletes release from child tables when parent row is deleted'
  COMMENT 'If is_numeric is true, identifier should be the releases_id, if false the guid'

  main: BEGIN

    DECLARE where_constr VARCHAR(255) DEFAULT '';

    IF is_numeric IS TRUE
    THEN
      DELETE r
      FROM releases r
      WHERE r.id = identifier;

    ELSEIF is_numeric IS FALSE
    THEN
      DELETE r
      FROM releases r
      WHERE r.guid = identifier;

    ELSE LEAVE main;
    END IF;

  END;$$

DELIMITER;