<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateMusicinfoTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('musicinfo', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title');
            $table->string('asin', 128)->nullable()->default('NULL')->unique('ix_musicinfo_asin');
            $table->string('url', 1000)->nullable()->default('NULL');
            $table->integer('salesrank')->unsigned()->nullable()->default('NULL');
            $table->string('artist')->nullable()->default('NULL');
            $table->string('publisher')->nullable()->default('NULL');
            $table->dateTime('releasedate')->nullable()->default('NULL');
            $table->string('review', 3000)->nullable()->default('NULL');
            $table->string('year', 4);
            $table->integer('genres_id')->nullable()->default('NULL');
            $table->string('tracks', 3000)->nullable()->default('NULL');
            $table->boolean('cover')->default(0);
            $table->timestamps();
            $table->index(['artist','title'], 'ix_musicinfo_artist_title_ft');
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('musicinfo');
    }
}
