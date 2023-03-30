<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateTvEpisodesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tv_episodes', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8';
            $table->collation = 'utf8_unicode_ci';
            $table->increments('id');
            $table->integer('videos_id')->unsigned()->comment('FK to videos.id of the parent series.');
            $table->smallInteger('series')->unsigned()->default(0)->comment('Number of series/season.');
            $table->smallInteger('episode')->unsigned()->default(0)->comment('Number of episode within series');
            $table->string('se_complete', 10)->comment('String version of Series/Episode as taken from release subject (i.e. S02E21+22).');
            $table->string('title', 180)->comment('Title of the episode.');
            $table->date('firstaired')->comment('Date of original airing/release.');
            $table->text('summary', 65535)->comment('Description/summary of the episode.');
            $table->unique(['videos_id', 'series', 'episode', 'firstaired'], 'videos_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('tv_episodes');
    }
}
