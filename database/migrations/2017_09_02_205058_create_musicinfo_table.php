<?php

use Illuminate\Support\Facades\Schema;
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
            $table->string('title', 255);
            $table->string('asin', 128)->nullable();
            $table->string('url', 1000)->nullable();
            $table->unsignedInteger('salesrank')->nullable();
            $table->string('artist', 255)->nullable();
            $table->string('publisher', 255)->nullable();
            $table->dateTime('releasedate')->nullable();
            $table->string('review', 3000)->nullable();
            $table->string('year', 4);
            $table->integer('genres_id')->nullable();
            $table->string('tracks', 3000)->default(0);
            $table->tinyInteger('cover')->default(0);
            $table->timestamps();
            $table->unique(['asin'], 'ux_musicinfo_asin');
            DB::statement('ALTER TABLE musicinfo ADD FULLTEXT INDEX ix_musicinfo_artist_title_ft (artist, title)');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('musicinfo');
    }
}
