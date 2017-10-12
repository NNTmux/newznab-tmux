<?php

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
            $table->increments('id');
            $table->string('title');
            $table->string('asin', 128)->nullable()->default(null)->unique('ix_consoleinfo_asin');
            $table->string('url', 1000)->nullable()->default(null);
            $table->integer('salesrank')->unsigned()->nullable()->default(null);
            $table->string('platform')->nullable()->default(null);
            $table->string('publisher')->nullable()->default(null);
            $table->integer('genres_id')->nullable()->default(null);
            $table->string('esrb')->nullable()->default(null);
            $table->dateTime('releasedate')->nullable()->default(null);
            $table->string('review', 3000)->nullable()->default(null);
            $table->boolean('cover')->default(0);
            $table->timestamps();
            $table->index(['title', 'platform'], 'ix_consoleinfo_title_platform_ft');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('consoleinfo');
    }
}
