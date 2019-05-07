<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateReleasesGroupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('releases_groups', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8';
            $table->collation = 'utf8_unicode_ci';
            $table->integer('releases_id')->unsigned()->default(0)->comment('FK to releases.id');
            $table->integer('groups_id')->unsigned()->default(0)->comment('FK to groups.id');
            $table->primary(['releases_id', 'groups_id']);
            $table->foreign('releases_id', 'FK_rg_releases')->references('id')->on('releases')->onUpdate('CASCADE')->onDelete('CASCADE');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('releases_groups');
    }
}
