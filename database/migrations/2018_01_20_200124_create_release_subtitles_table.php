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
        Schema::create('release_subtitles', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8';
            $table->collation = 'utf8_unicode_ci';
            $table->increments('id');
            $table->integer('releases_id')->unsigned()->comment('FK to releases.id');
            $table->integer('subsid')->unsigned();
            $table->string('subslanguage', 50);
            $table->unique(['releases_id', 'subsid'], 'ix_releasesubs_releases_id_subsid');
            $table->foreign('releases_id', 'FK_rs_releases')->references('id')->on('releases')->onUpdate('CASCADE')->onDelete('CASCADE');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('release_subtitles');
    }
};
