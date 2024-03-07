<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('audio_data', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8';
            $table->collation = 'utf8_unicode_ci';
            $table->increments('id');
            $table->integer('releases_id')->unsigned()->comment('FK to releases.id');
            $table->integer('audioid')->unsigned();
            $table->string('audioformat', 50)->nullable();
            $table->string('audiomode', 50)->nullable();
            $table->string('audiobitratemode', 50)->nullable();
            $table->string('audiobitrate', 10)->nullable();
            $table->string('audiochannels', 25)->nullable();
            $table->string('audiosamplerate', 25)->nullable();
            $table->string('audiolibrary', 50)->nullable();
            $table->string('audiolanguage', 50)->nullable();
            $table->string('audiotitle', 50)->nullable();
            $table->unique(['releases_id', 'audioid'], 'ix_releaseaudio_releaseid_audioid');
            $table->foreign('releases_id', 'FK_ad_releases')->references('id')->on('releases')->onUpdate('CASCADE')->onDelete('CASCADE');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('audio_data');
    }
};
