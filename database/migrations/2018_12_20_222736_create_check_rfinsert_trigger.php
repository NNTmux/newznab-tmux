<?php

use Illuminate\Database\Migrations\Migration;
use NtimYeboah\LaravelDatabaseTrigger\TriggerFacade as Schema;

class CreateCheckRfinsertTrigger extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('check_rfinsert')
            ->on('release_files')
            ->statement(function () {
                return 'IF NEW.name REGEXP "[a-fA-F0-9]{32}" THEN SET NEW.ishashed = 1; END IF;';
            })
            ->before()
            ->insert();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('release_files.check_rfinsert');
    }
}
