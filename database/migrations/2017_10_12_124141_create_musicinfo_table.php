<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

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
            $table->string('asin', 128)->nullable()->default(null)->unique('ix_musicinfo_asin');
            $table->string('url', 1000)->nullable()->default(null);
            $table->integer('salesrank')->unsigned()->nullable()->default(null);
            $table->string('artist')->nullable()->default(null);
            $table->string('publisher')->nullable()->default(null);
            $table->dateTime('releasedate')->nullable()->default(null);
            $table->string('review', 3000)->nullable()->default(null);
            $table->string('year', 4);
            $table->integer('genres_id')->nullable()->default(null);
            $table->string('tracks', 3000)->nullable()->default(null);
            $table->boolean('cover')->default(0);
            $table->timestamps();
            $table->index(['artist', 'title'], 'ix_musicinfo_artist_title_ft');
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
