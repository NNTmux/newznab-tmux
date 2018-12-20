<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTriggers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement('CREATE TRIGGER check_insert BEFORE INSERT ON releases FOR EACH ROW BEGIN IF NEW.searchname REGEXP "[a-fA-F0-9]{32}" OR NEW.name REGEXP "[a-fA-F0-9]{32}"
      THEN SET NEW.ishashed = 1;
    END IF;
  END;

CREATE TRIGGER check_update BEFORE UPDATE ON releases FOR EACH ROW
  BEGIN
    IF NEW.searchname REGEXP "[a-fA-F0-9]{32}" OR NEW.name REGEXP "[a-fA-F0-9]{32}"
      THEN SET NEW.ishashed = 1;
    END IF;
  END;

CREATE TRIGGER check_rfinsert BEFORE INSERT ON release_files FOR EACH ROW
  BEGIN
    IF NEW.name REGEXP "[a-fA-F0-9]{32}"
      THEN SET NEW.ishashed = 1;
    END IF;
  END;

CREATE TRIGGER check_rfupdate BEFORE UPDATE ON release_files FOR EACH ROW
  BEGIN
    IF NEW.name REGEXP "[a-fA-F0-9]{32}"
      THEN SET NEW.ishashed = 1;
    END IF;
  END;

CREATE TRIGGER insert_hashes AFTER INSERT ON predb FOR EACH ROW BEGIN INSERT INTO predb_hashes (hash, predb_id) VALUES (UNHEX(MD5(NEW.title)), NEW.id), (UNHEX(MD5(MD5(NEW.title))), NEW.id), (UNHEX(SHA1(NEW.title)), NEW.id), (UNHEX(SHA2(NEW.title, 256)), NEW.id), (UNHEX(MD5(CONCAT(NEW.title, NEW.requestid))), NEW.id), (UNHEX(MD5(CONCAT(NEW.title, NEW.requestid, NEW.requestid))), NEW.id);END;

CREATE TRIGGER update_hashes AFTER UPDATE ON predb FOR EACH ROW BEGIN IF NEW.title != OLD.title THEN DELETE FROM predb_hashes WHERE hash IN ( UNHEX(md5(OLD.title)), UNHEX(md5(md5(OLD.title))), UNHEX(sha1(OLD.title)), UNHEX(sha2(OLD.title, 256)), UNHEX(MD5(CONCAT(OLD.title, OLD.requestid)))) AND predb_id = OLD.id; INSERT INTO predb_hashes (hash, predb_id) VALUES (UNHEX(MD5(NEW.title)), NEW.id), (UNHEX(MD5(MD5(NEW.title))), NEW.id), (UNHEX(SHA1(NEW.title)), NEW.id), (UNHEX(SHA2(NEW.title, 256)), NEW.id), (UNHEX(MD5(CONCAT((NEW.title, NEW.requestid)))), NEW.id), (UNHEX(MD5(CONCAT(NEW.title, NEW.requestid, NEW.requestid))), NEW.id);END IF;END;

CREATE TRIGGER delete_hashes AFTER DELETE ON predb FOR EACH ROW BEGIN DELETE FROM predb_hashes WHERE hash IN ( UNHEX(md5(OLD.title)), UNHEX(md5(md5(OLD.title))), UNHEX(sha1(OLD.title)), UNHEX(sha2(OLD.title, 256)), UNHEX(MD5(CONCAT(OLD.title, OLD.requestid))), UNHEX(MD5(CONCAT(OLD.title, OLD.requestid, OLD.requestid)))) AND predb_id = OLD.id;END;

CREATE TRIGGER insert_MD5 BEFORE INSERT ON release_comments FOR EACH ROW
  SET
    NEW.text_hash = MD5(NEW.text);
');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement('DROP TRIGGER check_insert; DROP TRIGGER check_update; DROP TRIGGER check_rfinsert; DROP TRIGGER check_rfupdate; DROP TRIGGER insert_hashes; DROP TRIGGER update_hashes; DROP TRIGGER delete_hashes; DROP TRIGGER insert_MD5;');
    }
}
