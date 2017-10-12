<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAudioDataTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('audio_data', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('releases_id')->unsigned()->comment('FK to releases.id');
            $table->integer('audioid')->unsigned();
            $table->string('audioformat', 50)->nullable()->default(null);
            $table->string('audiomode', 50)->nullable()->default(null);
            $table->string('audiobitratemode', 50)->nullable()->default(null);
            $table->string('audiobitrate', 10)->nullable()->default(null);
            $table->string('audiochannels', 25)->nullable()->default(null);
            $table->string('audiosamplerate', 25)->nullable()->default(null);
            $table->string('audiolibrary', 50)->nullable()->default(null);
            $table->string('audiolanguage', 50)->nullable()->default(null);
            $table->string('audiotitle', 50)->nullable()->default(null);
            $table->unique(['releases_id', 'audioid'], 'ix_releaseaudio_releaseid_audioid');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('audio_data');
    }
}
