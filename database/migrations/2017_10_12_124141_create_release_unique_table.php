<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateReleaseUniqueTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('release_unique', function (Blueprint $table) {
            $table->integer('releases_id')->unsigned()->comment('FK to releases.id.');
            $table->binary('uniqueid', 16)->default('\'0$UP$$UP$$UP$$UP$$UP$$UP$$UP$$UP$$UP$$UP$$UP$$UP$$UP$$UP$$UP$\'')->comment('Unique_ID from mediainfo.');
            $table->primary(['releases_id','uniqueid']);
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('release_unique');
    }
}
