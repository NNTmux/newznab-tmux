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
            $table->string('containerformat', 50)->nullable()->default(null);
            $table->string('overallbitrate', 20)->nullable()->default(null);
            $table->string('videoduration', 20)->nullable()->default(null);
            $table->string('videoformat', 50)->nullable()->default(null);
            $table->string('videocodec', 50)->nullable()->default(null);
            $table->integer('videowidth')->nullable()->default(null);
            $table->integer('videoheight')->nullable()->default(null);
            $table->string('videoaspect', 10)->nullable()->default(null);
            $table->float('videoframerate', 7, 4)->nullable()->default(null);
            $table->string('videolibrary', 50)->nullable()->default(null);
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
