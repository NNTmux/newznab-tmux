<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAnidbInfoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('anidb_info', function (Blueprint $table) {
            $table->integer('anidbid')->unsigned()->primary()->comment('ID of title from AniDB');
            $table->string('type', 32)->nullable()->default('NULL');
            $table->date('startdate')->nullable()->default('NULL');
            $table->date('enddate')->nullable()->default('NULL');
            $table->dateTime('updated')->default('current_timestamp()');
            $table->string('related', 1024)->nullable()->default('NULL');
            $table->string('similar', 1024)->nullable()->default('NULL');
            $table->string('creators', 1024)->nullable()->default('NULL');
            $table->text('description', 65535)->nullable()->default('NULL');
            $table->string('rating', 5)->nullable()->default('NULL');
            $table->string('picture')->nullable()->default('NULL');
            $table->string('categories', 1024)->nullable()->default('NULL');
            $table->string('characters', 1024)->nullable()->default('NULL');
            $table->index(['startdate', 'enddate', 'updated'], 'ix_anidb_info_datetime');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('anidb_info');
    }
}
