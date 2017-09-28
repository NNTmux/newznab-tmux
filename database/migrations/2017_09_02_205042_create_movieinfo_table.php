<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMovieinfoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('movieinfo', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedMediumInteger('imdb');
            $table->unsignedInteger('tmdbid')->default(0);
            $table->string('title', 255)->default('');
            $table->string('tagline', 1024)->default('');
            $table->string('rating', 4)->default('');
            $table->string('plot', 1024)->default('');
            $table->string('year', 4)->default('');
            $table->string('genre', 64)->default('');
            $table->string('type', 32)->default('');
            $table->string('director', 64)->default('');
            $table->string('actors', 2000)->default('');
            $table->string('language', 64)->default('');
            $table->string('trailer', 255)->default('');
            $table->tinyInteger('cover')->default(0);
            $table->tinyInteger('backdrop')->default(0);
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
            $table->index('title', 'ix_movieinfo_title');
            $table->unique('imdbid', 'ix_movieinfo_imdbid');
            DB::statement('ALTER TABLE movieinfo CHANGE imdbid imdbid MEDIUMINT(7) UNSIGNED ZEROFILL NOT NULL');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('movieinfo');
    }
}
