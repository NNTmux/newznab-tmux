<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateXxxInfoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('xxxinfo', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title', 1024);
            $table->string('tagline', 1024);
            $table->binary('plot');
            $table->string('genre');
            $table->string('director');
            $table->string('actors', 2500);
            $table->text('extras');
            $table->text('productinfo');
            $table->text('trailers');
            $table->string('directurl', 2000);
            $table->string('classused', 20);
            $table->unsignedTinyInteger('cover');
            $table->unsignedTinyInteger('backdrop');
            $table->dateTime('createddate');
            $table->dateTime('updateddate');
            $table->unique('title', 'ix_xxxinfo_title');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('xxxinfo');
    }
}
