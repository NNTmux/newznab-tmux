<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSpotnabsourcesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('spotnabsources', function (Blueprint $table) {
            $table->bigInteger('id', true)->unsigned();
            $table->string('username', 64)->default('\'nntp\'');
            $table->string('useremail', 128)->default('\'spot@nntp.com\'');
            $table->string('usenetgroup', 64)->default('\'alt.binaries.backup\'');
            $table->string('publickey', 512);
            $table->boolean('active')->default(0);
            $table->string('description')->nullable()->default('\'\'');
            $table->dateTime('lastupdate')->nullable()->default(null);
            $table->dateTime('lastbroadcast')->nullable()->default(null);
            $table->bigInteger('lastarticle')->unsigned()->default(0);
            $table->dateTime('dateadded')->nullable()->default(null);
            $table->unique(['username', 'useremail', 'usenetgroup'], 'spotnabsources_ix1');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('spotnabsources');
    }
}
