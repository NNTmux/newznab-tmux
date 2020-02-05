<?php

use DariusIII\LaravelDatabaseTrigger\TriggerFacade as Schema;
use Illuminate\Database\Migrations\Migration;

class CreateCheckRfupdateTrigger extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('check_rfupdate')
            ->on('release_files')
            ->statement(function () {
                return 'IF NEW.name REGEXP "[a-fA-F0-9]{32}" THEN SET NEW.ishashed = 1; END IF;';
            })
            ->before()
            ->update();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('release_files.check_rfupdate');
    }
}
