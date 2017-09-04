<?php

use Illuminate\Support\Facades\Schema;
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
            $table->integer('anidbid', false, true)->comment('ID of title from AniDB');
            $table->string('type');
            $table->date('startdate');
            $table->date('enddate');
            $table->timestamp('updated');
            $table->string('related');
            $table->string('similar');
            $table->string('creators');
            $table->text('description');
            $table->string('rating');
            $table->string('picture');
            $table->string('categories');
            $table->string('characters');
            $table->index(['startdate', 'enddate', 'updated']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('anidb_info');
    }
}
