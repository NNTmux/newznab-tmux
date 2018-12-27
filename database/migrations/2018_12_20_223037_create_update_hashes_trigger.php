<?php

use Illuminate\Database\Migrations\Migration;
use NtimYeboah\LaravelDatabaseTrigger\TriggerFacade as Schema;

class CreateUpdateHashesTrigger extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('update_hashes')
            ->on('predb')
            ->statement(function () {
                return 'IF NEW.title != OLD.title THEN DELETE FROM predb_hashes WHERE hash IN ( UNHEX(md5(OLD.title)), UNHEX(md5(md5(OLD.title))), UNHEX(sha1(OLD.title)), UNHEX(sha2(OLD.title, 256)), UNHEX(MD5(CONCAT(OLD.title, OLD.requestid)))) AND predb_id = OLD.id; INSERT INTO predb_hashes (hash, predb_id) VALUES (UNHEX(MD5(NEW.title)), NEW.id), (UNHEX(MD5(MD5(NEW.title))), NEW.id), (UNHEX(SHA1(NEW.title)), NEW.id), (UNHEX(SHA2(NEW.title, 256)), NEW.id), (UNHEX(MD5(CONCAT((NEW.title, NEW.requestid)))), NEW.id), (UNHEX(MD5(CONCAT(NEW.title, NEW.requestid, NEW.requestid))), NEW.id);END IF;';
            })
            ->after()
            ->update();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('predb.update_hashes');
    }
}
