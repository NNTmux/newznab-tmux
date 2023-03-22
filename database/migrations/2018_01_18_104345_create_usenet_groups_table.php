<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateUsenetGroupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('usenet_groups', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8';
            $table->collation = 'utf8_unicode_ci';
            $table->increments('id');
            $table->string('name', 255)->default('')->unique('ix_groups_name');
            $table->integer('backfill_target')->default(1);
            $table->bigInteger('first_record')->unsigned()->default(0);
            $table->dateTime('first_record_postdate')->nullable();
            $table->bigInteger('last_record')->unsigned()->default(0);
            $table->dateTime('last_record_postdate')->nullable();
            $table->dateTime('last_updated')->nullable();
            $table->integer('minfilestoformrelease')->nullable();
            $table->bigInteger('minsizetoformrelease')->nullable();
            $table->boolean('active')->default(0)->index('active');
            $table->boolean('backfill')->default(0);
            $table->string('description')->nullable()->default('');
        });

        DB::statement('ALTER TABLE usenet_groups AUTO_INCREMENT = 100000;');
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
