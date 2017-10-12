<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLoggingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('logging', function (Blueprint $table) {
            $table->increments('id');
            $table->dateTime('time')->nullable()->default(null);
            $table->string('username', 50)->nullable()->default(null);
            $table->string('host', 40)->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('logging');
    }
}
