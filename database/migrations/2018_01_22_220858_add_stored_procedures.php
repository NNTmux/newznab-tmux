<?php

use Illuminate\Database\Migrations\Migration;

class AddStoredProcedures extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS loop_cbpm; DROP PROCEDURE IF EXISTS delete_release; CREATE PROCEDURE loop_cbpm(IN method CHAR(10))
  COMMENT "Performs tasks on All CBPM tables one by one -- REPAIR/ANALYZE/OPTIMIZE or DROP/TRUNCATE"

    main: BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE tname VARCHAR(255) DEFAULT "";
    DECLARE regstr VARCHAR(255) CHARSET utf8 COLLATE utf8_general_ci DEFAULT "";

    DECLARE cur1 CURSOR FOR
      SELECT TABLE_NAME
      FROM information_schema.TABLES
      WHERE
        TABLE_SCHEMA = (SELECT DATABASE())
        AND TABLE_NAME REGEXP regstr
      ORDER BY TABLE_NAME ASC;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    IF method NOT IN ("repair", "analyze", "optimize", "drop", "truncate")
    THEN LEAVE main; END IF;

    IF method = "drop" THEN SET regstr = "^(collections|binaries|parts|missed_parts)_[0-9]+$";
    ELSE SET regstr = "^(multigroup_)?(collections|binaries|parts|missed_parts)(_[0-9]+)?$";
    END IF;

    OPEN cur1;
    cbpm_loop: LOOP FETCH cur1
    INTO tname;
      IF done
      THEN LEAVE cbpm_loop; END IF;
      SET @SQL := CONCAT(method, " TABLE ", tname);
      PREPARE _stmt FROM @SQL;
      EXECUTE _stmt;
      DEALLOCATE PREPARE _stmt;
    END LOOP;
    CLOSE cur1;
  END;


CREATE PROCEDURE delete_release(IN is_numeric BOOLEAN, IN identifier VARCHAR(40))
  COMMENT "Cascade deletes release from child tables when parent row is deleted"
  COMMENT "If is_numeric is true, identifier should be the releases_id, if false the guid"

  main: BEGIN

    DECLARE where_constr VARCHAR(255) DEFAULT "";

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

  END;');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::unprepared('DROP PROCEDURE loop_cbpm; DROP PROCEDURE delete_release;');
    }
}
