<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSharingSitesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sharing_sites', function (Blueprint $table) {
            $table->increments('id');
            $table->string('site_name')->default('\'\'');
            $table->string('site_guid', 40)->default('\'\'');
            $table->dateTime('last_time')->nullable()->default(null);
            $table->dateTime('first_time')->nullable()->default(null);
            $table->boolean('enabled')->default(0);
            $table->integer('comments')->unsigned()->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('sharing_sites');
    }
}
