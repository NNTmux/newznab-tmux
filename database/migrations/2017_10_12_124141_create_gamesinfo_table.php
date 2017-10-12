<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGamesinfoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gamesinfo', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title')->index('ix_title_ft');
            $table->string('asin', 128)->nullable()->default(null)->unique('ix_gamesinfo_asin');
            $table->string('url', 1000)->nullable()->default(null);
            $table->string('publisher')->nullable()->default(null);
            $table->integer('genres_id')->nullable()->default(null);
            $table->string('esrb')->nullable()->default(null);
            $table->dateTime('releasedate')->nullable()->default(null);
            $table->string('review', 3000)->nullable()->default(null);
            $table->boolean('cover')->default(0);
            $table->boolean('backdrop')->default(0);
            $table->string('trailer', 1000)->default('\'\'');
            $table->string('classused', 10)->default('\'steam\'');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('gamesinfo');
    }
}
