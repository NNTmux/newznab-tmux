<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateVideoDataTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('video_data', function (Blueprint $table) {
            $table->integer('releases_id')->unsigned()->primary()->comment('FK to releases.id');
            $table->string('containerformat', 50)->nullable()->default('NULL');
            $table->string('overallbitrate', 20)->nullable()->default('NULL');
            $table->string('videoduration', 20)->nullable()->default('NULL');
            $table->string('videoformat', 50)->nullable()->default('NULL');
            $table->string('videocodec', 50)->nullable()->default('NULL');
            $table->integer('videowidth')->nullable()->default('NULL');
            $table->integer('videoheight')->nullable()->default('NULL');
            $table->string('videoaspect', 10)->nullable()->default('NULL');
            $table->float('videoframerate', 7, 4)->nullable()->default('NULL');
            $table->string('videolibrary', 50)->nullable()->default('NULL');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('video_data');
    }
}
