<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateGamesinfoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('gamesinfo', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8';
            $table->collation = 'utf8_unicode_ci';
            $table->increments('id');
            $table->string('title');
            $table->string('asin', 128)->nullable()->unique('ix_gamesinfo_asin');
            $table->string('url', 1000)->nullable();
            $table->string('publisher')->nullable();
            $table->integer('genres_id')->nullable();
            $table->string('esrb')->nullable();
            $table->dateTime('releasedate')->nullable();
            $table->string('review', 3000)->nullable();
            $table->boolean('cover')->default(0);
            $table->boolean('backdrop')->default(0);
            $table->string('trailer', 1000)->default('');
            $table->string('classused', 10)->default('steam');
            $table->timestamps();
            $table->fullText('title', 'ix_title_ft');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::drop('gamesinfo');
    }
}
