<?php

use Illuminate\Database\Migrations\Migration;
use NtimYeboah\LaravelDatabaseTrigger\TriggerFacade as Schema;

class CreateInsertHashesTrigger extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('insert_hashes')
            ->on('predb')
            ->statement(function () {
                return 'INSERT INTO predb_hashes (hash, predb_id) VALUES (UNHEX(MD5(NEW.title)), NEW.id), (UNHEX(MD5(MD5(NEW.title))), NEW.id), (UNHEX(SHA1(NEW.title)), NEW.id), (UNHEX(SHA2(NEW.title, 256)), NEW.id), (UNHEX(MD5(CONCAT(NEW.title, NEW.requestid))), NEW.id), (UNHEX(MD5(CONCAT(NEW.title, NEW.requestid, NEW.requestid))), NEW.id);';
            })
            ->after()
            ->insert();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('predb.insert_hashes');
    }
}
