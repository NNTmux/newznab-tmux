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

        if (env('DB_CONNECTION') !== 'pgsql') {
            DB::statement('ALTER TABLE groups AUTO_INCREMENT = 100000;');
        } else {
            DB::statement('ALTER SEQUENCE groups_id_seq RESTART 1000000;');
        }
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
