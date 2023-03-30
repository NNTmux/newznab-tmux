<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateAnidbTitlesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('anidb_titles', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8';
            $table->collation = 'utf8_unicode_ci';
            $table->integer('anidbid')->unsigned()->comment('ID of title from AniDB');
            $table->string('type', 25)->comment('type of title.');
            $table->string('lang', 25);
            $table->string('title');
            $table->primary(['anidbid', 'type', 'lang', 'title']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('anidb_titles');
    }
}
