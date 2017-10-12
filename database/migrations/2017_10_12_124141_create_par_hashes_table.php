<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateParHashesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('par_hashes', function (Blueprint $table) {
            $table->integer('releases_id')->comment('FK to releases.id');
            $table->string('hash', 32)->comment('hash_16k block of par2');
            $table->primary(['releases_id', 'hash']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('par_hashes');
    }
}
