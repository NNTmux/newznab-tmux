<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAnidbEpisodesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('anidb_episodes', function (Blueprint $table) {
            $table->integer('anidbid')->comment('ID of title from AniDB');
            $table->integer('episodeid');
            $table->smallInteger('episode_no');
            $table->string('episode_title');
            $table->date('airdate');
            $table->index(
                [
                    'anidbid',
                    'episodeid',
                ]
            );
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('anidb_episodes');
    }
}
