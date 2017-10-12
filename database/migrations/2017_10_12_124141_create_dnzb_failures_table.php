<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDnzbFailuresTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dnzb_failures', function (Blueprint $table) {
            $table->integer('release_id')->unsigned();
            $table->integer('userid')->unsigned();
            $table->integer('failed')->unsigned()->default(0);
            $table->primary(['release_id', 'userid']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('dnzb_failures');
    }
}
