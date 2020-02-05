<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateImdbColumnVideosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->string('imdb', 100)->default(0)->comment('ID number for IMDB site (without the \'tt\' prefix).')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->integer('imdb')->unsigned()->default(0)->index('ix_videos_imdb')->comment('ID number for IMDB site (without the \'tt\' prefix).');
        });
    }
}
