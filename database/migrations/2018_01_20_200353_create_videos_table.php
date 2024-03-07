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
        Schema::create('videos', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8';
            $table->collation = 'utf8_unicode_ci';
            $table->increments('id')->comment('Show ID to be used in other tables as reference ');
            $table->boolean('type')->default(0)->comment('0 = TV, 1 = Film, 2 = Anime');
            $table->string('title', 180)->comment('Name of the video.');
            $table->char('countries_id', 2)->default('')->comment('Two character country code (FK to countries table).');
            $table->dateTime('started')->comment('Date (UTC) of production\'s first airing.');
            $table->integer('anidb')->unsigned()->default(0)->comment('ID number for anidb site');
            $table->integer('imdb')->unsigned()->default(0)->index('ix_videos_imdb')->comment('ID number for IMDB site (without the \'tt\' prefix).');
            $table->integer('tmdb')->unsigned()->default(0)->index('ix_videos_tmdb')->comment('ID number for TMDB site.');
            $table->integer('trakt')->unsigned()->default(0)->index('ix_videos_trakt')->comment('ID number for TraktTV site.');
            $table->integer('tvdb')->unsigned()->default(0)->index('ix_videos_tvdb')->comment('ID number for TVDB site');
            $table->integer('tvmaze')->unsigned()->default(0)->index('ix_videos_tvmaze')->comment('ID number for TVMaze site.');
            $table->integer('tvrage')->unsigned()->default(0)->index('ix_videos_tvrage')->comment('ID number for TVRage site.');
            $table->boolean('source')->default(0)->comment('Which site did we use for info?');
            $table->unique(['title', 'type', 'started', 'countries_id'], 'ix_videos_title');
            $table->index(['type', 'source'], 'ix_videos_type_source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('videos');
    }
};
