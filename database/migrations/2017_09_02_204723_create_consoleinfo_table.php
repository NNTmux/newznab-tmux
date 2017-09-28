<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateConsoleinfoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('consoleinfo', function (Blueprint $table) {
            $table->engine = 'innodb';
            $table->increments('id');
            $table->string('title');
            $table->string('asin', '128')->nullable();
            $table->string('url', '1000')->nullable();
            $table->unsignedInteger('salesrank')->nullable();
            $table->string('platform')->nullable();
            $table->string('publisher')->nullable();
            $table->integer('genres_id')->nullable();
            $table->string('esrb')->nullable();
            $table->dateTime('releasedate')->nullable();
            $table->string('review', '3000')->nullable();
            $table->unsignedTinyInteger('cover')->default('0');
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
            $table->primary('id');
            $table->unique('asin', 'ix_consoleinfo_asin');
            DB::statement('ALTER TABLE consoleinfo ADD FULLTEXT INDEX ix_consoleinfo_title_platform_ft (title, platform)');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('consoleinfo');
    }
}
