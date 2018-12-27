<?php

use Illuminate\Database\Migrations\Migration;
use NtimYeboah\LaravelDatabaseTrigger\TriggerFacade as Schema;

class CreateDeleteHashesTrigger extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('delete_hashes')
            ->on('predb')
            ->statement(function () {
                return 'DELETE FROM predb_hashes WHERE hash IN ( UNHEX(md5(OLD.title)), UNHEX(md5(md5(OLD.title))), UNHEX(sha1(OLD.title)), UNHEX(sha2(OLD.title, 256)), UNHEX(MD5(CONCAT(OLD.title, OLD.requestid))), UNHEX(MD5(CONCAT(OLD.title, OLD.requestid, OLD.requestid)))) AND predb_id = OLD.id;';
            })
            ->after()
            ->delete();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('predb.delete_hashes');
    }
}
