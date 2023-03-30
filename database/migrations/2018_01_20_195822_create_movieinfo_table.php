<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateMovieinfoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('movieinfo', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8';
            $table->collation = 'utf8_unicode_ci';
            $table->increments('id');
            $table->string('imdbid')->unique('ix_movieinfo_imdbid');
            $table->integer('tmdbid')->unsigned()->default(0);
            $table->integer('traktid')->unsigned()->default(0);
            $table->string('title')->default('')->index('ix_movieinfo_title');
            $table->string('tagline', 1024)->default('');
            $table->string('rating', 4)->default('');
            $table->string('rtrating', 10)->default('')->comment('RottenTomatoes rating score');
            $table->string('plot', 1024)->default('');
            $table->string('year', 4)->default('');
            $table->string('genre', 64)->default('');
            $table->string('type', 32)->default('');
            $table->string('director', 64)->default('');
            $table->string('actors', 2000)->default('');
            $table->string('language', 64)->default('');
            $table->boolean('cover')->default(0);
            $table->boolean('backdrop')->default(0);
            $table->timestamps();
            $table->string('trailer')->default('');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::drop('movieinfo');
    }
}
