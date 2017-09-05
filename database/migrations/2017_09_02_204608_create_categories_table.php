<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCategoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title');
            $table->integer('parentid');
            $table->tinyInteger('status');
            $table->string('description');
            $table->tinyInteger('disablepreview');
            $table->bigInteger('minsizetoformrelease');
            $table->bigInteger('maxsizetoformrelease');
            $table->primary('id');
            $table->index('status');
            $table->index('parentid');
        });

        DB::update('ALTER TABLE categories AUTO_INCREMENT = 1000001');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('categories');
    }
}
