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
            $table->string('type', 32)->nullable()->default(null);
            $table->date('startdate')->nullable()->default(null);
            $table->date('enddate')->nullable()->default(null);
            $table->dateTime('updated')->default('current_timestamp()');
            $table->string('related', 1024)->nullable()->default(null);
            $table->string('similar', 1024)->nullable()->default(null);
            $table->string('creators', 1024)->nullable()->default(null);
            $table->text('description', 65535)->nullable()->default(null);
            $table->string('rating', 5)->nullable()->default(null);
            $table->string('picture')->nullable()->default(null);
            $table->string('categories', 1024)->nullable()->default(null);
            $table->string('characters', 1024)->nullable()->default(null);
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
