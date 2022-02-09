<?php

use Illuminate\Database\Migrations\Migration;
use NtimYeboah\LaravelDatabaseTrigger\TriggerFacade as Schema;

class CreateCheckUpdateTrigger extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('check_update')
            ->on('releases')
            ->statement(function () {
                return 'IF NEW.searchname REGEXP "[a-fA-F0-9]{32}" OR NEW.name REGEXP "[a-fA-F0-9]{32}" THEN SET NEW.ishashed = 1; END IF;';
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
        Schema::dropIfExists('releases.check_update');
    }
}
