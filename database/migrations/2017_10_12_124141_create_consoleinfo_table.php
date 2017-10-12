<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

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
            $table->string('asin', 128)->nullable()->default('NULL')->unique('ix_consoleinfo_asin');
            $table->string('url', 1000)->nullable()->default('NULL');
            $table->integer('salesrank')->unsigned()->nullable()->default('NULL');
            $table->string('platform')->nullable()->default('NULL');
            $table->string('publisher')->nullable()->default('NULL');
            $table->integer('genres_id')->nullable()->default('NULL');
            $table->string('esrb')->nullable()->default('NULL');
            $table->dateTime('releasedate')->nullable()->default('NULL');
            $table->string('review', 3000)->nullable()->default('NULL');
            $table->boolean('cover')->default(0);
            $table->timestamps();
            $table->index(['title','platform'], 'ix_consoleinfo_title_platform_ft');
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
