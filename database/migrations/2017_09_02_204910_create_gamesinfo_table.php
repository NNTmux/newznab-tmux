<?php

use Illuminate\Support\Facades\Schema;
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
            $table->engine = 'innodb';
            $table->increments('id');
            $table->string('title');
            $table->string('asin', '128')->nullable();
            $table->string('url', '1000')->nullable();
            $table->string('publisher')->nullable();
            $table->integer('genres_id')->nullable();
            $table->string('esrb')->nullable();
            $table->dateTime('releasedate')->nullable();
            $table->string('review', '3000')->nullable();
            $table->unsignedTinyInteger('cover')->default('0');
            $table->unsignedTinyInteger('backdrop')->default('0');
            $table->string('trailer', 1000)->default('');
            $table->string('classused', 10)->default('');
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
            $table->primary('id');
            $table->unique('asin', 'ix_gamesinfo_asin');
            $table->unique('title', 'ix_title');
            DB::statement('ALTER TABLE gamesinfo ADD FULLTEXT INDEX ix_gamesinfo_title_ft (title)');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gamesinfo');
    }
}
