<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateUserMoviesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('user_movies', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8';
            $table->collation = 'utf8_unicode_ci';
            $table->increments('id');
            $table->integer('users_id')->unsigned();
            $table->string('imdbid')->nullable();
            $table->string('categories', 64)->nullable()->comment('List of categories for user movies');
            $table->timestamps();
            $table->index(['users_id', 'imdbid'], 'ix_usermovies_userid');
            $table->foreign('users_id', 'FK_users_um')->references('id')->on('users')->onUpdate('CASCADE')->onDelete('CASCADE');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::drop('user_movies');
    }
}
