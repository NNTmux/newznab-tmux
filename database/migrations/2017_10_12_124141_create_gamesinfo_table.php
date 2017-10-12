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
    public function up()
    {
        Schema::create('gamesinfo', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title')->index('ix_title_ft');
            $table->string('asin', 128)->nullable()->default('NULL')->unique('ix_gamesinfo_asin');
            $table->string('url', 1000)->nullable()->default('NULL');
            $table->string('publisher')->nullable()->default('NULL');
            $table->integer('genres_id')->nullable()->default('NULL');
            $table->string('esrb')->nullable()->default('NULL');
            $table->dateTime('releasedate')->nullable()->default('NULL');
            $table->string('review', 3000)->nullable()->default('NULL');
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
