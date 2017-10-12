<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateReleaseextrafullTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('releaseextrafull', function (Blueprint $table) {
            $table->integer('releases_id')->unsigned()->primary()->comment('FK to releases.id');
            $table->text('mediainfo', 65535)->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('releaseextrafull');
    }
}
