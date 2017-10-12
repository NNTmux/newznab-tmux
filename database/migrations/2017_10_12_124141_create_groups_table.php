<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGroupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('name')->default('\'\'')->unique('ix_groups_name');
            $table->integer('backfill_target')->default(1);
            $table->bigInteger('first_record')->unsigned()->default(0);
            $table->dateTime('first_record_postdate')->nullable()->default(null);
            $table->bigInteger('last_record')->unsigned()->default(0);
            $table->dateTime('last_record_postdate')->nullable()->default(null);
            $table->dateTime('last_updated')->nullable()->default(null);
            $table->integer('minfilestoformrelease')->nullable()->default(null);
            $table->bigInteger('minsizetoformrelease')->nullable()->default(null);
            $table->boolean('active')->default(0)->index('active');
            $table->boolean('backfill')->default(0);
            $table->string('description')->nullable()->default('\'\'');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('groups');
    }
}
